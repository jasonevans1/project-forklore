import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const authFile = path.join(path.dirname(fileURLToPath(import.meta.url)), '.auth/user.json');

setup('authenticate as test user', async ({ page }) => {
    fs.mkdirSync(path.dirname(authFile), { recursive: true });

    await page.goto('/login');
    await page.getByLabel('Email').fill('test@example.com');
    await page.getByRole('textbox', { name: 'Password' }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.context().storageState({ path: authFile });
});
