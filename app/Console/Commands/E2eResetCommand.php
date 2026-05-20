<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Console\Command;

/**
 * Resets e2e test state so every Playwright run starts from a clean baseline.
 *
 * What it does:
 *  - Deletes all visits for the test user (keeps the QuickPick pool full)
 *  - Removes any restaurants created by e2e tests (non-seeded names)
 *  - Calls the RestaurantSeeder to restore seeded rows if any were accidentally deleted
 */
class E2eResetCommand extends Command
{
    protected $signature = 'e2e:reset';

    protected $description = 'Reset Playwright e2e test data (visits + test-created restaurants)';

    public function handle(): int
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            $this->error('test@example.com user not found. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        $visitCount = Visit::where('user_id', $user->id)->delete();
        $this->line("  Deleted {$visitCount} visit(s) for test user.");

        $seededNames = [
            "Fong's Pizza", 'Exile Brewing Co', 'Centro', 'Zombie Burger',
            'Django', 'Proof', 'ARC Restaurant', 'El Bait Shop',
            "Zanzibar's Coffee Adventure", 'Eatery A', '85 Bar', 'Food Depot',
        ];

        $e2eCount = Restaurant::where('owner_user_id', $user->id)
            ->whereNotIn('name', $seededNames)
            ->delete();
        $this->line("  Deleted {$e2eCount} e2e-created restaurant(s).");

        $this->call('db:seed', ['--class' => 'RestaurantSeeder']);

        $this->info('E2e state reset complete.');

        return self::SUCCESS;
    }
}
