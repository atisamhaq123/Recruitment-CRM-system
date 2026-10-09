<?php

namespace App\Http\Controllers;

use App\Services\Recruitment;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmController extends Controller
{
    public function __construct(public Recruitment $crm) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $counts = [
            'Open jobs' => $this->crm->query('jobs', $user)->where('status', 'Open')->count(),
            'Active applications' => $this->crm->query('applications', $user)->whereNotIn('status', ['Hired', 'Rejected'])->count(),
            'Upcoming interviews' => $this->crm->query('interviews', $user)->where('status', 'Scheduled')->where('scheduled_at', '>=', now())->count(),
            'Overdue tasks' => $this->crm->query('tasks', $user)->where('status', '!=', 'Done')->where('due_at', '<', now())->count(),
            'Successful placements' => $this->crm->query('placements', $user)->count(),
            'Placement fees' => $this->crm->query('placements', $user)->sum('fee'),
        ];
        $stages = [];
        foreach (config('recruitment.entities.applications.stages') as $stage) {
            $stages[$stage] = $this->crm->query('applications', $user)->where('status', $stage)->count();
        }
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $months[] = ['label' => $month->format('M'), 'fees' => (float) $this->crm->query('placements', $user)->whereBetween('start_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('fee')];
        }
        $interviews = $this->crm->query('interviews', $user)->where('status', 'Scheduled')->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->limit(4)->get();
        $tasks = $this->crm->query('tasks', $user)->where('status', '!=', 'Done')->orderBy('due_at')->limit(4)->get();
        $jobs = $this->crm->query('jobs', $user)->where('status', 'Open')->latest()->limit(5)->get();
        $activities = $this->crm->activities($user)->latest()->limit(5)->get();

        return view('dashboard', compact('counts', 'stages', 'months', 'interviews', 'tasks', 'jobs', 'activities'));
    }

    private function filtered(Request $request, string $entity): Builder
    {
        $definition = $this->crm->definition($entity);
        $request->validate(['q' => 'nullable|string|max:255', 'status' => 'nullable|string|max:60', 'owner' => 'nullable|integer', 'job_id' => 'nullable|integer']);
        $query = $this->crm->query($entity, $request->user());
        if ($request->filled('q')) {
            $query->where(function (Builder $nested) use ($request, $definition) {
                $nested->where('name', 'like', '%'.$request->string('q').'%');
                foreach ($definition['fields'] as $field => $metadata) {
                    if (in_array($metadata['type'], ['text', 'textarea', 'email'])) {
                        $nested->orWhere($field, 'like', '%'.$request->string('q').'%');
                    }
                }
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('owner')) {
            $query->where('owner_id', $request->integer('owner'));
        }
        if ($entity === 'applications' && $request->filled('job_id')) {
            $query->where('job_id', $request->integer('job_id'));
        }

        return $query;
    }

    public function index(Request $request, string $entity): View
    {
        $definition = $this->crm->definition($entity);
        $board = $request->query('view') === 'board' && isset($definition['stages']);
        $records = $this->filtered($request, $entity)->latest()->paginate($board ? 100 : 12)->withQueryString();
        $staff = $this->crm->staff($request->user())->orderBy('name')->get();
        $jobs = $entity === 'applications' ? $this->crm->query('jobs', $request->user())->orderBy('name')->get() : collect();

        return view('records.index', compact('entity', 'definition', 'records', 'board', 'staff', 'jobs'));
    }

    public function form(Request $request, string $entity, ?int $id = null): View
    {
        $definition = $this->crm->definition($entity);
        $record = $id ? $this->crm->find($entity, $id, $request->user()) : null;
        $references = [];
        foreach ($definition['fields'] as $field => $metadata) {
            if ($metadata['type'] === 'reference') {
                $query = $this->crm->query($metadata['entity'], $request->user());
                if ($entity === 'placements') {
                    $query->where('status', 'Hired');
                }
                $references[$field] = $query->orderBy('name')->get();
            }
        }
        $staff = $this->crm->staff($request->user())->orderBy('name')->get();

        return view('records.form', compact('entity', 'definition', 'record', 'references', 'staff'));
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        $id = $this->crm->save($entity, $request->all(), $request->user());

        return redirect()->route('records.show', [$entity, $id])->with('success', 'Record created successfully.');
    }

    public function update(Request $request, string $entity, int $id): RedirectResponse
    {
        $this->crm->save($entity, $request->all(), $request->user(), $id);

        return redirect()->route('records.show', [$entity, $id])->with('success', 'Changes saved.');
    }

    public function show(Request $request, string $entity, int $id): View
    {
        $definition = $this->crm->definition($entity);
        $record = $this->crm->find($entity, $id, $request->user());
        $references = [];
        foreach ($definition['fields'] as $field => $metadata) {
            if ($metadata['type'] === 'reference' && $record->$field) {
                $references[$field] = $this->crm->find($metadata['entity'], $record->$field, $request->user());
            }
        }
        $request->validate(['q' => 'nullable|string|max:255']);
        $activities = DB::table('activities')->where('entity', $entity)->where('record_id', $id)->when($request->filled('q'), fn (Builder $q) => $q->where('body', 'like', '%'.$request->string('q').'%'))->latest()->paginate(10)->withQueryString();
        $documents = DB::table('documents')->where('entity', $entity)->where('record_id', $id)->latest()->get();
        $related = [];
        foreach (config('recruitment.entities') as $other => $config) {
            foreach ($config['fields'] as $field => $metadata) {
                if (($metadata['entity'] ?? null) === $entity) {
                    $related[$other] = $this->crm->query($other, $request->user())->where($field, $id)->latest()->limit(30)->get();
                }
            }
        }
        $owner = DB::table('users')->where('id', $record->owner_id)->first();

        return view('records.show', compact('entity', 'definition', 'record', 'references', 'activities', 'documents', 'related', 'owner'));
    }

    public function stage(Request $request, string $entity, int $id): JsonResponse
    {
        $definition = $this->crm->definition($entity);
        abort_unless(isset($definition['stages']), 404);
        $request->validate(['status' => ['required', Rule::in($definition['stages'])], 'version' => 'required|integer|min:1']);
        $record = $this->crm->find($entity, $id, $request->user());
        $version = $request->integer('version');
        $this->crm->save($entity, [...(array) $record, 'status' => $request->input('status'), 'version' => $version], $request->user(), $id);

        return response()->json(['message' => 'Stage updated', 'version' => $version + 1]);
    }

    public function note(Request $request, string $entity, int $id): RedirectResponse
    {
        $this->crm->find($entity, $id, $request->user());
        $data = $request->validate(['body' => 'required|string|max:10000', 'action' => 'required|in:Note,Call,Email logged,Meeting']);
        $this->crm->audit($entity, $id, $request->user(), $data['action'], $data['body']);

        return back()->with('success', 'Activity logged. Email entries are manual history; no message was sent.');
    }

    public function upload(Request $request, string $entity, int $id): RedirectResponse
    {
        $this->crm->find($entity, $id, $request->user());
        $request->validate(['document' => 'required|file|max:10240|mimes:pdf,docx,txt|extensions:pdf,docx,txt']);
        $file = $request->file('document');
        if (strtolower($file->getClientOriginalExtension()) === 'docx') {
            $zip = new \ZipArchive;
            $valid = $zip->open($file->getRealPath()) === true && $zip->locateName('word/document.xml') !== false && $zip->locateName('word/vbaProject.bin') === false;
            if (! $valid) {
                throw ValidationException::withMessages(['document' => 'Upload a valid DOCX document without macros.']);
            }
            $zip->close();
        }
        $path = $file->store('documents', 'local');
        abort_unless($path, 500, 'Unable to store document.');
        try {
            DB::transaction(function () use ($request, $entity, $id, $file, $path) {
                $name = mb_substr(preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '_', $file->getClientOriginalName()), 0, 200);
                DB::table('documents')->insert(['entity' => $entity, 'record_id' => $id, 'user_id' => $request->user()->id, 'name' => $name, 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);
                $this->crm->audit($entity, $id, $request->user(), 'Document uploaded', $name);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Document securely uploaded.');
    }

    public function download(Request $request, int $document): BinaryFileResponse
    {
        $file = DB::table('documents')->where('id', $document)->first();
        abort_unless($file, 404);
        $this->crm->find($file->entity, $file->record_id, $request->user());
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        $this->crm->audit($file->entity, $file->record_id, $request->user(), 'Document downloaded', $file->name);

        return response()->download(Storage::disk('local')->path($file->path), $file->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, string $entity, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $record = $this->crm->find($entity, $id, $request->user());
        try {
            DB::transaction(function () use ($request, $entity, $id, $record) {
                DB::table($this->crm->definition($entity)['table'])->where('id', $id)->delete();
                $this->crm->audit($entity, $id, $request->user(), 'Deleted', $record->name);
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503'])) {
                return back()->withErrors(['delete' => 'This record has linked records. Remove those first.']);
            }
            throw $exception;
        }
        foreach (DB::table('documents')->where('entity', $entity)->where('record_id', $id)->get() as $document) {
            Storage::disk('local')->delete($document->path);
        }
        DB::table('documents')->where('entity', $entity)->where('record_id', $id)->delete();

        return redirect()->route('records.index', $entity)->with('success', 'Record deleted.');
    }

    public function export(Request $request, string $entity): StreamedResponse
    {
        $definition = $this->crm->definition($entity);
        $query = $this->filtered($request, $entity)->orderBy('id');

        return response()->streamDownload(function () use ($query, $definition) {
            $stream = fopen('php://output', 'w');
            $fields = [...array_keys($definition['fields']), 'owner_id'];
            fputcsv($stream, $fields, ',', '"', '');
            foreach ($query->cursor() as $row) {
                $cells = array_map(function (string $field) use ($row) {
                    $value = (string) ($row->$field ?? '');

                    return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
                }, $fields);
                fputcsv($stream, $cells, ',', '"', '');
            }
            fclose($stream);
        }, $entity.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, string $entity): RedirectResponse
    {
        abort_unless(in_array($entity, ['clients', 'candidates', 'jobs']), 404);
        $request->validate(['csv' => 'required|file|max:2048|extensions:csv,txt']);
        $stream = fopen($request->file('csv')->getRealPath(), 'r');
        $header = fgetcsv($stream, 0, ',', '"', '');
        if (! $header) {
            fclose($stream);
            throw ValidationException::withMessages(['csv' => 'The CSV is empty.']);
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $allowed = [...array_keys($this->crm->definition($entity)['fields']), 'owner_id'];
        if (count($header) !== count(array_unique($header)) || array_diff($header, $allowed)) {
            fclose($stream);
            throw ValidationException::withMessages(['csv' => 'Use the exact column names from an export of this module.']);
        }
        $count = 0;
        try {
            DB::transaction(function () use ($stream, $header, $request, $entity, &$count) {
                while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                    if ($row === [null]) {
                        continue;
                    }
                    $count++;
                    if ($count > 500 || count($row) !== count($header)) {
                        throw ValidationException::withMessages(['csv' => 'Maximum 500 rows; every row must match the header. Nothing was imported.']);
                    }
                    $data = array_combine($header, array_map(fn ($value) => $value === '' ? null : $value, $row));
                    $data['owner_id'] ??= $request->user()->id;
                    try {
                        $this->crm->save($entity, $data, $request->user());
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages(['csv' => 'Row '.($count + 1).': '.implode(' ', $exception->validator->errors()->all()).' Nothing was imported.']);
                    }
                }
            });
        } finally {
            fclose($stream);
        }

        return back()->with('success', "$count records imported.");
    }

    public function activity(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:255']);
        $activities = $this->crm->activities($request->user())->when($request->filled('q'), fn (Builder $q) => $q->where('body', 'like', '%'.$request->string('q').'%'))->latest()->paginate(20)->withQueryString();

        return view('activity', compact('activities'));
    }

    public function contact(Request $request, int $client, ?int $contact = null): RedirectResponse
    {
        $this->crm->find('clients', $client, $request->user());
        $data = $request->validate(['name' => 'required|string|max:255', 'job_title' => 'nullable|string|max:255', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:255']);
        DB::transaction(function () use ($request, $client, $contact, $data) {
            if ($contact) {
                $existing = DB::table('client_contacts')->where('client_id', $client)->where('id', $contact)->lockForUpdate()->first();
                abort_unless($existing, 404);
                DB::table('client_contacts')->where('id', $contact)->update([...$data, 'updated_at' => now()]);
            } else {
                DB::table('client_contacts')->insert([...$data, 'client_id' => $client, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->crm->audit('clients', $client, $request->user(), $contact ? 'Contact updated' : 'Contact added', $data['name'].' · '.$data['email']);
        });

        return redirect()->route('records.show', ['clients', $client])->with('success', 'Hiring contact saved.');
    }

    public function deleteContact(Request $request, int $client, int $contact): RedirectResponse
    {
        $this->crm->find('clients', $client, $request->user());
        $existing = DB::table('client_contacts')->where('client_id', $client)->where('id', $contact)->first();
        abort_unless($existing, 404);
        DB::transaction(function () use ($request, $client, $contact, $existing) {
            DB::table('client_contacts')->where('id', $contact)->delete();
            $this->crm->audit('clients', $client, $request->user(), 'Contact removed', $existing->name);
        });

        return redirect()->route('records.show', ['clients', $client])->with('success', 'Contact removed.');
    }

    public function reports(Request $request): View
    {
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->endOfYear()->toDateString());
        $rows = [];
        foreach ($this->crm->staff($request->user())->orderBy('name')->get() as $member) {
            $query = fn (string $entity) => $this->crm->query($entity, $request->user())->where('owner_id', $member->id)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
            $rows[] = ['member' => $member, 'candidates' => $query('candidates')->count(), 'applications' => $query('applications')->count(), 'interviews' => $query('interviews')->count(), 'placements' => $query('placements')->count(), 'fees' => $query('placements')->sum('fee'), 'paid' => $query('placements')->where('status', 'Paid')->sum('fee')];
        }

        return view('reports', compact('rows', 'from', 'to'));
    }
}
