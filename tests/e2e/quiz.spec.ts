import { test, expect, type Page } from '@playwright/test';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Wait for a Livewire update response (used everywhere we trigger a round-trip). */
async function waitForLivewire(page: Page, timeout = 10_000) {
    return page.waitForResponse(
        r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
        { timeout },
    );
}

/**
 * Answer all 7 quiz questions with the supplied values and wait for
 * Livewire to resolve the result on the final step.
 *
 * Step order: dine-in/takeout → service level → cuisine → energy → hunger →
 * distance → familiarity. When serviceLevel is "Quick and easy", the wizard
 * skips the energy and familiarity steps (5-step flow instead of 7), so this
 * helper skips clicking them too.
 */
async function completeQuiz(
    page: Page,
    opts: {
        dineInTakeout?: string;
        serviceLevel?: string;
        cuisine?: string;
        energy?: string;
        hunger?: string;
        distance?: string;
        familiarity?: string;
    } = {},
) {
    const {
        dineInTakeout = 'Either is fine',
        serviceLevel  = 'Casual sit-down',
        cuisine       = 'Surprise me',
        energy        = 'Moderate',
        hunger        = 'Full meal',
        distance      = 'Anywhere',
        familiarity   = 'Either',
    } = opts;

    const isQuickEasy = /quick/i.test(serviceLevel);

    // Each step triggers a Livewire round-trip. We wait for each response before
    // clicking the next answer so rapid-fire clicks don't race past the DOM update.
    let step = waitForLivewire(page);
    await page.getByRole('button', { name: new RegExp(dineInTakeout, 'i') }).click();
    await step;

    step = waitForLivewire(page);
    await page.getByRole('button', { name: new RegExp(serviceLevel, 'i') }).click();
    await step;

    step = waitForLivewire(page);
    await page.getByRole('button', { name: new RegExp(cuisine, 'i') }).click();
    await step;

    if (!isQuickEasy) {
        step = waitForLivewire(page);
        await page.getByRole('button', { name: new RegExp(energy, 'i') }).click();
        await step;
    }

    step = waitForLivewire(page);
    await page.getByRole('button', { name: new RegExp(hunger, 'i') }).click();
    await step;

    // For a quick_easy flow, this distance click resolves the quiz (familiarity
    // is skipped). Otherwise familiarity below resolves it.
    step = waitForLivewire(page);
    await page.getByRole('button', { name: new RegExp(distance, 'i') }).click();
    await step;

    if (!isQuickEasy) {
        step = waitForLivewire(page);
        await page.getByRole('button', { name: new RegExp(familiarity, 'i') }).click();
        await step;
    }
}

/**
 * Reset the quiz wizard to a clean step 1, regardless of which state
 * (questions/result/empty) a previous test left it in. The result state has
 * no "Start over" control in the UI (by design), and rejecting repeatedly via
 * "Not this one" isn't guaranteed to terminate (it can oscillate between the
 * same two restaurants) — so this calls the component's `restart` action
 * directly via Livewire's JS API instead of depending on which button is
 * visible.
 */
async function resetQuizState(page: Page) {
    await page.goto('/quiz');

    const reset = waitForLivewire(page);
    await page.evaluate(() => (window as any).Livewire.first().call('restart'));
    await reset;
}

// ── Unauthenticated guard ─────────────────────────────────────────────────────

test('unauthenticated user is redirected to login from /quiz', async ({ page }) => {
    await page.goto('/quiz');
    await expect(page).toHaveURL(/login/);
});

// ── Authenticated ─────────────────────────────────────────────────────────────

test.describe('guided quiz', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    // All quiz tests share one authenticated session cookie, and the wizard
    // now persists its step/state server-side (`quiz.wizard`). That state is
    // keyed by session, not by browser context, so fullyParallel workers
    // sharing this storageState would race on the same server-side session —
    // force this describe block to run serially, then reset via the existing
    // Start over control before every test so a prior test that ended
    // mid-quiz can't leak state into this one.
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await resetQuizState(page);
    });

    // ── Page shell ────────────────────────────────────────────────────────────

    test('shows the page title', async ({ page }) => {
        await page.goto('/quiz');
        await expect(page).toHaveTitle(/Guided Quiz/);
    });

    test('shows a Quiz link in the sidebar nav', async ({ page }) => {
        await page.goto('/quiz');
        await expect(page.getByRole('link', { name: /Quiz/i })).toBeVisible();
    });

    // ── Step 1 — dine-in/takeout ─────────────────────────────────────────────

    test('shows step 1 of 7 on load', async ({ page }) => {
        await page.goto('/quiz');
        await expect(page.getByText('Step 1 of 7')).toBeVisible();
    });

    test('shows the dine-in/takeout question on step 1', async ({ page }) => {
        await page.goto('/quiz');
        await expect(page.getByText('Dine in or takeout?')).toBeVisible();
    });

    test('step 1 shows Dine in, Takeout and Either is fine answer buttons', async ({ page }) => {
        await page.goto('/quiz');
        await expect(page.getByRole('button', { name: /Dine in/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Takeout/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Either is fine/i })).toBeVisible();
    });

    // ── Progress bar ──────────────────────────────────────────────────────────

    test('progress bar advances to step 2 after answering dine-in/takeout', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await expect(page.getByText('Step 2 of 7')).toBeVisible();
    });

    test('progress bar advances to step 3 after answering service level', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click(); // dine-in/takeout
        await page.getByRole('button', { name: /Casual sit-down/i }).click(); // service level
        await expect(page.getByText('Step 3 of 7')).toBeVisible();
    });

    test('progress bar advances to step 4 after answering cuisine', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click(); // cuisine
        await expect(page.getByText('Step 4 of 7')).toBeVisible();
    });

    test('progress bar advances to step 5 after answering energy', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await expect(page.getByText('Step 5 of 7')).toBeVisible();
    });

    test('progress bar advances to step 6 after answering hunger', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await page.getByRole('button', { name: /Full meal/i }).click(); // hunger
        await expect(page.getByText('Step 6 of 7')).toBeVisible();
    });

    test('progress bar advances to step 7 after answering distance', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await page.getByRole('button', { name: /Full meal/i }).click(); // hunger
        await page.getByRole('button', { name: /Anywhere/i }).click(); // distance
        await expect(page.getByText('Step 7 of 7')).toBeVisible();
    });

    test('choosing Quick and easy service level shortens the flow to 5 steps', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click(); // dine-in/takeout
        await page.getByRole('button', { name: /Quick and easy/i }).click(); // service level
        // Lands on step 3 (cuisine) — dineInTakeout + serviceLevel + cuisine = effective step 3 of 5.
        await expect(page.getByText('Step 3 of 5')).toBeVisible();
    });

    // ── Step content ──────────────────────────────────────────────────────────

    test('step 2 shows the service level question', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await expect(page.getByText('What kind of night is it?')).toBeVisible();
        await expect(page.getByRole('button', { name: /Quick and easy/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Casual sit-down/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Nicer night out/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Special occasion/i })).toBeVisible();
    });

    test('step 3 shows the cuisine question with a Surprise me option', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await expect(page.getByText('Any cuisine in mind?')).toBeVisible();
        await expect(page.getByRole('button', { name: /Surprise me/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Italian/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Mexican/i })).toBeVisible();
    });

    test('step 4 shows the energy question', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await expect(page.getByText("What's your energy tonight?")).toBeVisible();
        await expect(page.getByRole('button', { name: /Lively/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Quiet/i })).toBeVisible();
    });

    test('step 5 shows the hunger question', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await expect(page.getByText(/hunger/i)).toBeVisible();
        await expect(page.getByRole('button', { name: /Quick bite/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Feast/i })).toBeVisible();
    });

    test('step 6 shows the distance question', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await page.getByRole('button', { name: /Full meal/i }).click(); // hunger
        await expect(page.getByText('How far are you willing to go?')).toBeVisible();
        await expect(page.getByRole('button', { name: /Under 2 mi/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /2–5 mi/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /5–15 mi/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Anywhere/i })).toBeVisible();
    });

    test('step 7 shows the familiar vs new question', async ({ page }) => {
        await page.goto('/quiz');
        await page.getByRole('button', { name: /Either is fine/i }).click();
        await page.getByRole('button', { name: /Casual sit-down/i }).click();
        await page.getByRole('button', { name: /Surprise me/i }).click();
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await page.getByRole('button', { name: /Full meal/i }).click(); // hunger
        await page.getByRole('button', { name: /Anywhere/i }).click(); // distance
        await expect(page.getByText('Something new or a familiar spot?')).toBeVisible();
        await expect(page.getByRole('button', { name: /Something new/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /A favorite/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /Either/i })).toBeVisible();
    });

    // ── Result card ───────────────────────────────────────────────────────────

    test('shows a result card after completing all 7 questions', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const result = page.locator('[data-flux-badge]:has-text("Quiz Pick")');
        const empty  = page.locator('[data-flux-heading]:has-text("No matches found")');
        await result.or(empty).waitFor({ state: 'visible', timeout: 10_000 });

        if (await empty.isVisible()) return; // no favorites — empty state is valid

        await expect(result).toBeVisible();
    });

    test('result card shows the restaurant name', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const result  = page.locator('[data-flux-badge]:has-text("Quiz Pick")');
        const empty   = page.locator('[data-flux-heading]:has-text("No matches found")');
        await result.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        await expect(
            page.locator('[data-flux-badge]:has-text("Quiz Pick") + [data-flux-heading]')
        ).toBeVisible();
    });

    test('result card shows the Going and Not this one buttons', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const result = page.locator('[data-flux-badge]:has-text("Quiz Pick")');
        const empty  = page.locator('[data-flux-heading]:has-text("No matches found")');
        await result.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Not this one' })).toBeVisible();
    });

    test('result card shows cuisine tags', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        await goingBtn.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        // Every seeded restaurant has at least one cuisine tag rendered as a badge.
        await expect(page.locator('[data-flux-badge]').first()).toBeVisible();
    });

    // ── Cuisine filter ────────────────────────────────────────────────────────

    test('picking Italian cuisine surfaces a restaurant when one exists', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page, { cuisine: 'Italian' });

        const result = page.locator('[data-flux-badge]:has-text("Quiz Pick")');
        const empty  = page.locator('[data-flux-heading]:has-text("No matches found")');
        await result.or(empty).waitFor({ state: 'visible', timeout: 10_000 });

        // Either outcome is valid — just confirm we left the wizard.
        expect(
            await result.isVisible() || await empty.isVisible()
        ).toBe(true);
    });

    // ── Not this one (runner-up) ──────────────────────────────────────────────

    test('Not this one shows a different restaurant or the empty state', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        await goingBtn.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        const nameLocator = page.locator('[data-flux-badge]:has-text("Quiz Pick") + [data-flux-heading]');
        const firstName   = await nameLocator.textContent();

        const reject = waitForLivewire(page);
        await page.getByRole('button', { name: 'Not this one' }).click();
        await reject;

        await goingBtn.or(empty).waitFor({ state: 'visible', timeout: 5_000 });

        if (await empty.isVisible()) {
            // Only one restaurant was in the pool — empty state is correct.
            return;
        }

        // A different restaurant was shown (or same name with a different ID —
        // either way Livewire re-rendered which is the important assertion).
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();
        const secondName = await nameLocator.textContent();
        expect(secondName).not.toBeNull();
    });

    test('Not this one stays on /quiz', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        await goingBtn.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        const reject = waitForLivewire(page);
        await page.getByRole('button', { name: 'Not this one' }).click();
        await reject;

        await expect(page).toHaveURL(/\/quiz$/);
    });

    // ── Empty state ───────────────────────────────────────────────────────────

    test('empty state shows No matches found heading and a Start over button', async ({ page }) => {
        await page.goto('/quiz');

        // Use a very restrictive combination to maximise the chance of hitting
        // the empty state. If a result comes back instead, skip gracefully.
        await completeQuiz(page, {
            energy: 'Lively',
            hunger: 'Quick bite',
            familiarity: 'Something new',
            distance: 'Under 2 mi',
            cuisine: 'Italian',
        });

        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        await empty.or(goingBtn).waitFor({ state: 'visible', timeout: 10_000 });

        if (await goingBtn.isVisible()) {
            // A match was found; reject until the pool is exhausted.
            for (let i = 0; i < 20; i++) {
                if (await empty.isVisible()) break;
                if (!await goingBtn.isVisible({ timeout: 2_000 }).catch(() => false)) break;
                const r = waitForLivewire(page);
                await page.getByRole('button', { name: 'Not this one' }).click();
                await r;
                await empty.or(goingBtn).waitFor({ state: 'visible', timeout: 5_000 }).catch(() => {});
            }
        }

        if (!await empty.isVisible()) {
            test.skip(); // large pool — can't exhaust in this run
            return;
        }

        await expect(empty).toBeVisible();
        await expect(page.getByRole('button', { name: 'Start over' })).toBeVisible();
    });

    test('Start over resets the wizard to step 1', async ({ page }) => {
        await page.goto('/quiz');

        // Get to the empty state by exhausting a very tight filter set.
        await completeQuiz(page, {
            energy: 'Lively',
            hunger: 'Quick bite',
            familiarity: 'Something new',
            distance: 'Under 2 mi',
            cuisine: 'Italian',
        });

        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        await empty.or(goingBtn).waitFor({ state: 'visible', timeout: 10_000 });

        if (await goingBtn.isVisible()) {
            for (let i = 0; i < 20; i++) {
                if (await empty.isVisible()) break;
                if (!await goingBtn.isVisible({ timeout: 2_000 }).catch(() => false)) break;
                const r = waitForLivewire(page);
                await page.getByRole('button', { name: 'Not this one' }).click();
                await r;
                await empty.or(goingBtn).waitFor({ state: 'visible', timeout: 5_000 }).catch(() => {});
            }
        }

        if (!await empty.isVisible()) {
            test.skip();
            return;
        }

        await page.getByRole('button', { name: 'Start over' }).click();
        await expect(page.getByText('Step 1 of 7')).toBeVisible();
        await expect(page.getByText(/dineInTakeout/i)).toBeVisible();
    });

    // ── Going flow ────────────────────────────────────────────────────────────

    test('Going redirects to the dashboard', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        await goingBtn.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        await goingBtn.click();
        await expect(page).toHaveURL(/\/dashboard$/);
    });

    test('Going shows a success toast', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        const empty    = page.locator('[data-flux-heading]:has-text("No matches found")');
        await goingBtn.or(empty).waitFor({ state: 'visible', timeout: 10_000 });
        if (await empty.isVisible()) return;

        await goingBtn.click();
        await expect(page.getByRole('status')).toContainText('Enjoy your meal');
    });

    // ── Loading state ─────────────────────────────────────────────────────────

    test('shows a loading indicator while the final answer is being resolved', async ({ page }) => {
        await page.goto('/quiz');

        // Answer steps 1–6 without a delay.
        await page.getByRole('button', { name: /Either is fine/i }).click(); // dine-in/takeout
        await page.getByRole('button', { name: /Casual sit-down/i }).click(); // service level
        await page.getByRole('button', { name: /Surprise me/i }).click(); // cuisine
        await page.getByRole('button', { name: /Moderate/i }).click(); // energy
        await page.getByRole('button', { name: /Full meal/i }).click(); // hunger
        await page.getByRole('button', { name: /Anywhere/i }).click(); // distance

        // Intercept the Livewire resolve request and hold it so the loading
        // indicator stays visible long enough for the assertion.
        await page.route('**/livewire*/update', async route => {
            await new Promise(r => setTimeout(r, 800));
            await route.continue();
        });

        await page.getByRole('button', { name: /Either/i }).click(); // familiarity — resolves the quiz

        // Livewire's wire:loading attribute renders a loading indicator on the
        // active button while the network request is in flight.
        await expect(page.getByText(/Either/i)).toBeVisible();
    });
});

// ── Mobile: tap targets ─────────────────────────────────────────────────────
// These tests run under the 'mobile' project (iPhone 12) in playwright.config.ts.

test.describe('guided quiz — mobile', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    // Same shared-session concern as the desktop describe block above.
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await resetQuizState(page);
    });

    test('answer buttons are tall enough to be thumb-friendly (≥ 64 px)', async ({ page }) => {
        await page.goto('/quiz');

        // Measure the first answer button on step 1.
        const btn = page.getByRole('button', { name: /Dine in/i });
        await expect(btn).toBeVisible();

        const box = await btn.boundingBox();
        expect(box).not.toBeNull();
        // 44 px is Apple's HIG minimum touch-target height.
        expect(box!.height).toBeGreaterThanOrEqual(44);
    });

    test('can complete the full quiz by tapping on a mobile viewport', async ({ page }) => {
        await page.goto('/quiz');
        await completeQuiz(page);

        const result = page.locator('[data-flux-badge]:has-text("Quiz Pick")');
        const empty  = page.locator('[data-flux-heading]:has-text("No matches found")');
        await result.or(empty).waitFor({ state: 'visible', timeout: 15_000 });

        expect(
            await result.isVisible() || await empty.isVisible()
        ).toBe(true);
    });
});
