import { test, expect } from '@playwright/test';

// ── Unauthenticated guards ────────────────────────────────────────────────────

test('unauthenticated user is redirected to login from /restaurants', async ({ page }) => {
    await page.goto('/restaurants');
    await expect(page).toHaveURL(/login/);
});

test('unauthenticated user is redirected to login from /restaurants/create', async ({ page }) => {
    await page.goto('/restaurants/create');
    await expect(page).toHaveURL(/login/);
});

// ── Authenticated: restaurant index ──────────────────────────────────────────

test.describe('restaurant index', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('shows the page title', async ({ page }) => {
        await page.goto('/restaurants');
        await expect(page).toHaveTitle(/Restaurants/);
    });

    test('lists restaurant cards with name, cuisine tags, and price level', async ({ page }) => {
        await page.goto('/restaurants');

        // A known seeded restaurant
        await expect(page.getByText('Zombie Burger')).toBeVisible();
        await expect(page.getByText('burgers').first()).toBeVisible();
        await expect(page.getByText('$').first()).toBeVisible();
    });

    test('shows the add restaurant link', async ({ page }) => {
        await page.goto('/restaurants');
        await expect(page.getByRole('link', { name: 'Add restaurant' })).toBeVisible();
    });

    test('add restaurant link navigates to the create page', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Add restaurant' }).click();
        await expect(page).toHaveURL(/restaurants\/create/);
    });
});

// ── Authenticated: create form ────────────────────────────────────────────────

test.describe('restaurant create', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('shows the page title', async ({ page }) => {
        await page.goto('/restaurants/create');
        await expect(page).toHaveTitle(/Add restaurant/);
    });

    test('renders all form fields', async ({ page }) => {
        await page.goto('/restaurants/create');
        await expect(page.getByRole('textbox', { name: 'Name' })).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Address' })).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Cuisine tags' })).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Vibe tags' })).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Price level' })).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Patio quality' })).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Indoor vibe when cold' })).toBeVisible();
        await expect(page.getByRole('spinbutton', { name: /duration/i })).toBeVisible();
    });

    test('does not expose a source field', async ({ page }) => {
        await page.goto('/restaurants/create');
        await expect(page.locator('input[name="source"], select[name="source"]')).toHaveCount(0);
    });

    test('submit button is visible', async ({ page }) => {
        await page.goto('/restaurants/create');
        await expect(page.getByRole('button', { name: 'Add restaurant' })).toBeVisible();
    });

    test('shows validation errors when submitting without required fields', async ({ page }) => {
        await page.goto('/restaurants/create');
        await page.getByRole('button', { name: 'Add restaurant' }).click();
        // Name is required — Flux renders its error inside the input group
        await expect(page.getByText(/required/i).first()).toBeVisible();
    });

    test('saves a restaurant and redirects to the index on valid submission', async ({ page }) => {
        await page.goto('/restaurants/create');

        await page.getByRole('textbox', { name: 'Name' }).fill('E2E Test Bistro');
        await page.getByRole('textbox', { name: 'Cuisine tags' }).fill('French, Bistro');
        await page.getByRole('textbox', { name: 'Vibe tags' }).fill('romantic');
        await page.getByRole('combobox', { name: 'Price level' }).selectOption('2');
        await page.getByRole('button', { name: 'Add restaurant' }).click();

        await expect(page).toHaveURL(/\/restaurants$/);
        await expect(page.getByText('E2E Test Bistro')).toBeVisible();
    });
});
