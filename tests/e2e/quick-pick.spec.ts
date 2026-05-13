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
        const nameLocator = page.locator('[data-flux-badge]:has-text("Quick Pick") + [data-flux-heading]');
        const firstName = await nameLocator.textContent();

        await page.getByRole('button', { name: 'Not this one' }).click();
        // Wait for Livewire to re-render with the new restaurant — don't rely on
        // 'Going ✓' being visible (it was already visible), wait for the name itself
        // to change so we read the updated DOM.
        await expect(nameLocator).not.toHaveText(firstName!);

        const secondName = await nameLocator.textContent();

        // Pool has 10 seeded restaurants — a different one should be picked.
        expect(firstName).not.toBe(secondName);
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
