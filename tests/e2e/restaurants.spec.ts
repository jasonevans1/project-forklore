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

test('unauthenticated user is redirected to login from a restaurant show page', async ({ page }) => {
    await page.goto('/restaurants/1');
    await expect(page).toHaveURL(/login/);
});

test('unauthenticated user is redirected to login from a restaurant edit page', async ({ page }) => {
    await page.goto('/restaurants/1/edit');
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
        // Vibe tags is a chip picker — verify chips are rendered
        await expect(page.getByRole('button', { name: 'casual' })).toBeVisible();
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
        await page.getByRole('button', { name: 'casual' }).click();
        // Wait for Livewire to sync chip selection back to the parent component
        await expect(page.getByRole('button', { name: 'casual' })).toHaveClass(/bg-\[var\(--color-accent\)\]/);
        await page.getByRole('combobox', { name: 'Price level' }).selectOption('2');
        await page.getByRole('button', { name: 'Add restaurant' }).click();

        await expect(page).toHaveURL(/\/restaurants$/);
        await expect(page.getByRole('link', { name: 'E2E Test Bistro' }).first()).toBeVisible();
    });
});

// ── Authenticated: restaurant show ───────────────────────────────────────────

test.describe('restaurant show', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('shows the restaurant name as the page heading', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await expect(page.getByText("Fong's Pizza", { exact: true }).first()).toBeVisible();
    });

    test('shows cuisine tags', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await expect(page.getByText('pizza', { exact: true })).toBeVisible();
    });

    test('shows the address', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await expect(page.getByText('223 4th St, Des Moines')).toBeVisible();
    });

    test('shows vibe tags', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await expect(page.getByText('lively')).toBeVisible();
    });

    test('edit button is visible', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await expect(page.getByRole('link', { name: 'Edit' })).toBeVisible();
    });

    test('edit button navigates to the edit page', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page).toHaveURL(/\/edit$/);
    });

    test('delete button is visible', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await expect(page.getByRole('button', { name: 'Delete' }).first()).toBeVisible();
    });

    test('delete button opens a confirmation modal', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: "Fong's Pizza" }).click();
        await page.getByRole('button', { name: 'Delete' }).first().click();
        await expect(page.getByText('Delete restaurant?')).toBeVisible();
    });
});

// ── Authenticated: restaurant edit ───────────────────────────────────────────

test.describe('restaurant edit', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('shows the page title', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page).toHaveTitle(/Edit/);
    });

    test('pre-populates the name field', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page.getByRole('textbox', { name: 'Name' })).toHaveValue('Centro');
    });

    test('pre-populates the address field', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page.getByRole('textbox', { name: 'Address' })).toHaveValue('1003 Locust St, Des Moines');
    });

    test('pre-populates cuisine tags and shows selected vibe chips', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        await expect(page.getByRole('textbox', { name: 'Cuisine tags' })).toHaveValue('italian');
        // date_night is Centro's saved vibe tag — chip should render in primary (selected) state
        await expect(page.getByRole('button', { name: 'date night' })).toHaveClass(/bg-\[var\(--color-accent\)\]/);
    });

    test('save changes redirects to the show page', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.waitForURL(/\/restaurants\/\d+$/);
        const showUrl = page.url();
        await page.getByRole('link', { name: 'Edit' }).click();
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page).toHaveURL(showUrl);
    });

    test('cancel button returns to the show page', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.waitForURL(/\/restaurants\/\d+$/);
        const showUrl = page.url();
        await page.getByRole('link', { name: 'Edit' }).click();
        await page.getByRole('link', { name: 'Cancel' }).click();
        await expect(page).toHaveURL(showUrl);
    });

    test('shows a validation error when name is cleared', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        await page.getByRole('textbox', { name: 'Name' }).clear();
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText(/required/i).first()).toBeVisible();
    });

    test('shows a validation error when all vibe chips are deselected', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Centro' }).click();
        await page.getByRole('link', { name: 'Edit' }).click();
        // Deselect the pre-selected chip to produce an empty vibe_tags array
        await page.getByRole('button', { name: 'date night' }).click();
        // Wait for Livewire to sync the deselection back to the parent component
        await expect(page.getByRole('button', { name: 'date night' })).not.toHaveClass(/bg-\[var\(--color-accent\)\]/);
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText(/required/i).first()).toBeVisible();
    });
});

// ── Authenticated: restaurant delete ─────────────────────────────────────────

test.describe('restaurant delete', () => {
    test.use({ storageState: 'tests/e2e/.auth/user.json' });

    test('cancel in the confirmation modal keeps the user on the show page', async ({ page }) => {
        await page.goto('/restaurants');
        await page.getByRole('link', { name: 'Django' }).click();
        await page.waitForURL(/\/restaurants\/\d+$/);
        const showUrl = page.url();
        await page.getByRole('button', { name: 'Delete' }).first().click();
        await expect(page.getByText('Delete restaurant?')).toBeVisible();
        await page.getByRole('button', { name: 'Cancel' }).click();
        await expect(page).toHaveURL(showUrl);
    });

    test('confirming delete removes the restaurant and redirects to the index', async ({ page }) => {
        // Use a unique name so stale data from prior runs never causes ambiguous locators.
        const name = `E2E Delete Target ${Date.now()}`;

        await page.goto('/restaurants/create');
        await page.getByRole('textbox', { name: 'Name' }).fill(name);
        await page.getByRole('textbox', { name: 'Cuisine tags' }).fill('Test');
        await page.getByRole('button', { name: 'casual' }).click();
        await expect(page.getByRole('button', { name: 'casual' })).toHaveClass(/bg-\[var\(--color-accent\)\]/);
        await page.getByRole('button', { name: 'Add restaurant' }).click();
        await expect(page).toHaveURL(/\/restaurants$/);

        await page.getByRole('link', { name }).click();
        await page.getByRole('button', { name: 'Delete' }).first().click();
        await expect(page.getByText('Delete restaurant?')).toBeVisible();
        await page.getByRole('button', { name: 'Delete' }).last().click();

        await expect(page).toHaveURL(/\/restaurants$/);
        await expect(page.getByRole('link', { name })).not.toBeVisible();
    });
});
