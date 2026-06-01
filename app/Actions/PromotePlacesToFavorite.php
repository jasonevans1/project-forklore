<?php

namespace App\Actions;

use App\Enums\RestaurantSource;
use App\Models\Restaurant;
use App\Models\User;

class PromotePlacesToFavorite
{
    /**
     * Flip a Places-sourced restaurant to a user-owned favorite.
     *
     * Only promotes when the current source is `places`. Calling this on a
     * restaurant that is already a favorite is intentionally a no-op so the
     * action is safe to call unconditionally.
     */
    public function execute(Restaurant $restaurant, User $user): void
    {
        if ($restaurant->source !== RestaurantSource::Places) {
            return;
        }

        $restaurant->update([
            'source' => RestaurantSource::Favorite,
            'owner_user_id' => $user->id,
        ]);
    }
}
