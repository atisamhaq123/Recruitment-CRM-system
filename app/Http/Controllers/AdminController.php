<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);
        $request->validate(['edit' => 'nullable|integer']);
        $users = User::orderBy('name')->get();
        $teams = DB::table('teams')->orderBy('name')->get();
        $selected = $request->filled('edit') ? User::findOrFail($request->integer('edit')) : null;
        $settings = DB::table('agency_settings')->first();

        return view('admin', compact('users', 'teams', 'selected', 'settings'));
    }

    public function user(Request $request, ?int $id = null): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'password' => ($id ? 'nullable' : 'required').'|string|min:12|max:128', 'role' => 'required|in:admin,manager,recruiter',
            'team_id' => 'nullable|required_unless:role,admin|integer|exists:teams,id', 'active' => 'required|boolean',
        ]);
        DB::transaction(function () use ($request, $data, $id) {
            $admins = User::where('role', 'admin')->where('active', true)->lockForUpdate()->get();
            $user = $id ? User::findOrFail($id) : new User;
            if ($id === $request->user()->id && (! $data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'You cannot disable or demote your own account.']);
            }
            if ($user->role === 'admin' && $user->active && $admins->count() <= 1 && (! $data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active administrator.']);
            }
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $user->fill($data)->save();
        });

        return redirect()->route('admin')->with('success', 'User saved.');
    }

    public function team(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['id' => 'nullable|integer|exists:teams,id', 'name' => ['required', 'string', 'max:255', Rule::unique('teams')->ignore($request->input('id'))]]);
        if (! empty($data['id'])) {
            DB::table('teams')->where('id', $data['id'])->update(['name' => $data['name'], 'updated_at' => now()]);
        } else {
            DB::table('teams')->insert(['name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('success', 'Team saved.');
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255']);
        DB::table('agency_settings')->updateOrInsert(['id' => 1], [...$data, 'updated_at' => now()]);

        return back()->with('success', 'Agency settings saved.');
    }
}
