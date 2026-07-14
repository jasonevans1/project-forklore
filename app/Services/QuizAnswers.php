<?php

namespace App\Services;

/**
 * Captured answers from the guided quiz wizard.
 *
 * @property-read string      $energy        'lively' | 'moderate' | 'quiet'
 * @property-read string      $hunger        'quick_bite' | 'full_meal' | 'feast'
 * @property-read string      $familiarity   'new' | 'familiar' | 'either'
 * @property-read string      $distance      'under_2_miles' | '2_to_5_miles' | '5_to_15_miles' | 'anywhere'
 * @property-read string|null $cuisine       A `PrimaryCuisine` enum value, or null for "surprise me"
 * @property-read float|null  $lat           User latitude (injected by the component)
 * @property-read float|null  $lng           User longitude (injected by the component)
 * @property-read string      $serviceLevel  'quick_easy' | 'casual_sit_down' | 'nicer_night_out' | 'special_occasion'
 * @property-read string      $dineInTakeout 'dine_in' | 'takeout' | 'either'
 */
readonly class QuizAnswers
{
    public function __construct(
        public string $energy = 'moderate',
        public string $hunger = 'full_meal',
        public string $familiarity = 'either',
        public string $distance = 'anywhere',
        public ?string $cuisine = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public string $serviceLevel = 'casual_sit_down',
        public string $dineInTakeout = 'either',
    ) {}
}
