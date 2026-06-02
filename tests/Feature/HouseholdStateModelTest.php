<?php

use App\Models\HouseholdState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();

    // Link as partners.
    $this->alice->update(['partner_id' => $this->bob->id]);
    $this->bob->update(['partner_id' => $this->alice->id]);
});

// ---------------------------------------------------------------------------
// recordPick()
// ---------------------------------------------------------------------------

it('creates a household_state row for the picker after recordPick', function () {
    HouseholdState::recordPick($this->alice);

    $this->assertDatabaseHas('household_state', [
        'user_id' => $this->alice->id,
        'last_picker_id' => $this->alice->id,
    ]);
});

it('creates a household_state row for the partner after recordPick', function () {
    HouseholdState::recordPick($this->alice);

    $this->assertDatabaseHas('household_state', [
        'user_id' => $this->bob->id,
        'last_picker_id' => $this->alice->id,
    ]);
});

it('updates an existing row instead of creating a duplicate', function () {
    HouseholdState::recordPick($this->alice);
    HouseholdState::recordPick($this->bob);

    expect(HouseholdState::where('user_id', $this->alice->id)->count())->toBe(1);
    expect(HouseholdState::where('user_id', $this->bob->id)->count())->toBe(1);
});

it('reflects the most recent picker after multiple recordPick calls', function () {
    HouseholdState::recordPick($this->alice);
    HouseholdState::recordPick($this->bob);

    expect(
        HouseholdState::where('user_id', $this->alice->id)->value('last_picker_id')
    )->toBe($this->bob->id);
});

it('does not create a partner row when the user has no partner', function () {
    $solo = User::factory()->create(); // no partner_id

    HouseholdState::recordPick($solo);

    expect(HouseholdState::count())->toBe(1);
    $this->assertDatabaseHas('household_state', [
        'user_id' => $solo->id,
        'last_picker_id' => $solo->id,
    ]);
});

// ---------------------------------------------------------------------------
// isPartnersTurn()
// ---------------------------------------------------------------------------

it('returns false when no household_state exists for the user', function () {
    expect(HouseholdState::isPartnersTurn($this->alice))->toBeFalse();
});

it('returns true when the current user was the last picker', function () {
    HouseholdState::recordPick($this->alice);

    expect(HouseholdState::isPartnersTurn($this->alice))->toBeTrue();
});

it('returns false when the partner was the last picker', function () {
    HouseholdState::recordPick($this->bob);

    expect(HouseholdState::isPartnersTurn($this->alice))->toBeFalse();
});

it('returns false when the user has no partner', function () {
    $solo = User::factory()->create();
    HouseholdState::recordPick($solo);

    expect(HouseholdState::isPartnersTurn($solo))->toBeFalse();
});

// ---------------------------------------------------------------------------
// lastPickerFor()
// ---------------------------------------------------------------------------

it('returns null when no state exists', function () {
    expect(HouseholdState::lastPickerFor($this->alice))->toBeNull();
});

it('returns the User who last picked', function () {
    HouseholdState::recordPick($this->alice);

    $picker = HouseholdState::lastPickerFor($this->alice);

    expect($picker)->not->toBeNull()
        ->and($picker->id)->toBe($this->alice->id);
});
