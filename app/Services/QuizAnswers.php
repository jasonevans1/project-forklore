<?php

namespace App\Services;

/**
 * Captured answers from the 5-step guided quiz.
 *
 * @property-read string      $energy      'lively' | 'moderate' | 'quiet'
 * @property-read string      $hunger      'light' | 'moderate' | 'hungry'
 * @property-read string      $familiarity 'new' | 'familiar' | 'either'
 * @property-read string      $distance    'nearby' | 'close' | 'anywhere'
 * @property-read string|null $cuisine     Cuisine tag, or null for "surprise me"
 * @property-read float|null  $lat         User latitude (injected by the component)
 * @property-read float|null  $lng         User longitude (injected by the component)
 */
readonly class QuizAnswers
{
    public function __construct(
        public string $energy = 'moderate',
        public string $hunger = 'moderate',
        public string $familiarity = 'either',
        public string $distance = 'anywhere',
        public ?string $cuisine = null,
        public ?float $lat = null,
        public ?float $lng = null,
    ) {}
}
