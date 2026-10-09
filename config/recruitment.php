<?php

$text = fn (string $label, bool $required = false) => ['label' => $label, 'type' => 'text', 'rules' => ($required ? 'required' : 'nullable').'|string|max:255'];
$area = fn (string $label) => ['label' => $label, 'type' => 'textarea', 'rules' => 'nullable|string|max:10000'];
$select = fn (string $label, array $options) => ['label' => $label, 'type' => 'select', 'options' => $options, 'rules' => 'required|string|in:'.implode(',', $options)];
$reference = fn (string $label, string $entity, bool $required = true) => ['label' => $label, 'type' => 'reference', 'entity' => $entity, 'rules' => ($required ? 'required' : 'nullable').'|integer'];
$money = fn (string $label, bool $required = false) => ['label' => $label, 'type' => 'number', 'rules' => ($required ? 'required' : 'nullable').'|numeric|min:0|max:99999999'];
$date = fn (string $label, bool $required = false) => ['label' => $label, 'type' => 'date', 'rules' => ($required ? 'required' : 'nullable').'|date_format:Y-m-d'];
$email = ['label' => 'Email address', 'type' => 'email', 'rules' => 'required|email|max:255'];
$sales = ['New lead', 'Contacted', 'Meeting', 'Proposal', 'Contract signed', 'Lost'];
$hiring = ['Sourced', 'Screening', 'Shortlisted', 'Interview', 'Offer', 'Hired', 'Rejected'];

return ['entities' => [
    'clients' => ['table' => 'clients', 'label' => 'Clients', 'singular' => 'client', 'description' => 'Build relationships. Turn opportunities into partnerships.', 'stages' => $sales, 'fields' => [
        'name' => $text('Company name', true), 'industry' => $text('Industry', true), 'location' => $text('Location', true), 'contact_name' => $text('Primary hiring contact', true), 'email' => $email, 'phone' => $text('Phone'),
        'website' => ['label' => 'Website', 'type' => 'url', 'rules' => 'nullable|url:http,https|max:255'], 'status' => $select('Sales stage', $sales), 'requirements' => $area('Hiring requirements'), 'notes' => $area('Relationship notes'),
    ]],
    'candidates' => ['table' => 'candidates', 'label' => 'Candidates', 'singular' => 'candidate', 'description' => 'Great people, ready for their next chapter.', 'fields' => [
        'name' => $text('Full name', true), 'headline' => $text('Professional headline', true), 'email' => $email, 'phone' => $text('Phone'), 'location' => $text('Location', true), 'skills' => $text('Skills, separated by commas', true),
        'experience_years' => ['label' => 'Years of experience', 'type' => 'number', 'rules' => 'required|integer|min:0|max:60'], 'salary_expectation' => $money('Expected annual salary (USD)'), 'available_from' => $date('Available from'),
        'status' => $select('Availability', ['Available', 'Notice period', 'Unavailable']), 'experience' => $area('Experience and education'), 'notes' => $area('Recruiter notes'),
    ]],
    'jobs' => ['table' => 'vacancies', 'label' => 'Job openings', 'singular' => 'job', 'description' => 'Connect the right opportunity with the right talent.', 'fields' => [
        'name' => $text('Job title', true), 'client_id' => $reference('Client company', 'clients'), 'location' => $text('Location', true), 'employment_type' => $select('Employment type', ['Full-time', 'Part-time', 'Contract', 'Temporary']),
        'salary_min' => $money('Minimum annual salary (USD)', true), 'salary_max' => $money('Maximum annual salary (USD)', true), 'status' => $select('Job status', ['Open', 'On hold', 'Closed']), 'requirements' => $area('Role requirements'), 'notes' => $area('Internal notes'),
    ]],
    'applications' => ['table' => 'applications', 'label' => 'Applications', 'singular' => 'application', 'description' => 'Every candidate. Every opportunity. One clear pipeline.', 'stages' => $hiring, 'fields' => [
        'candidate_id' => $reference('Candidate', 'candidates'), 'job_id' => $reference('Job opening', 'jobs'), 'status' => $select('Application stage', $hiring), 'source' => $select('Source', ['Direct', 'Referral', 'LinkedIn', 'Job board', 'Talent pool']), 'notes' => $area('Application notes'),
    ]],
    'interviews' => ['table' => 'interviews', 'label' => 'Interviews', 'singular' => 'interview', 'description' => 'Make every conversation count.', 'fields' => [
        'name' => $text('Interview title', true), 'application_id' => $reference('Application', 'applications'), 'scheduled_at' => ['label' => 'Start time (Asia/Karachi)', 'type' => 'datetime-local', 'rules' => 'required|date'],
        'duration_minutes' => ['label' => 'Duration in minutes', 'type' => 'number', 'rules' => 'required|integer|min:15|max:480'], 'participants' => $text('Participants', true), 'meeting_url' => ['label' => 'Meeting link', 'type' => 'url', 'rules' => 'nullable|url:http,https|max:255'],
        'status' => $select('Outcome', ['Scheduled', 'Completed', 'Passed', 'Declined', 'Cancelled']), 'feedback' => $area('Interview feedback'),
    ]],
    'tasks' => ['table' => 'tasks', 'label' => 'Tasks & reminders', 'singular' => 'task', 'description' => 'Stay one step ahead of every follow-up.', 'fields' => [
        'name' => $text('Task title', true), 'client_id' => $reference('Related client', 'clients', false), 'candidate_id' => $reference('Related candidate', 'candidates', false), 'job_id' => $reference('Related job', 'jobs', false), 'due_at' => ['label' => 'Due time (Asia/Karachi)', 'type' => 'datetime-local', 'rules' => 'required|date'],
        'priority' => $select('Priority', ['Low', 'Medium', 'High']), 'status' => $select('Status', ['To do', 'In progress', 'Done']), 'notes' => $area('Details'),
    ]],
    'placements' => ['table' => 'placements', 'label' => 'Placements', 'singular' => 'placement', 'description' => 'Celebrate the match. Track the business.', 'fields' => [
        'application_id' => $reference('Hired application', 'applications'), 'start_date' => $date('Employment start date', true), 'fee' => $money('Agreed recruitment fee (USD)', true), 'invoice_number' => $text('Invoice reference'),
        'status' => $select('Invoice / payment status', ['Pending', 'Invoiced', 'Paid']), 'notes' => $area('Placement and payment notes'),
    ]],
]];
