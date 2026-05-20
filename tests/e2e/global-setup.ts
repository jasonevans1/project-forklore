import { execSync } from 'child_process';

/**
 * Runs once before the entire Playwright suite.
 *
 * Clears accumulated visits and leftover e2e restaurants so the QuickPick pool
 * is always full at the start of each run, regardless of how many times the
 * suite has been executed before.
 */
export default async function globalSetup(): Promise<void> {
    execSync('ddev exec php artisan e2e:reset', { stdio: 'inherit' });
}
