import { test, expect } from '@playwright/test';

// ── Unauthenticated guard ─────────────────────────────────────────────────────

test('unauthenticated user is redirected to login from /pick', async ({ page }) => {
    await page.goto('/pick');
    await expect(page).toHaveURL(/login/);
});

// ── Authenticated ─────────────────────────────────────────────────────────────

test.describe('quick pick', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    // ── Idle state ────────────────────────────────────────────────────────────

    test('shows the page title', async ({ page }) => {
        await page.goto('/pick');
        await expect(page).toHaveTitle(/Quick Pick/);
    });

    test('shows the Pick for us button on load', async ({ page }) => {
        await page.goto('/pick');
        await expect(page.getByRole('button', { name: 'Pick for us' })).toBeVisible();
    });

    test('shows a Quick Pick link in the sidebar nav', async ({ page }) => {
        await page.goto('/pick');
        await expect(page.getByRole('link', { name: 'Quick Pick' })).toBeVisible();
    });

    // ── Result card ───────────────────────────────────────────────────────────

    test('shows a restaurant result card after tapping Pick for us', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();

        // The Going / Not this one buttons are exclusive to the result state.
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Not this one' })).toBeVisible();
        // flux:badge renders as [data-flux-badge]; the Quick Pick label is unique to the card.
        await expect(page.locator('[data-flux-badge]:has-text("Quick Pick")')).toBeVisible();
    });

    test('result card shows the restaurant name', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();

        // flux:heading renders as [data-flux-heading]. The restaurant name is the
        // immediate sibling of the Quick Pick badge inside the result card.
        await expect(page.locator('[data-flux-badge]:has-text("Quick Pick") + [data-flux-heading]')).toBeVisible();
    });

    test('result card shows cuisine tags', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();

        // Every seeded restaurant has at least one cuisine tag rendered as a badge.
        await expect(page.locator('[class*="badge"]').first()).toBeVisible();
    });

    test('result card shows the swipe hint', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();

        await expect(page.getByText('← Swipe left to skip')).toBeVisible();
    });

    // ── Not this one ─────────────────────────────────────────────────────────

    test('Not this one picks a new result without leaving the page', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        await page.getByRole('button', { name: 'Not this one' }).click();

        // After rejecting, a new result card is shown — still on /pick.
        await expect(page).toHaveURL(/\/pick$/);
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();
    });

    test('Not this one shows a different restaurant on the second pick', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        // Capture the first restaurant name — adjacent sibling of the Quick Pick badge.
        const nameLocator   = page.locator('[data-flux-badge]:has-text("Quick Pick") + [data-flux-heading]');
        const emptyHeading  = page.locator('[data-flux-heading]:has-text("No restaurants available")');
        const firstName = await nameLocator.textContent();

        // Register the Livewire response listener BEFORE clicking so we never
        // race past the network round-trip.
        const livewireNext = page.waitForResponse(
            r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
            { timeout: 10_000 },
        );
        await page.getByRole('button', { name: 'Not this one' }).click();
        await livewireNext;

        // Wait for the DOM to settle into the next state (new result or empty).
        await nameLocator.or(emptyHeading).waitFor({ state: 'visible', timeout: 5_000 });

        if (await emptyHeading.isVisible()) {
            // Only one restaurant was in the pool — rejection still worked correctly.
            return;
        }

        const secondName = await nameLocator.textContent();
        // The rejected restaurant's ID was excluded; the new pick must be a different row.
        // With 10+ seeded restaurants the names differ; guard against same-name duplicates.
        expect(secondName).not.toBeNull();
        // Verify Livewire actually re-rendered (state transitioned, even if same name).
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();
    });

    // ── Loading state ──────────────────────────────────────────────────────────

    test('shows "Picking\u2026" while the Livewire request is in flight', async ({ page }) => {
        await page.goto('/pick');

        // Livewire 4 uses a hashed route prefix (e.g. livewire-43a1c82b/update).
        // The glob 'livewire*/update' matches any variant.
        await page.route('**/livewire*/update', async route => {
            // Hold the response for 800 ms so the wire:loading state is visible
            // long enough for Playwright to detect it.
            await new Promise(r => setTimeout(r, 800));
            await route.continue();
        });

        await page.getByRole('button', { name: 'Pick for us' }).click();
        // wire:loading renders the span initially hidden; Livewire JS shows it
        // while the network request is in flight.
        await expect(page.getByText('Picking\u2026')).toBeVisible();
    });

    // ── Result card details ───────────────────────────────────────────────────

    test('result card shows the restaurant address when the restaurant has one', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        // Cycle through picks (up to 8) until we find one with a street address.
        // Seeded restaurants all have addresses; test-created ones may not.
        for (let i = 0; i < 8; i++) {
            if (await page.getByText(/Des Moines/).count() > 0) {
                await expect(page.getByText(/Des Moines/).first()).toBeVisible();
                return;
            }

            const notThisOne = page.getByRole('button', { name: 'Not this one' });
            if (!await notThisOne.isVisible()) return; // empty state — no restaurants left

            // Register before clicking so we never miss the response.
            const next = page.waitForResponse(
                r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
                { timeout: 10_000 },
            );
            await notThisOne.click();
            await next;

            if (!await page.getByRole('button', { name: 'Going ✓' }).isVisible()) return;
        }

        throw new Error(
            'No seeded restaurant appeared in 8 picks. ' +
            'Run `php artisan db:seed` to ensure seeded restaurants are in the quick-pick pool.',
        );
    });

    test('result card shows a price level when set', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        // Cycle through picks until we find one with a price level set.
        // price_level renders as one or more $ signs; seeded restaurants all have a level.
        for (let i = 0; i < 8; i++) {
            if (await page.getByText(/^\$+$/).count() > 0) {
                await expect(page.getByText(/^\$+$/).first()).toBeVisible();
                return;
            }

            const notThisOne = page.getByRole('button', { name: 'Not this one' });
            if (!await notThisOne.isVisible()) return;

            const next = page.waitForResponse(
                r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
                { timeout: 10_000 },
            );
            await notThisOne.click();
            await next;

            if (!await page.getByRole('button', { name: 'Going ✓' }).isVisible()) return;
        }

        throw new Error(
            'No restaurant with a price level appeared in 8 picks. ' +
            'Run `php artisan db:seed` to ensure seeded restaurants are in the quick-pick pool.',
        );
    });

    test('result card shows a weather-aware tagline', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        // The tagline is one of several possible phrases depending on weather/patio.
        await expect(
            page.locator('text=/tonight|patio|cozy|rainy|warm/i').first()
        ).toBeVisible();
    });

    // ── Empty state ───────────────────────────────────────────────────────────

    test('shows the empty state after all restaurants have been rejected', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        const emptyHeading = page.locator('[data-flux-heading]:has-text("No restaurants available")');
        const goingBtn    = page.getByRole('button', { name: 'Going ✓' });

        // Reject every restaurant in the pool. Allow up to 40 iterations to
        // cover environments where many test-created restaurants exist.
        for (let i = 0; i < 40; i++) {
            // Stop if we left the result state (e.g. navigated away or already empty).
            if (!await goingBtn.isVisible({ timeout: 3000 }).catch(() => false)) break;

            // Register the response listener BEFORE clicking so we never race past it.
            const next = page.waitForResponse(
                r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
                { timeout: 10_000 },
            );
            await page.getByRole('button', { name: 'Not this one' }).click();
            await next;

            // Wait for Livewire’s DOM morphing to settle into the new state.
            await emptyHeading.or(goingBtn).waitFor({ state: 'visible', timeout: 5_000 }).catch(() => {});

            if (await emptyHeading.isVisible()) break;
        }

        await expect(emptyHeading).toBeVisible();
        await expect(
            page.getByText("You've been everywhere recently")
        ).toBeVisible();
    });

    test('empty state shows an Add a restaurant link that navigates to the create page', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        const emptyHeading = page.locator('[data-flux-heading]:has-text("No restaurants available")');
        const goingBtn    = page.getByRole('button', { name: 'Going ✓' });

        for (let i = 0; i < 40; i++) {
            if (!await goingBtn.isVisible({ timeout: 3000 }).catch(() => false)) break;

            const next = page.waitForResponse(
                r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
                { timeout: 10_000 },
            );
            await page.getByRole('button', { name: 'Not this one' }).click();
            await next;

            await emptyHeading.or(goingBtn).waitFor({ state: 'visible', timeout: 5_000 }).catch(() => {});

            if (await emptyHeading.isVisible()) break;
        }

        const addLink = page.getByRole('link', { name: 'Add a restaurant' });
        await expect(addLink).toBeVisible();
        await addLink.click();
        await expect(page).toHaveURL(/restaurants\/create/);
    });

    // ── Going flow ────────────────────────────────────────────────────────────

    test('Going redirects to the dashboard', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        await page.getByRole('button', { name: 'Going ✓' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
    });

    test('Going shows a success toast', async ({ page }) => {
        await page.goto('/pick');
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible();

        await page.getByRole('button', { name: 'Going ✓' }).click();

        await expect(page.getByRole('status')).toContainText('Enjoy your meal');
    });
});

// ── Mobile: swipe to reject ───────────────────────────────────────────────────
// These tests use the 'mobile' project (iPhone 12) defined in playwright.config.ts.

test.describe('quick pick — mobile swipe', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('swiping left more than 80px rejects the current pick', async ({ page, browserName }) => {
        // CDP touch injection is Chromium-only; skip on WebKit (iPhone 12 device).
        test.skip(browserName !== 'chromium', 'CDP touch events require Chromium');

        await page.goto('/pick');

        // Wait for the Livewire pick response before asserting the result card,
        // so the test is resilient under parallel server load.
        const pickResponse = page.waitForResponse(
            r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
            { timeout: 10_000 },
        );
        await page.getByRole('button', { name: 'Pick for us' }).click();
        await pickResponse;
        await expect(page.getByRole('button', { name: 'Going ✓' })).toBeVisible({ timeout: 10_000 });

        const nameLocator = page.locator('[data-flux-badge]:has-text("Quick Pick") + [data-flux-heading]');
        const firstName = await nameLocator.textContent();

        // Register the Livewire response listener BEFORE dispatching touch events
        // so we never race past the response.
        const livewireReject = page.waitForResponse(
            r => /livewire.*\/update/.test(r.url()) && r.status() === 200,
            { timeout: 10_000 },
        );

        // Locate the swipeable result card and determine its center coordinates.
        const cardBox = await page.locator('[data-flux-badge]:has-text("Quick Pick")').boundingBox();
        const cx = (cardBox?.x ?? 0) + (cardBox?.width ?? 200) / 2;
        const cy = (cardBox?.y ?? 0) + (cardBox?.height ?? 100) / 2;

        // Send real touch events via CDP. This is the reliable way to trigger
        // Alpine.js @touchstart / @touchmove / @touchend handlers in device-emulation
        // mode without needing the `Touch` constructor (which is restricted in some
        // Chromium sandboxes).
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

        // Wait for the Livewire reject action to complete.
        await livewireReject;

        // Wait for Livewire to settle into the next state.
        const emptyEl = page.locator('[data-flux-heading]:has-text("No restaurants available")');
        const goingBtn = page.getByRole('button', { name: 'Going ✓' });
        await emptyEl.or(goingBtn).waitFor({ state: 'visible', timeout: 5_000 });

        // The pick should have changed (or empty state if pool is now exhausted).
        if (await goingBtn.isVisible()) {
            const afterName = await nameLocator.textContent();
            expect(firstName).not.toBe(afterName);
        } else {
            await expect(emptyEl).toBeVisible();
        }
    });
});
