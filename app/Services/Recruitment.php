<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Recruitment
{
    public function definition(string $entity): array
    {
        $definition = config("recruitment.entities.$entity");
        abort_unless(is_array($definition), 404);

        return $definition;
    }

    public function staff(User $user): Builder
    {
        $query = DB::table('users')->where('active', true);
        if ($user->role === 'manager') {
            $query->whereNotNull('team_id')->where('team_id', $user->team_id)->whereIn('role', ['manager', 'recruiter']);
        } elseif ($user->role !== 'admin') {
            $query->where('id', $user->id);
        }

        return $query;
    }

    public function query(string $entity, User $user): Builder
    {
        $definition = $this->definition($entity);
        $table = $definition['table'];
        $query = DB::table($table);
        if ($user->role !== 'admin') {
            $query->whereIn("$table.owner_id", $this->staff($user)->select('id'));
            foreach ($definition['fields'] as $field => $metadata) {
                if ($metadata['type'] === 'reference') {
                    $related = $this->definition($metadata['entity'])['table'];
                    $query->where(function (Builder $nested) use ($table, $field, $metadata, $related, $user) {
                        if (! str_starts_with($metadata['rules'], 'required')) {
                            $nested->whereNull("$table.$field")->orWhereIn("$table.$field", $this->query($metadata['entity'], $user)->select("$related.id"));
                        } else {
                            $nested->whereIn("$table.$field", $this->query($metadata['entity'], $user)->select("$related.id"));
                        }
                    });
                }
            }
        }

        return $query;
    }

    public function find(string $entity, int $id, User $user): object
    {
        $record = $this->query($entity, $user)->where('id', $id)->first();
        abort_unless($record, 404);

        return $record;
    }

    public function audit(string $entity, int $id, User $user, string $action, string $body): void
    {
        DB::table('activities')->insert(['entity' => $entity, 'record_id' => $id, 'user_id' => $user->id, 'action' => $action, 'body' => $body, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function activities(User $user): Builder
    {
        return DB::table('activities')->where(function (Builder $query) use ($user) {
            foreach (config('recruitment.entities') as $entity => $definition) {
                $query->orWhere(function (Builder $nested) use ($entity, $definition, $user) {
                    $nested->where('entity', $entity)->whereIn('record_id', $this->query($entity, $user)->select($definition['table'].'.id'));
                });
            }
        });
    }

    public function save(string $entity, array $input, User $user, ?int $id = null): int
    {
        return DB::transaction(function () use ($entity, $input, $user, $id) {
            $definition = $this->definition($entity);
            $previous = $id ? $this->find($entity, $id, $user) : null;
            if ($previous) {
                DB::table($definition['table'])->where('id', $id)->lockForUpdate()->first();
                $previous = $this->find($entity, $id, $user);
                abort_if((int) ($input['version'] ?? 0) !== $previous->version, 409, 'This record changed. Refresh and try again.');
            }
            $rules = array_map(fn (array $field) => $field['rules'], $definition['fields']);
            $rules['owner_id'] = ['required', 'integer', Rule::in($this->staff($user)->pluck('id')->all())];
            if ($user->role === 'recruiter') {
                $input['owner_id'] = $user->id;
            }
            if ($entity === 'jobs') {
                $rules['salary_max'] .= '|gte:salary_min';
            }
            $data = Validator::make($input, $rules)->validate();
            $owner = User::findOrFail($data['owner_id']);
            foreach ($definition['fields'] as $field => $metadata) {
                if ($metadata['type'] === 'reference' && ! empty($data[$field])) {
                    $related = $this->find($metadata['entity'], (int) $data[$field], $user);
                    if (! $this->query($metadata['entity'], $owner)->where('id', $related->id)->exists()) {
                        throw ValidationException::withMessages([$field => 'The assigned recruiter must also have access to this linked record.']);
                    }
                }
                if ($metadata['type'] === 'datetime-local' && isset($data[$field])) {
                    $data[$field] = Carbon::parse($data[$field])->format('Y-m-d H:i:s');
                }
            }
            if ($entity === 'applications') {
                DB::table('candidates')->where('id', $data['candidate_id'])->lockForUpdate()->first();
                $duplicate = DB::table('applications')->where('candidate_id', $data['candidate_id'])->where('job_id', $data['job_id'])->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['candidate_id' => 'This candidate already has an application for this job.']);
                }
                if ($previous && DB::table('placements')->where('application_id', $id)->exists() && ($data['status'] !== 'Hired' || (int) $data['candidate_id'] !== $previous->candidate_id || (int) $data['job_id'] !== $previous->job_id)) {
                    throw ValidationException::withMessages(['status' => 'A placed application must stay Hired with its original candidate and job.']);
                }
                $candidate = $this->find('candidates', (int) $data['candidate_id'], $user);
                $job = $this->find('jobs', (int) $data['job_id'], $user);
                if (! $previous && $job->status !== 'Open') {
                    throw ValidationException::withMessages(['job_id' => 'New applications require an open job.']);
                }
                $data['name'] = mb_substr($candidate->name.' · '.$job->name, 0, 255);
            }
            if ($entity === 'placements') {
                $application = $this->find('applications', (int) $data['application_id'], $user);
                $application = DB::table('applications')->where('id', $application->id)->lockForUpdate()->first();
                if ($application->status !== 'Hired') {
                    throw ValidationException::withMessages(['application_id' => 'Move the application to Hired before recording a placement.']);
                }
                if (DB::table('placements')->where('application_id', $application->id)->when($id, fn (Builder $q) => $q->where('id', '!=', $id))->exists()) {
                    throw ValidationException::withMessages(['application_id' => 'This application already has a placement.']);
                }
                if ($data['status'] !== 'Pending' && empty($data['invoice_number'])) {
                    throw ValidationException::withMessages(['invoice_number' => 'An invoice reference is required for invoiced or paid placements.']);
                }
                $data['name'] = $application->name;
            }
            $data['updated_at'] = now();
            $data['version'] = $previous ? $previous->version + 1 : 1;
            if ($id) {
                DB::table($definition['table'])->where('id', $id)->update($data);
            } else {
                $data['created_at'] = now();
                $id = DB::table($definition['table'])->insertGetId($data);
            }
            $changes = $previous ? collect($data)->except(['updated_at', 'version'])->filter(fn ($value, $field) => (string) $value !== (string) ($previous->$field ?? ''))->map(fn ($value, $field) => str_replace('_', ' ', $field).': '.mb_substr((string) ($previous->$field ?? '—'), 0, 180).' → '.mb_substr((string) $value, 0, 180))->implode('; ') : 'Created '.$definition['singular'].' '.$data['name'];
            $this->audit($entity, $id, $user, $previous ? 'Updated' : 'Created', $changes ?: 'Saved without field changes');

            return $id;
        }, 3);
    }
}
