<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Collection;

class TournamentService
{
    /** Minimum pool size required to seed a bracket. */
    private const int MIN_SIZE = 4;

    /** Bracket sizes supported, in ascending order. */
    private const array BRACKET_SIZES = [4, 8];

    /**
     * Seed a bracket from the user's favorites.
     *
     * Picks the largest supported bracket size that fits within the available
     * pool. Returns an empty array when the pool is smaller than MIN_SIZE.
     *
     * @return list<Restaurant>
     */
    public function seed(User $user, ?int $budgetMax = null): array
    {
        $pool = $this->buildPool($user, $budgetMax);

        if ($pool->count() < self::MIN_SIZE) {
            return [];
        }

        $size = $this->bracketSize($pool->count());

        return $pool->shuffle()->take($size)->values()->all();
    }

    /**
     * Filter the current bracket down to only the winners, preserving order.
     *
     * @param  list<Restaurant>  $bracket
     * @param  list<int>  $winnerIds
     * @return list<Restaurant>
     */
    public function advance(array $bracket, array $winnerIds): array
    {
        return collect($bracket)
            ->filter(fn (Restaurant $r) => in_array($r->id, $winnerIds, true))
            ->values()
            ->all();
    }

    /**
     * Return the single champion from a final bracket of one, or null.
     *
     * @param  list<Restaurant>  $bracket
     */
    public function winner(array $bracket): ?Restaurant
    {
        return count($bracket) === 1 ? $bracket[0] : null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @return Collection<int, Restaurant>
     */
    private function buildPool(User $user, ?int $budgetMax): Collection
    {
        $query = Restaurant::ownedBy($user)->favorites();

        if ($budgetMax !== null) {
            $query->where('price_level', '<=', $budgetMax);
        }

        return $query->get();
    }

    /**
     * Choose the largest bracket size that the pool can fill.
     */
    private function bracketSize(int $poolSize): int
    {
        $size = self::MIN_SIZE;

        foreach (self::BRACKET_SIZES as $candidate) {
            if ($poolSize >= $candidate) {
                $size = $candidate;
            }
        }

        return $size;
    }
}
