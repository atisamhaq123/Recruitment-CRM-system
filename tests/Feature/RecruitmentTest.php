<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    private function actor(string $role = 'recruiter'): User
    {
        return User::where('email', "$role@talentflow.test")->firstOrFail();
    }

    private function record(string $table, int $owner = 3): object
    {
        return DB::table($table)->where('owner_id', $owner)->orderBy('id')->first();
    }

    public function test_login_uses_hashed_passwords_and_denies_disabled_accounts(): void
    {
        $user = $this->actor();
        $this->assertTrue(Hash::check('TalentFlow!2026', $user->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'TalentFlow!2026'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $user->update(['active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'TalentFlow!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_per_account_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'admin@talentflow.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'admin@talentflow.test', 'password' => 'wrong'])->assertStatus(429);
        $this->post('/login', ['email' => 'recruiter@talentflow.test', 'password' => 'TalentFlow!2026'])->assertRedirect('/');
    }

    public function test_hiring_contacts_are_managed_under_client_permissions(): void
    {
        $client = $this->record('clients');
        $other = $this->record('clients', 6);
        $payload = ['name' => 'Casey Jones', 'job_title' => 'Hiring Manager', 'email' => 'casey@example.com', 'phone' => '+1 202 555 0199'];
        $this->actingAs($this->actor())->post("/clients/{$client->id}/contacts", $payload)->assertRedirect();
        $contact = DB::table('client_contacts')->where('name', 'Casey Jones')->first();
        $this->put("/clients/{$client->id}/contacts/{$contact->id}", [...$payload, 'job_title' => 'Director'])->assertRedirect();
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id, 'job_title' => 'Director']);
        $this->post("/clients/{$other->id}/contacts", $payload)->assertNotFound();
        $this->actingAs($this->actor('manager'))->get("/workspace/clients/{$client->id}")->assertOk()->assertSee('Casey Jones');
        $this->delete("/clients/{$other->id}/contacts/{$contact->id}")->assertNotFound();
        $this->delete("/clients/{$client->id}/contacts/{$contact->id}")->assertRedirect();
        $this->assertDatabaseMissing('client_contacts', ['id' => $contact->id]);
    }

    public function test_job_salary_ranges_and_parent_ownership_are_validated(): void
    {
        $job = $this->record('vacancies');
        $this->actingAs($this->actor())->put("/workspace/jobs/{$job->id}", [...(array) $job, 'salary_min' => 100000, 'salary_max' => 50000])->assertSessionHasErrors('salary_max');
        $this->put("/workspace/jobs/{$job->id}", [...(array) $job, 'client_id' => $this->record('clients', 6)->id])->assertNotFound();
        $this->put("/workspace/jobs/{$job->id}", [...(array) $job, 'salary_min' => 90000, 'salary_max' => 125000])->assertRedirect();
        $this->assertDatabaseHas('vacancies', ['id' => $job->id, 'salary_min' => 90000, 'salary_max' => 125000]);
    }

    public function test_all_roles_render_dashboard_and_all_accessible_modules(): void
    {
        foreach (['admin', 'manager', 'recruiter'] as $role) {
            $this->actingAs($this->actor($role))->get('/')->assertOk()->assertSee('Hiring pipeline');
            foreach (config('recruitment.entities') as $entity => $definition) {
                $this->get("/workspace/$entity")->assertOk();
                $this->get("/workspace/$entity/create")->assertOk();
                $record = $this->record($definition['table']);
                $this->get("/workspace/$entity/{$record->id}")->assertOk();
                $this->get("/workspace/$entity/{$record->id}/edit")->assertOk();
                $this->get("/workspace/$entity/export")->assertOk();
            }
            $this->get('/workspace/applications?view=board')->assertOk();
            $this->get('/workspace/clients?view=board')->assertOk();
            $this->get('/reports')->assertOk();
            $this->get('/activity')->assertOk();
        }
        $this->actingAs($this->actor('admin'))->get('/admin')->assertOk();
    }

    public function test_recruiter_and_manager_access_is_enforced_on_records_exports_and_reports(): void
    {
        $other = $this->record('candidates', 6);
        $sameTeam = $this->record('candidates', 4);
        $this->actingAs($this->actor())->get("/workspace/candidates/{$other->id}")->assertNotFound();
        $this->get("/workspace/candidates/{$sameTeam->id}")->assertNotFound();
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/teams', ['name' => 'Unauthorized'])->assertForbidden();
        $this->get('/workspace/candidates?q='.urlencode($other->name))->assertDontSee($other->email);
        $export = $this->get('/workspace/candidates/export')->streamedContent();
        $this->assertStringNotContainsString($other->email, $export);
        $this->get('/reports')->assertDontSee('Leo Brooks')->assertDontSee('Maya Patel');
        $this->actingAs($this->actor('manager'))->get("/workspace/candidates/{$sameTeam->id}")->assertOk();
        $this->get("/workspace/candidates/{$other->id}")->assertNotFound();
        $this->get('/reports')->assertSee('Maya Patel')->assertDontSee('Leo Brooks');
        $this->actingAs($this->actor('admin'))->get("/workspace/candidates/{$other->id}")->assertOk();
    }

    public function test_client_and_candidate_creation_validation_and_assignment(): void
    {
        $this->actingAs($this->actor())->post('/workspace/clients', [
            'name' => 'Meridian Design', 'industry' => 'Design', 'location' => 'Remote', 'contact_name' => 'Taylor Lane', 'email' => 'taylor@example.com', 'status' => 'New lead', 'owner_id' => 6,
        ])->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => 'Meridian Design', 'owner_id' => 3]);
        $this->post('/workspace/candidates', ['name' => 'Missing fields'])->assertSessionHasErrors(['email', 'skills']);
        $candidate = (array) $this->record('candidates');
        unset($candidate['id'], $candidate['version']);
        $candidate['name'] = 'Taylor Lane';
        $candidate['email'] = 'talent@example.com';
        $this->post('/workspace/candidates', $candidate)->assertRedirect();
        $this->assertDatabaseHas('candidates', ['name' => 'Taylor Lane', 'owner_id' => 3]);
    }

    public function test_application_status_is_per_job_and_duplicate_pairs_are_rejected(): void
    {
        $candidate = $this->record('candidates');
        $existing = DB::table('applications')->where('candidate_id', $candidate->id)->first();
        $used = DB::table('applications')->where('candidate_id', $candidate->id)->pluck('job_id');
        $job = DB::table('vacancies')->where('owner_id', 3)->whereNotIn('id', $used)->first();
        $payload = ['candidate_id' => $candidate->id, 'job_id' => $job->id, 'status' => 'Offer', 'source' => 'Direct', 'owner_id' => 3];
        $this->actingAs($this->actor())->post('/workspace/applications', $payload)->assertRedirect();
        $this->assertDatabaseHas('applications', ['candidate_id' => $candidate->id, 'job_id' => $job->id, 'status' => 'Offer']);
        $this->assertDatabaseHas('applications', ['id' => $existing->id, 'status' => $existing->status]);
        $this->post('/workspace/applications', $payload)->assertSessionHasErrors('candidate_id');
        $payload['job_id'] = $this->record('vacancies', 6)->id;
        $this->post('/workspace/applications', $payload)->assertNotFound();
    }

    public function test_both_pipelines_persist_history_and_reject_stale_invalid_or_unauthorized_updates(): void
    {
        foreach (['clients' => 'Proposal', 'applications' => 'Shortlisted'] as $entity => $stage) {
            $record = $this->record($entity);
            $this->actingAs($this->actor())->patchJson("/workspace/$entity/{$record->id}/stage", ['status' => $stage, 'version' => $record->version])->assertOk()->assertJsonPath('version', 2);
            $this->assertDatabaseHas($entity, ['id' => $record->id, 'status' => $stage]);
            $this->assertDatabaseHas('activities', ['entity' => $entity, 'record_id' => $record->id, 'action' => 'Updated']);
            $this->patchJson("/workspace/$entity/{$record->id}/stage", ['status' => $stage, 'version' => 1])->assertConflict();
            $this->patchJson("/workspace/$entity/{$record->id}/stage", ['status' => 'Bogus', 'version' => 2])->assertUnprocessable();
            $other = $this->record($entity, 6);
            $this->patchJson("/workspace/$entity/{$other->id}/stage", ['status' => $stage, 'version' => 1])->assertNotFound();
        }
    }

    public function test_managers_can_assign_their_team_but_cannot_assign_another_team(): void
    {
        $candidate = $this->record('candidates');
        $this->actingAs($this->actor('manager'))->put("/workspace/candidates/{$candidate->id}", [...(array) $candidate, 'owner_id' => 4])->assertRedirect();
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'owner_id' => 4]);
        $this->put("/workspace/candidates/{$candidate->id}", [...(array) $candidate, 'owner_id' => 6, 'version' => 2])->assertSessionHasErrors('owner_id');
        $job = $this->record('vacancies');
        $this->put("/workspace/jobs/{$job->id}", [...(array) $job, 'owner_id' => 4])->assertSessionHasErrors('client_id');
    }

    public function test_linked_record_visibility_revokes_after_parent_reassignment(): void
    {
        $application = $this->record('applications');
        DB::table('candidates')->where('id', $application->candidate_id)->update(['owner_id' => 6]);
        $this->actingAs($this->actor())->get("/workspace/applications/{$application->id}")->assertNotFound();
        $this->get('/workspace/applications/export')->assertOk();
        $this->actingAs($this->actor('manager'))->get("/workspace/applications/{$application->id}")->assertNotFound();
    }

    public function test_interview_scheduling_feedback_and_meeting_validation(): void
    {
        $application = $this->record('applications');
        $payload = ['name' => 'Technical interview', 'application_id' => $application->id, 'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'participants' => 'Candidate, Hiring Manager', 'meeting_url' => 'https://meet.example.com/room', 'status' => 'Scheduled', 'owner_id' => 3];
        $this->actingAs($this->actor())->post('/workspace/interviews', $payload)->assertRedirect();
        $interview = DB::table('interviews')->where('name', 'Technical interview')->first();
        $this->put("/workspace/interviews/{$interview->id}", [...$payload, 'status' => 'Passed', 'feedback' => 'Strong technical skills.', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('interviews', ['id' => $interview->id, 'status' => 'Passed', 'feedback' => 'Strong technical skills.']);
        $this->post('/workspace/interviews', [...$payload, 'meeting_url' => 'javascript:alert(1)'])->assertSessionHasErrors('meeting_url');
        $this->post('/workspace/interviews', [...$payload, 'application_id' => $this->record('applications', 6)->id])->assertNotFound();
    }

    public function test_documents_are_private_validated_and_authorized_at_download_time(): void
    {
        $candidate = $this->record('candidates');
        $file = UploadedFile::fake()->createWithContent('profile.txt', 'Candidate CV and experience.');
        $this->actingAs($this->actor())->post("/workspace/candidates/{$candidate->id}/documents", ['document' => $file])->assertRedirect();
        $document = DB::table('documents')->where('name', 'profile.txt')->first();
        Storage::disk('local')->assertExists($document->path);
        $this->get("/documents/{$document->id}")->assertOk()->assertDownload('profile.txt');
        $this->post("/workspace/candidates/{$candidate->id}/documents", ['document' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo "unsafe";')])->assertSessionHasErrors('document');
        $this->post("/workspace/candidates/{$candidate->id}/documents", ['document' => UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf')])->assertSessionHasErrors('document');
        $this->actingAs($this->actor('finance-recruiter'))->get("/documents/{$document->id}")->assertNotFound();
        $this->actingAs($this->actor('manager'))->get("/documents/{$document->id}")->assertOk();
        DB::table('candidates')->where('id', $candidate->id)->update(['owner_id' => 6]);
        $this->get("/documents/{$document->id}")->assertNotFound();
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_placements_require_hired_application_and_track_invoice_and_payment(): void
    {
        $application = $this->record('applications');
        $payload = ['application_id' => $application->id, 'start_date' => now()->addDays(14)->toDateString(), 'fee' => 18000, 'status' => 'Pending', 'owner_id' => 3];
        $this->actingAs($this->actor())->post('/workspace/placements', $payload)->assertSessionHasErrors('application_id');
        $this->patchJson("/workspace/applications/{$application->id}/stage", ['status' => 'Hired', 'version' => 1])->assertOk();
        $this->post('/workspace/placements', $payload)->assertRedirect();
        $placement = DB::table('placements')->where('application_id', $application->id)->first();
        $this->put("/workspace/placements/{$placement->id}", [...$payload, 'status' => 'Paid', 'version' => 1])->assertSessionHasErrors('invoice_number');
        $this->put("/workspace/placements/{$placement->id}", [...$payload, 'status' => 'Paid', 'invoice_number' => 'TF-9001', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('placements', ['id' => $placement->id, 'fee' => 18000, 'status' => 'Paid', 'invoice_number' => 'TF-9001']);
        $this->post('/workspace/placements', $payload)->assertSessionHasErrors('application_id');
        $this->patchJson("/workspace/applications/{$application->id}/stage", ['status' => 'Rejected', 'version' => 2])->assertUnprocessable();
        $this->get('/reports')->assertSee('TF')->assertSee('Collected fees');
    }

    public function test_csv_import_is_atomic_and_export_prevents_spreadsheet_formula_injection(): void
    {
        $before = DB::table('clients')->count();
        $header = "name,industry,location,contact_name,email,status\n";
        $bad = $header."Imported One,Software,Remote,Taylor,taylor@example.com,New lead\nImported Two,Software,Remote,Taylor,not-email,New lead\n";
        $this->actingAs($this->actor())->post('/workspace/clients/import', ['csv' => UploadedFile::fake()->createWithContent('clients.csv', $bad)])->assertSessionHasErrors('csv');
        $this->assertSame($before, DB::table('clients')->count());
        $good = $header."=HYPERLINK(1),Software,Remote,Taylor,taylor@example.com,New lead\n";
        $this->post('/workspace/clients/import', ['csv' => UploadedFile::fake()->createWithContent('clients.csv', $good)])->assertRedirect();
        $this->assertDatabaseHas('clients', ['name' => '=HYPERLINK(1)', 'owner_id' => 3]);
        $export = $this->get('/workspace/clients/export')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(1)", $export);
        $this->post('/workspace/clients/import', ['csv' => UploadedFile::fake()->createWithContent('clients.csv', "name,name\nA,B\n")])->assertSessionHasErrors('csv');
    }

    public function test_admin_can_manage_users_teams_and_settings_but_cannot_demote_self(): void
    {
        $this->actingAs($this->actor('admin'))->post('/admin/teams', ['name' => 'Executive Search'])->assertRedirect();
        $team = DB::table('teams')->where('name', 'Executive Search')->value('id');
        $this->post('/admin/users', ['name' => 'New Recruiter', 'email' => 'new@example.com', 'password' => 'StrongPassword!2026', 'role' => 'recruiter', 'team_id' => $team, 'active' => 1])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'team_id' => $team]);
        $this->put('/admin/users/1', ['name' => 'Alex Morgan', 'email' => 'admin@talentflow.test', 'role' => 'recruiter', 'team_id' => 1, 'active' => 1])->assertSessionHasErrors('role');
        $this->post('/admin/settings', ['name' => 'Agency One', 'email' => 'hello@example.com'])->assertRedirect();
        $this->assertDatabaseHas('agency_settings', ['id' => 1, 'name' => 'Agency One']);
        $this->actingAs($this->actor())->post('/admin/settings', ['name' => 'Stolen', 'email' => 'x@example.com'])->assertForbidden();
    }

    public function test_tasks_can_complete_and_notes_are_escaped(): void
    {
        $task = $this->record('tasks');
        $this->actingAs($this->actor())->put("/workspace/tasks/{$task->id}", [...(array) $task, 'status' => 'Done'])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'Done']);
        $candidate = $this->record('candidates');
        $this->post("/workspace/candidates/{$candidate->id}/notes", ['action' => 'Note', 'body' => '<script>alert(1)</script>'])->assertRedirect();
        $this->get("/workspace/candidates/{$candidate->id}")->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $other = $this->record('candidates', 6);
        $this->post("/workspace/candidates/{$other->id}/notes", ['action' => 'Note', 'body' => 'Unauthorized'])->assertNotFound();
    }

    public function test_csrf_protects_mutations_and_login(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/login', ['email' => 'admin@talentflow.test', 'password' => 'TalentFlow!2026'])->assertStatus(419);
        $this->actingAs($this->actor())->post('/workspace/clients', [])->assertStatus(419);
    }

    public function test_disabled_sessions_are_revoked_and_recruiters_cannot_delete_records(): void
    {
        $user = $this->actor();
        $candidate = $this->record('candidates');
        $this->actingAs($user)->delete("/workspace/candidates/{$candidate->id}")->assertForbidden();
        $user->update(['active' => false]);
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
