<?php

namespace App\Enums;

enum ServiceLevel: string
{
    case FastFood = 'fast_food';
    case FastCasual = 'fast_casual';
    case Casual = 'casual';
    case UpscaleCasual = 'upscale_casual';
    case FineDining = 'fine_dining';

    public function label(): string
    {
        return match ($this) {
            self::FastFood => 'Fast food',
            self::FastCasual => 'Fast casual',
            self::Casual => 'Casual',
            self::UpscaleCasual => 'Upscale casual',
            self::FineDining => 'Fine dining',
        };
    }
}
