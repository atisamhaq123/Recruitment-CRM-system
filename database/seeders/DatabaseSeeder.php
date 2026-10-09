<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('email', 'admin@talentflow.test')->exists()) {
            $this->seedContacts();

            return;
        }
        $team = DB::table('teams')->insertGetId(['name' => 'Technology & Product', 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('teams')->insertGetId(['name' => 'Finance & Operations', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            ['Alex Morgan', 'admin', 'admin', $team], ['Olivia Bennett', 'manager', 'manager', $team],
            ['Jamie Chen', 'recruiter', 'recruiter', $team], ['Maya Patel', 'maya', 'recruiter', $team],
            ['Marcus Reed', 'finance-manager', 'manager', $other], ['Leo Brooks', 'finance-recruiter', 'recruiter', $other],
        ] as [$name, $login, $role, $teamId]) {
            User::create(['name' => $name, 'email' => "$login@talentflow.test", 'password' => 'TalentFlow!2026', 'role' => $role, 'team_id' => $teamId, 'active' => true]);
        }
        DB::table('agency_settings')->insert(['id' => 1, 'name' => 'TalentFlow', 'email' => 'hello@talentflow.test', 'created_at' => now(), 'updated_at' => now()]);
        $companies = ['Northstar Labs', 'LinearWorks', 'Arcadia Health', 'Orbit Commerce', 'Vertex Studio', 'Lumina AI', 'Clearpath Finance', 'Evergreen Partners', 'Atlas Logistics'];
        $names = ['Sophie Williams', 'Daniel Kim', 'Aisha Khan', 'Noah Anderson', 'Emma Wilson', 'Oliver Davis', 'Isabella Garcia', 'Liam Thompson', 'Zoe Martin', 'Ethan Clarke', 'Amelia Scott', 'Lucas Brown', 'Mia Robinson', 'James Lewis', 'Charlotte Lee', 'Benjamin Hall', 'Harper Young', 'Henry Allen', 'Grace Walker', 'Elijah King', 'Chloe Wright', 'Arjun Mehta', 'Ella Green', 'Jack Adams'];
        $titles = ['Senior Product Designer', 'Full Stack Engineer', 'Product Manager', 'Data Analyst'];
        $stages = config('recruitment.entities.applications.stages');
        $sales = config('recruitment.entities.clients.stages');
        $index = 0;
        foreach ([3, 4, 6] as $group => $ownerId) {
            $clients = [];
            for ($c = 0; $c < 3; $c++) {
                $clients[] = $this->insert('clients', ['owner_id' => $ownerId, 'name' => $companies[$group * 3 + $c], 'industry' => ['Technology', 'Professional services', 'Healthcare'][$c], 'location' => ['New York, NY', 'Austin, TX', 'Remote'][$c], 'contact_name' => ['Sarah Mitchell', 'David Park', 'Rachel Evans'][$c], 'email' => 'hiring@'.strtolower(str_replace(' ', '', $companies[$group * 3 + $c])).'.example', 'phone' => '+1 202 555 01'.($group * 3 + $c + 10), 'website' => 'https://example.com', 'status' => $sales[($group * 3 + $c) % 6], 'requirements' => 'Hiring experienced product and engineering talent. Collaborative teams, flexible working, and strong communication skills.', 'notes' => 'Preferred fee: 18% of first-year salary. Weekly hiring sync on Thursday.']);
            }
            $jobs = [];
            foreach ($titles as $j => $title) {
                $jobs[] = $this->insert('vacancies', ['owner_id' => $ownerId, 'name' => $title, 'client_id' => $clients[$j % 3], 'location' => ['New York · Hybrid', 'Remote', 'Austin · On-site', 'Boston · Hybrid'][$j], 'employment_type' => 'Full-time', 'salary_min' => 85000 + $j * 10000, 'salary_max' => 120000 + $j * 12000, 'status' => $j === 3 && $group === 2 ? 'On hold' : 'Open', 'requirements' => 'At least 3 years of relevant experience. Strong problem solving and stakeholder communication. Portfolio or demonstrable project experience required.', 'notes' => 'Two interview rounds plus a team conversation.']);
            }
            for ($c = 0; $c < 8; $c++, $index++) {
                $name = $names[$index];
                $candidate = $this->insert('candidates', ['owner_id' => $ownerId, 'name' => $name, 'headline' => $titles[$c % 4], 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'phone' => '+1 202 555 '.str_pad((string) ($index + 100), 4, '0', STR_PAD_LEFT), 'location' => ['New York, NY', 'San Francisco, CA', 'Austin, TX', 'Remote'][$c % 4], 'skills' => ['Figma, UX Research, Design Systems', 'PHP, Laravel, JavaScript, MySQL', 'Strategy, Agile, Roadmapping', 'SQL, Python, Tableau'][$c % 4], 'experience_years' => 3 + $c, 'salary_expectation' => 95000 + $c * 5000, 'available_from' => now()->addDays($c * 3)->toDateString(), 'status' => $c % 3 === 0 ? 'Notice period' : 'Available', 'experience' => 'Experienced specialist with a strong track record in cross-functional teams. Previously delivered projects for growth-stage businesses. Bachelor degree; references available on request.', 'notes' => 'Interested in meaningful work, career growth, and a collaborative culture.']);
                $path = "demo/cv-$candidate.txt";
                $contents = "$name\n".$titles[$c % 4]."\n\nDEMO CV — synthetic profile\nExperience: ".(3 + $c)." years\nPortfolio and references available on request.\n";
                Storage::disk('local')->put($path, $contents);
                DB::table('documents')->insert(['entity' => 'candidates', 'record_id' => $candidate, 'user_id' => $ownerId, 'name' => "$name - Demo CV.txt", 'path' => $path, 'mime' => 'text/plain', 'size' => strlen($contents), 'created_at' => now(), 'updated_at' => now()]);
                for ($a = 0; $a < 2; $a++) {
                    $stage = $stages[($c + $a + $group) % 7];
                    $jobIndex = ($c + $a) % 4;
                    $application = $this->insert('applications', ['owner_id' => $ownerId, 'name' => "$name · ".$titles[$jobIndex], 'candidate_id' => $candidate, 'job_id' => $jobs[$jobIndex], 'status' => $stage, 'source' => $a ? 'Referral' : 'LinkedIn', 'notes' => 'Initial suitability checked. Salary and availability confirmed.']);
                    if (in_array($stage, ['Interview', 'Offer'])) {
                        $this->insert('interviews', ['owner_id' => $ownerId, 'name' => 'Team conversation · '.$name, 'application_id' => $application, 'scheduled_at' => now()->addDays(($c % 5) + 1)->setTime(10 + $a, 30)->format('Y-m-d H:i:s'), 'duration_minutes' => 45, 'participants' => "$name, Sarah Mitchell, ".User::find($ownerId)->name, 'meeting_url' => null, 'status' => 'Scheduled', 'feedback' => null]);
                    }
                    if ($stage === 'Hired') {
                        $this->insert('placements', ['owner_id' => $ownerId, 'name' => "$name · ".$titles[$jobIndex], 'application_id' => $application, 'start_date' => now()->subMonths($c % 5)->startOfMonth()->addDays(10)->toDateString(), 'fee' => 15000 + $c * 800, 'invoice_number' => 'TF-2026-'.($index * 2 + $a + 100), 'status' => $a ? 'Invoiced' : 'Paid', 'notes' => 'Signed offer received. Onboarding confirmed with the hiring manager.']);
                    }
                    $this->activity('applications', $application, $ownerId, 'Stage changed', 'Application progressed to '.$stage.'. Candidate and hiring team updated manually.');
                }
                if ($c < 4) {
                    $this->insert('tasks', ['owner_id' => $ownerId, 'name' => ['Follow up on interview feedback', 'Confirm salary expectations', 'Share candidate shortlist', 'Check onboarding progress'][$c], 'candidate_id' => $candidate, 'job_id' => $jobs[$c], 'due_at' => now()->addDays($c - 2)->setTime(16, 0)->format('Y-m-d H:i:s'), 'priority' => $c < 2 ? 'High' : 'Medium', 'status' => 'To do', 'notes' => 'Follow up with the hiring team and log the outcome.']);
                }
                $this->activity('candidates', $candidate, $ownerId, 'Call', 'Discussed career goals, salary expectations, and preferred working arrangements.');
            }
            $contract = "DEMO RECRUITMENT AGREEMENT\nClient: ".$companies[$group * 3]."\nFee: 18% of annual salary. Payment terms: 30 days.\nSynthetic demonstration document only.\n";
            $path = 'demo/contract-'.$clients[0].'.txt';
            Storage::disk('local')->put($path, $contract);
            DB::table('documents')->insert(['entity' => 'clients', 'record_id' => $clients[0], 'user_id' => $ownerId, 'name' => 'Demo recruitment agreement.txt', 'path' => $path, 'mime' => 'text/plain', 'size' => strlen($contract), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($clients as $client) {
                $this->activity('clients', $client, $ownerId, 'Meeting', 'Discovery meeting completed. Confirmed hiring priorities and contract terms.');
            }
        }
        $this->seedContacts();
    }

    private function seedContacts(): void
    {
        foreach (DB::table('clients')->whereIn('owner_id', User::whereIn('email', ['recruiter@talentflow.test', 'maya@talentflow.test', 'finance-recruiter@talentflow.test'])->pluck('id'))->get() as $client) {
            if (! DB::table('client_contacts')->where('client_id', $client->id)->exists()) {
                DB::table('client_contacts')->insert(['client_id' => $client->id, 'name' => 'Jordan Ellis', 'job_title' => 'People Operations Manager', 'email' => 'jordan@'.strtolower(str_replace(' ', '', $client->name)).'.example', 'phone' => '+1 202 555 0189', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId([...$data, 'version' => 1, 'created_at' => now()->subDays(random_int(1, 45)), 'updated_at' => now()]);
    }

    private function activity(string $entity, int $id, int $userId, string $action, string $body): void
    {
        DB::table('activities')->insert(['entity' => $entity, 'record_id' => $id, 'user_id' => $userId, 'action' => $action, 'body' => $body, 'created_at' => now()->subMinutes($id * 15), 'updated_at' => now()]);
    }
}
