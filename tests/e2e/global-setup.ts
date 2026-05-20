import { execSync } from 'child_process';

/**
 * Runs once before the entire Playwright suite.
 *
 * Clears accumulated visits and leftover e2e restaurants so the QuickPick pool
 * is always full at the start of each run, regardless of how many times the
 * suite has been executed before.
 *
 * In CI (GitHub Actions) the app runs via `php artisan serve` so we call
 * artisan directly. Locally the app runs inside DDEV, so we prefix with
 * `ddev exec` to target the web container.
 */
export default async function globalSetup(): Promise<void> {
    const cmd = process.env.CI
        ? 'php artisan e2e:reset'
        : 'ddev exec php artisan e2e:reset';

    execSync(cmd, { stdio: 'inherit' });
}
