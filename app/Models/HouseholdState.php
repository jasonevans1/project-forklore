<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'last_picker_id'])]
class HouseholdState extends Model
{
    protected $table = 'household_state';

    /**
     * Record that $picker just made a selection.
     *
     * Upserts a row for the picker AND their partner so both see the same
     * last_picker_id without any join queries.
     */
    public static function recordPick(User $picker): void
    {
        static::upsert(
            [['user_id' => $picker->id, 'last_picker_id' => $picker->id]],
            uniqueBy: ['user_id'],
            update: ['last_picker_id'],
        );

        if ($picker->partner_id !== null) {
            static::upsert(
                [['user_id' => $picker->partner_id, 'last_picker_id' => $picker->id]],
                uniqueBy: ['user_id'],
                update: ['last_picker_id'],
            );
        }
    }

    /**
     * Return true when the current user was the last picker — meaning it is
     * now the partner's turn.
     */
    public static function isPartnersTurn(User $user): bool
    {
        if ($user->partner_id === null) {
            return false;
        }

        $row = static::where('user_id', $user->id)->first();

        if ($row === null) {
            return false;
        }

        return (int) $row->last_picker_id === (int) $user->id;
    }

    /**
     * Return the User who most recently made a pick for this household,
     * or null when no state has been recorded yet.
     */
    public static function lastPickerFor(User $user): ?User
    {
        $row = static::with('lastPicker')
            ->where('user_id', $user->id)
            ->first();

        return $row?->lastPicker;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastPicker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_picker_id');
    }
}
