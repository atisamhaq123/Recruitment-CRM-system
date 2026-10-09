/* Run with Playwright available through NODE_PATH. See README for browser checks. */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

(async () => {
    const baseURL = process.env.QA_URL || 'http://127.0.0.1:8000';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || (process.platform === 'win32' ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe' : undefined) });
    const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1050 }, acceptDownloads: true });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const created = [];
    const output = path.resolve('storage/app/qa');
    fs.mkdirSync(output, { recursive: true });
    const suffix = Date.now();
    const prefix = `Browser QA ${suffix}`;

    async function login(email) {
        await page.goto('/login');
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('TalentFlow!2026');
        await Promise.all([page.waitForURL(baseURL + '/'), page.getByRole('button', { name: 'Sign in to TalentFlow' }).click()]);
    }
    async function logout() {
        if (!await page.getByRole('button', { name: 'Sign out' }).count()) { await page.goto('/'); }
        if (await page.locator('[data-toggle-nav]').isVisible() && !await page.locator('body').evaluate(body => body.classList.contains('sidebar-open'))) { await page.locator('[data-toggle-nav]').click(); }
        await page.getByRole('button', { name: 'Sign out' }).click();
        await page.waitForURL('**/login');
    }
    async function create(entity, fields) {
        await page.goto(`/workspace/${entity}/create`);
        for (const [key, value] of Object.entries(fields)) {
            const field = page.locator(`#${key}`);
            if (await field.evaluate(element => element.tagName === 'SELECT')) { await field.selectOption(String(value)); }
            else { await field.fill(String(value)); }
        }
        await Promise.all([page.waitForURL(new RegExp(`/workspace/${entity}/\\d+$`)), page.locator('.record-form button[type="submit"]').click()]);
        assert.equal(await page.locator('.flash.error').count(), 0, `Creating ${entity} failed`);
        const id = Number(page.url().split('/').pop());
        created.push([entity, id]);
        return id;
    }
    async function boardStage(entity, id, stage) {
        await page.goto(`/workspace/${entity}?view=board&q=${encodeURIComponent(prefix)}`);
        const card = page.locator(`.pipeline-card[data-id="${id}"]`);
        const promise = page.waitForResponse(response => response.url().endsWith(`/${id}/stage`) && response.request().method() === 'PATCH');
        await card.locator('[data-stage-select]').selectOption(stage);
        assert.equal((await promise).status(), 200);
        await page.waitForFunction(({ id, stage }) => document.querySelector(`.pipeline-card[data-id="${id}"]`)?.closest('.board-column').dataset.stage === stage, { id, stage });
        assert.equal(await page.evaluate(() => document.activeElement.id), 'stage-' + id, 'Stage selector should retain keyboard focus');
        await page.reload();
        assert.equal(await page.locator(`.pipeline-card[data-id="${id}"]`).locator('[data-stage-select]').inputValue(), stage);
    }

    try {
        await login('admin@talentflow.test');
        await page.screenshot({ path: path.join(output, 'dashboard-desktop.png'), fullPage: true, animations: 'disabled' });
        for (const entity of ['clients','candidates','jobs','applications','interviews','tasks','placements']) {
            const response = await page.goto(`/workspace/${entity}`);
            assert.equal(response.status(), 200, entity);
            assert.equal(await page.locator('.flash.error').count(), 0);
        }
        const client = await create('clients', { name: prefix + ' Client', industry: 'Technology', location: 'Remote', contact_name: 'Taylor Lane', email: 'qa-client@example.com', status: 'New lead', owner_id: 3, requirements: 'Recruit two developers.' });
        await page.locator('.contact-add summary').click();
        await page.locator('#new-contact-name').fill('QA Hiring Partner');
        await page.locator('#new-contact-title').fill('Hiring Manager');
        await page.locator('#new-contact-email').fill('qa-partner@example.com');
        await page.getByRole('button', { name: 'Add contact', exact: true }).click();
        await page.waitForLoadState();
        const contactCard = page.locator('.contact-card').filter({ hasText: 'QA Hiring Partner' });
        await contactCard.locator('summary').click();
        await contactCard.locator('[name="job_title"]').fill('Director of Talent');
        await contactCard.getByRole('button', { name: 'Save contact' }).click();
        await page.waitForLoadState();
        assert.ok(await page.getByText('Director of Talent', { exact: false }).count());
        await page.goto(`/workspace/clients?view=board&q=${encodeURIComponent(prefix)}`);
        const card = page.locator(`.pipeline-card[data-id="${client}"]`);
        const moved = page.waitForResponse(response => response.url().endsWith(`/${client}/stage`) && response.request().method() === 'PATCH');
        await card.dragTo(page.locator('.board-column[data-stage="Proposal"]'));
        assert.equal((await moved).status(), 200);
        await page.reload();
        assert.equal(await page.locator(`#stage-${client}`).inputValue(), 'Proposal');
        const candidate = await create('candidates', { name: prefix + ' Candidate', headline: 'Laravel Engineer', email: 'qa-candidate@example.com', location: 'Remote', skills: 'PHP, Laravel, MySQL', experience_years: 5, status: 'Available', owner_id: 3, salary_expectation: 95000 });
        await page.locator('#document').setInputFiles({ name: 'browser-cv.txt', mimeType: 'text/plain', buffer: Buffer.from('Synthetic browser QA candidate CV. PHP and Laravel experience.') });
        await page.getByRole('button', { name: 'Upload document' }).click();
        await page.waitForLoadState();
        const downloadEvent = page.waitForEvent('download');
        await page.getByRole('link', { name: /browser-cv.txt/ }).click();
        const download = await downloadEvent;
        assert.equal(download.suggestedFilename(), 'browser-cv.txt');
        const documentUrl = await page.getByRole('link', { name: /browser-cv.txt/ }).getAttribute('href');
        await page.locator('#activity-body').fill('Browser QA conversation logged.');
        await page.getByRole('button', { name: 'Log activity' }).click();
        await page.waitForLoadState();
        assert.ok(await page.getByText('Browser QA conversation logged.').count());
        const jobFields = { client_id: client, location: 'Remote', employment_type: 'Full-time', salary_min: 80000, salary_max: 110000, status: 'Open', owner_id: 3, requirements: 'Laravel and MySQL experience.' };
        const job1 = await create('jobs', { ...jobFields, name: prefix + ' Engineer' });
        const job2 = await create('jobs', { ...jobFields, name: prefix + ' Lead' });
        const app1 = await create('applications', { candidate_id: candidate, job_id: job1, status: 'Sourced', source: 'Direct', owner_id: 3 });
        const app2 = await create('applications', { candidate_id: candidate, job_id: job2, status: 'Offer', source: 'Referral', owner_id: 3 });
        await boardStage('applications', app1, 'Interview');
        assert.equal(await page.locator(`#stage-${app2}`).inputValue(), 'Offer');
        await page.screenshot({ path: path.join(output, 'applications-board.png'), fullPage: true, animations: 'disabled' });
        const scheduled = new Date(Date.now() + 86400000).toISOString().slice(0,16);
        const interview = await create('interviews', { name: prefix + ' Technical interview', application_id: app1, scheduled_at: scheduled, duration_minutes: 45, participants: 'Taylor Lane, Candidate, Jamie Chen', status: 'Scheduled', owner_id: 3, meeting_url: 'https://meet.example.com/qa' });
        await page.goto(`/workspace/interviews/${interview}/edit`);
        await page.locator('#status').selectOption('Passed');
        await page.locator('#feedback').fill('Strong Laravel and database skills.');
        await page.getByRole('button', { name: 'Save changes' }).click();
        await page.waitForLoadState();
        assert.ok(await page.getByText('Strong Laravel and database skills.').count());
        await boardStage('applications', app1, 'Hired');
        await create('placements', { application_id: app1, start_date: new Date(Date.now()+604800000).toISOString().slice(0,10), fee: 1234.56, status: 'Paid', invoice_number: 'QA-'+suffix, owner_id: 3 });
        await create('tasks', { name: prefix + ' Follow up', client_id: client, candidate_id: candidate, job_id: job1, due_at: scheduled, priority: 'High', status: 'To do', owner_id: 3 });
        await logout();
        await login('recruiter@talentflow.test');
        assert.equal((await page.goto(`/workspace/candidates/${candidate}`)).status(), 200);
        assert.equal((await page.goto('/workspace/candidates/24')).status(), 404);
        assert.equal((await page.goto('/admin')).status(), 403);
        await page.goto('/');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({ path: path.join(output, 'dashboard-mobile.png'), fullPage: true, animations: 'disabled' });
        const overflowing = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > window.innerWidth + 1 && getComputedStyle(el).position !== 'fixed').map(el => ({ tag: el.tagName, class: el.className, width: el.getBoundingClientRect().width, right: el.getBoundingClientRect().right })).slice(0,15));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Mobile dashboard must not overflow: ' + JSON.stringify(overflowing));
        await page.getByRole('button', { name: 'Open navigation' }).click();
        assert.equal(await page.locator('[data-toggle-nav]').getAttribute('aria-expanded'), 'true');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-toggle-nav]').getAttribute('aria-expanded'), 'false');
        await logout();
        await login('finance-recruiter@talentflow.test');
        assert.equal((await page.goto(documentUrl)).status(), 404);
        assert.equal((await page.goto(`/workspace/applications/${app1}`)).status(), 404);
        await logout();
        await login('manager@talentflow.test');
        assert.equal((await page.goto(`/workspace/candidates/${candidate}`)).status(), 200);
        assert.equal((await page.goto('/workspace/candidates/24')).status(), 404);
        assert.equal((await page.goto('/reports')).status(), 200);
        assert.equal(errors.length, 0, errors.join('\n'));
        console.log('PASS: Chrome workflows, drag/drop persistence, independent applications, interviews, private document download, placements, three-role permissions, and mobile layout.');
    } finally {
        await page.setViewportSize({ width: 1440, height: 1050 });
        try {
            await page.goto('/');
            if (await page.getByRole('button', { name: 'Sign out' }).count()) { await logout(); }
            await login('admin@talentflow.test');
            for (const [entity, id] of created.reverse()) {
                await page.goto(`/workspace/${entity}/${id}`);
                const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
                const response = await context.request.delete(`/workspace/${entity}/${id}`, { headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, maxRedirects: 0 });
                if (response.status() !== 302) console.error(`Cleanup failed for ${entity} ${id}: ${response.status()}`);
            }
        } catch (error) { console.error('Cleanup:', error.message); }
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
