# TalentFlow — Recruitment CRM & ATS

A separate Laravel application with original TalentFlow branding, responsive Blade views, plain JavaScript, CSS, and MySQL persistence. No framework is required on the frontend, and no Node build step is needed. Other projects, including any English-learning LMS, are untouched.

## Requirements

- PHP 8.3+ (developed with WAMP PHP 8.4.15)
- Composer 2
- MySQL 8+ with InnoDB
- PHP extensions: PDO MySQL, mbstring, openssl, fileinfo, zip, XML, ctype, tokenizer; PDO SQLite for automated tests

## Install

From the `talentflow` directory:

```sh
composer install
cp .env.example .env
php artisan key:generate
```

On PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

Create the dedicated database using `database/setup.sql`, or execute:

```sql
CREATE DATABASE talentflow_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure `.env` for your MySQL server:

```dotenv
APP_NAME=TalentFlow
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=talentflow_crm
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_ENCRYPT=true
```

The blank root password is a local WAMP example. Use your actual database credentials. The connection explicitly specifies InnoDB, even when the server defaults to MyISAM.

```sh
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000**. On this Windows workspace, `powershell -ExecutionPolicy Bypass -File .\start.ps1` also starts the server. It detects PHP on PATH or the installed WAMP PHP binary.

If PHP and Composer are not on PATH, the installed equivalents here are:

```powershell
& 'C:\wamp64\bin\php\php8.4.15\php.exe' 'C:\Users\CCS\AppData\Roaming\Composer\latest.phar' install
& 'C:\wamp64\bin\php\php8.4.15\php.exe' artisan migrate --seed
& 'C:\wamp64\bin\php\php8.4.15\php.exe' artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

## Demo accounts

All demo accounts use **`TalentFlow!2026`**.

| Role | Email | Access |
| --- | --- | --- |
| Admin | admin@talentflow.test | Entire agency; users, teams, and settings |
| Manager | manager@talentflow.test | Technology & Product team |
| Recruiter | recruiter@talentflow.test | Jamie Chen's assignments |
| Recruiter | maya@talentflow.test | Maya Patel's assignments |
| Manager | finance-manager@talentflow.test | Finance & Operations team |
| Recruiter | finance-recruiter@talentflow.test | Leo Brooks's assignments |

The seed includes 6 users, 2 teams, 9 clients with additional hiring contacts, 24 candidates, 12 jobs, 48 independent applications, scheduled interviews, overdue and upcoming tasks, placements, communication history, and private demonstration CVs/contracts. Dates are relative to installation. All people, documents, companies, and emails are synthetic. Repeat seeding preserves existing demo accounts/records and ensures demo clients have an additional hiring contact.

## Working workflows

1. Add a **client** with primary hiring contact, requirements, and assigned recruiter. Manage additional hiring contacts, upload contracts, and log calls/meetings from its details.
2. Use the client **Board** for: New lead → Contacted → Meeting → Proposal → Contract signed / Lost.
3. Add a **candidate** with skills, experience, salary, availability, and owner. Upload a PDF, DOCX, or TXT CV from the candidate detail page.
4. Add an open **job** linked to an accessible client. Set requirements, employment type, location, salary range, and recruiter.
5. Add an **application** joining a candidate and an open job. A candidate can have several applications; a candidate/job pair is unique.
6. Use the application **Board** for: Sourced → Screening → Shortlisted → Interview → Offer → Hired / Rejected. Drag cards or use each card's selector on mobile/keyboard. Changes persist, validate, and create history. Stale changes return a conflict instead of overwriting newer edits. Earlier stages can be revisited; placed applications must remain Hired.
7. Schedule an **interview** against an application, including participants, start time, duration, and an optional real meeting URL. Edit it afterward to capture feedback/outcome.
8. Add a **task** with due time, priority, optional client/candidate/job references, and assignee. The dashboard shows overdue tasks and upcoming interviews. Complete tasks by changing status to Done.
9. Move the application to **Hired**, then create a **placement** with start date and fee. Advance payment status from Pending to Invoiced/Paid; an invoice reference is required for Invoiced/Paid.
10. Filter/search any module, export CSV, browse linked records, and search communication timelines. Reports summarize accessible recruiting activity and agreed/collected fees for a date range.

## Permissions and security

- Every record query checks ownership. Admins access all records; managers access active manager/recruiter assignments in their team; recruiters access their own assignments. Linked records also require access to each underlying parent, recursively.
- Only admins manage users, teams, settings, and permanent deletion. Deletion is blocked when foreign-key references exist. Disabling an account revokes its access on the next request.
- Managers assign records only within their team. An assignee must have access to all linked records. Reassign clients, candidates, jobs, then applications and dependent records as appropriate; parent reassignment can immediately hide descendants from their former owners. Team changes also affect access immediately.
- Laravel authentication uses hashed passwords, rotated sessions, encrypted database sessions, HTTP-only cookies, CSRF protection on mutations, and login throttling. Blade escapes output; database operations use bound parameters and whitelisted field definitions.
- Document uploads are restricted to PDF, DOCX, TXT, 10 MB maximum. DOCX files must contain a Word document and have no VBA project. Documents use randomized private paths in `storage/app/private`. They are not publicly linked, and Laravel's temporary local-file serving is disabled. The download controller checks current parent-record access every time, forces an attachment, and logs access.
- CSV exports escape spreadsheet formula prefixes. Imports support clients/candidates/jobs, validate all rows, restrict fields and assignments, and roll back the whole import if any row is invalid.
- Server headers prevent MIME sniffing and frame embedding. Authenticated responses use `no-store`.

## CSV

Export a module to obtain the exact header format. Imports add records rather than updating existing ones. Up to 500 rows / 2 MB; reference fields such as `client_id` use database IDs. Missing `owner_id` defaults to the current user; recruiters cannot import records under someone else's ownership. Required fields and exact status labels must be supplied. Salary and recruitment fees are USD.

Example client import:

```csv
name,industry,location,contact_name,email,status
Meridian Design,Design,Remote,Taylor Lane,taylor@example.com,New lead
```

## Integrations and boundaries

Email delivery, SMS, job-board publishing, and automatic CV parsing are **not connected**. There are no simulated success buttons. Email timeline entries are manual logs and never send messages. A provider adapter, credentials, and integration tests must be implemented before any of these can be enabled. Meeting links are entered manually; this application does not create meetings with external providers.

Invoices are tracking records, not generated tax documents or payment processing. Fees use one currency (USD). Times use Asia/Karachi. Each record has one assigned recruiter. Hiring contacts are managed on company profiles, with communication history in their timeline. Reminders appear inside the workspace; no background email/SMS reminders are sent.

## Verification

```sh
php artisan config:clear
php artisan test --compact
php vendor/laravel/pint/builds/pint --format agent
php artisan view:cache
php artisan route:list --except-vendor
```

Feature tests use an isolated in-memory SQLite database and fake private storage. A bootstrap guard refuses to run them against any other database. They cover role boundaries, linked-record ownership, credential hashing, login throttling, disabled sessions, actual CSRF rejection, clients/candidates/hiring contacts, salary validation, multi-job applications, duplicate prevention, persistent stage changes/history, stale edits, recruiter assignment, interview feedback, unsafe meeting URLs, private uploads/downloads, hired-only placements, invoice/payment validation, tasks, escaped notes, atomic CSV import, CSV formula protection, admin settings, and rendering modules for every role. Development also uses the dedicated MySQL database for live workflows.

An optional Chrome workflow test is included in `tests/browser.cjs`. With the local server running and Playwright available, run `node tests/browser.cjs` (set `NODE_PATH` if installed in a separate tools directory). On Windows it uses installed Google Chrome; override `CHROME_PATH` or `QA_URL` if needed. It creates and cleans up its own records, exercises actual drag/drop and form workflows against MySQL, and writes desktop/mobile screenshots to `storage/app/qa`. In this workspace, Playwright is available in `../.browser-tools/node_modules`:

```powershell
$env:NODE_PATH = Join-Path (Split-Path (Get-Location)) '.browser-tools\node_modules'
node tests\browser.cjs
```

## Deployment

Point the web server document root to **`talentflow/public`**, never the project root. Make `storage` and `bootstrap/cache` writable. Use a dedicated database user and HTTPS, then set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to your HTTPS URL, and `SESSION_SECURE_COOKIE=true`. Use a unique APP_KEY. Replace demo passwords or install without the demo seed for real use. Protect backups containing candidate data and private documents. Run migrations and Laravel configuration/view caches during deployment.

For a disposable demo reset only, `php artisan migrate:fresh --seed` deletes **all tables in the configured database**. Confirm that `.env` points to this project's dedicated disposable database before using it.

## Code map

- `config/recruitment.php`: module fields and pipeline stages
- `database/migrations/`: relational schema, unique pairs, ownership and foreign keys
- `app/Services/Recruitment.php`: reusable access scopes, validation, concurrency, and history
- `app/Http/Controllers/`: authentication, CRM workflows, documents, CSV, reports, admin
- `resources/views/`: responsive Blade pages
- `public/css/app.css`, `public/js/app.js`: interface and accessible pipeline controls
- `tests/Feature/RecruitmentTest.php`: workflow and security tests

The application is built on Laravel 13; setup conventions follow the [official Laravel installation documentation](https://laravel.com/docs/13.x/installation).
