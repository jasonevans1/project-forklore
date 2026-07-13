<?php

namespace App\Enums;

use App\Services\QuizAnswers;

enum QuizQuestion: string
{
    case DineInTakeout = 'dineInTakeout';
    case ServiceLevel = 'serviceLevel';
    case Cuisine = 'cuisine';
    case Energy = 'energy';
    case Hunger = 'hunger';
    case Distance = 'distance';
    case Familiarity = 'familiarity';

    public function shouldSkip(QuizAnswers $answers): bool
    {
        return match ($this) {
            self::Energy, self::Familiarity => $answers->serviceLevel === 'quick_easy',
            default => false,
        };
    }
}
