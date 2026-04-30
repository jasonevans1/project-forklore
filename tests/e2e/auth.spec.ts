import { test, expect } from '@playwright/test';

test('login page renders', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveTitle(/Forklore/);
    await expect(page.getByRole('textbox', { name: /email/i })).toBeVisible();
    await expect(page.getByRole('textbox', { name: /password/i })).toBeVisible();
});

test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/login/);
});
