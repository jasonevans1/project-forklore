import { test, expect } from '@playwright/test';

// ── Unauthenticated guard ─────────────────────────────────────────────────────

test('unauthenticated user is redirected to login from /tonight', async ({ page }) => {
    await page.goto('/tonight');
    await expect(page).toHaveURL(/login/);
});

// ── Authenticated ─────────────────────────────────────────────────────────────

test.describe('something happening tonight', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    // ── Idle state ────────────────────────────────────────────────────────────

    test('shows the page title', async ({ page }) => {
        await page.goto('/tonight');
        await expect(page).toHaveTitle(/Something Happening Tonight/);
    });

    test('shows the "What\'s happening" button on load', async ({ page }) => {
        await page.goto('/tonight');
        await expect(page.getByRole('button', { name: "What's happening" })).toBeVisible();
    });

    test('shows a Something Happening Tonight link in the sidebar nav', async ({ page }) => {
        await page.goto('/tonight');
        await expect(page.getByRole('link', { name: /Tonight/i })).toBeVisible();
    });

    // ── Loading state ─────────────────────────────────────────────────────────

    test('shows "Looking…" while the Livewire request is in flight', async ({ page }) => {
        await page.goto('/tonight');

        await page.route('**/livewire*/update', async route => {
            await new Promise(r => setTimeout(r, 800));
            await route.continue();
        });

        await page.getByRole('button', { name: "What's happening" }).click();
        await expect(page.getByText('Looking…')).toBeVisible();
    });

    // ── Result or empty after tap ─────────────────────────────────────────────

    test('clicking "What\'s happening" transitions out of idle state', async ({ page }) => {
        await page.goto('/tonight');
        await page.getByRole('button', { name: "What's happening" }).click();

        // After the Livewire round-trip, either a result card or the empty state is shown.
        const goingBtn    = page.getByRole('button', { name: 'Going ✓' });
        const emptyHeading = page.locator('[data-flux-heading]:has-text("Nothing happening soon")');

        await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 10_000 });

        const hasResult = await goingBtn.isVisible();
        const hasEmpty  = await emptyHeading.isVisible();
        expect(hasResult || hasEmpty).toBe(true);
    });

    // ── Result card ───────────────────────────────────────────────────────────

    test.describe('when a result is shown', () => {
        /**
         * Navigate to /tonight and click through until we reach a result card,
         * or exhaust all restaurants. Returns true when a result card is visible.
         */
        async function loadResult(page: any): Promise<boolean> {
            await page.goto('/tonight');
            await page.getByRole('button', { name: "What's happening" }).click();

            const goingBtn    = page.getByRole('button', { name: 'Going ✓' });
            const emptyHeading = page.locator('[data-flux-heading]:has-text("Nothing happening soon")');

            await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 10_000 });
            return goingBtn.isVisible();
        }

        test('result card shows the Tonight badge', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            await expect(page.locator('[data-flux-badge]:has-text("Tonight")')).toBeVisible();
        });

        test('result card shows the Going and Not this one buttons', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();
            await expect(page.getByRole('button', { name: 'Not this one' })).toBeVisible();
        });

        test('result card shows the restaurant name', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            // Restaurant name is adjacent to the Tonight badge
            await expect(
                page.locator('[data-flux-badge]:has-text("Tonight") + [data-flux-heading]')
            ).toBeVisible();
        });

        test('result card shows the event label above the restaurant name', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            // The event label is the text element rendered between the Tonight badge
            // and the restaurant heading. It contains a time, e.g. "Trivia starts at 7pm".
            await expect(
                page.locator('[data-flux-badge]:has-text("Tonight")').locator('~ *').first()
            ).toBeVisible();
        });

        test('result card shows the swipe hint', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            await expect(page.getByText('← Swipe left to skip')).toBeVisible();
        });

        // ── Not this one ──────────────────────────────────────────────────────

        test('"Not this one" stays on the page and shows another result or empty state', async ({ page }) => {
            if (!await loadResult(page)) test.skip();

            const livewireNext = page.waitForResponse(
                (r: any) => /livewire.*\/update/.test(r.url()) && r.status() === 200,
                { timeout: 10_000 },
            );
            await page.getByRole('button', { name: 'Not this one' }).click();
            await livewireNext;

            await expect(page).toHaveURL(/\/tonight$/);

            const goingBtn    = page.getByRole('button', { name: 'Going ✓' });
            const emptyHeading = page.locator('[data-flux-heading]:has-text("Nothing happening soon")');
            await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 5_000 });

            const hasResult = await goingBtn.isVisible();
            const hasEmpty  = await emptyHeading.isVisible();
            expect(hasResult || hasEmpty).toBe(true);
        });

        // ── Going flow ────────────────────────────────────────────────────────

        test('"Going" redirects to the dashboard', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            await page.getByRole('button', { name: 'Going ✓' }).click();
            await expect(page).toHaveURL(/\/dashboard$/);
        });

        test('"Going" shows a success toast', async ({ page }) => {
            if (!await loadResult(page)) test.skip();
            await page.getByRole('button', { name: 'Going ✓' }).click();
            await expect(page.getByRole('status')).toContainText('great time tonight');
        });
    });

    // ── Empty state ───────────────────────────────────────────────────────────

    test('empty state shows a View restaurants link', async ({ page }) => {
        await page.goto('/tonight');
        await page.getByRole('button', { name: "What's happening" }).click();

        const goingBtn    = page.getByRole('button', { name: 'Going ✓' });
        const emptyHeading = page.locator('[data-flux-heading]:has-text("Nothing happening soon")');

        await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 10_000 });

        if (!await emptyHeading.isVisible()) {
            // Reject every restaurant until empty state is reached.
            for (let i = 0; i < 40; i++) {
                if (!await goingBtn.isVisible({ timeout: 3_000 }).catch(() => false)) break;

                const next = page.waitForResponse(
                    (r: any) => /livewire.*\/update/.test(r.url()) && r.status() === 200,
                    { timeout: 10_000 },
                );
                await page.getByRole('button', { name: 'Not this one' }).click();
                await next;

                await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 5_000 }).catch(() => {});
                if (await emptyHeading.isVisible()) break;
            }
        }

        // If still no empty state, the environment has no tonight-events seeded — skip.
        if (!await emptyHeading.isVisible()) test.skip();

        await expect(page.getByRole('link', { name: 'View restaurants' })).toBeVisible();
    });
});

// ── Mobile: swipe to reject ───────────────────────────────────────────────────

test.describe('something happening tonight — mobile swipe', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('swiping left more than 80px rejects the current tonight pick', async ({ page, browserName }) => {
        test.skip(browserName !== 'chromium', 'CDP touch events require Chromium');

        await page.goto('/tonight');

        const pickResponse = page.waitForResponse(
            (r: any) => /livewire.*\/update/.test(r.url()) && r.status() === 200,
            { timeout: 10_000 },
        );
        await page.getByRole('button', { name: "What's happening" }).click();
        await pickResponse;

        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        const emptyHeading = page.locator('[data-flux-heading]:has-text("Nothing happening soon")');

        await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 10_000 });

        // No result to swipe — skip gracefully.
        if (!await goingBtn.isVisible()) test.skip();

        const cardBox = await page.locator('[data-flux-badge]:has-text("Tonight")').boundingBox();
        const cx = (cardBox?.x ?? 0) + (cardBox?.width ?? 200) / 2;
        const cy = (cardBox?.y ?? 0) + (cardBox?.height ?? 100) / 2;

        const livewireReject = page.waitForResponse(
            (r: any) => /livewire.*\/update/.test(r.url()) && r.status() === 200,
            { timeout: 10_000 },
        );

        const session = await page.context().newCDPSession(page);
        await session.send('Input.dispatchTouchEvent', {
            type: 'touchStart',
            touchPoints: [{ x: cx, y: cy, id: 0, radiusX: 1, radiusY: 1, rotationAngle: 0, force: 1 }],
        });
        await session.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{ x: cx - 120, y: cy, id: 0, radiusX: 1, radiusY: 1, rotationAngle: 0, force: 1 }],
        });
        await session.send('Input.dispatchTouchEvent', {
            type: 'touchEnd',
            touchPoints: [],
        });
        await session.detach();

        await livewireReject;

        await goingBtn.or(emptyHeading).waitFor({ state: 'visible', timeout: 5_000 });
        const afterHasResult = await goingBtn.isVisible();
        const afterHasEmpty  = await emptyHeading.isVisible();
        expect(afterHasResult || afterHasEmpty).toBe(true);
    });
});
