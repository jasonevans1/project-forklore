<?php

namespace App\Models;

use App\Enums\IndoorVibe;
use App\Enums\PatioQuality;
use App\Enums\RestaurantSource;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'owner_user_id',
    'name',
    'address',
    'lat',
    'lng',
    'cuisine_tags',
    'vibe_tags',
    'price_level',
    'source',
    'patio_quality',
    'indoor_vibe_when_cold',
    'avg_duration_minutes',
    'last_visited_at',
    'visit_count',
])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cuisine_tags' => 'array',
            'vibe_tags' => 'array',
            'source' => RestaurantSource::class,
            'patio_quality' => PatioQuality::class,
            'indoor_vibe_when_cold' => IndoorVibe::class,
            'last_visited_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this restaurant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Scope restaurants to a specific owner.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->id);
    }
}
