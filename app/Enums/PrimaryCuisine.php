<?php

namespace App\Enums;

enum PrimaryCuisine: string
{
    case American = 'american';
    case Italian = 'italian';
    case Mexican = 'mexican';
    case AsianGeneral = 'asian_general';
    case Chinese = 'chinese';
    case Japanese = 'japanese';
    case Thai = 'thai';
    case Vietnamese = 'vietnamese';
    case Korean = 'korean';
    case Indian = 'indian';
    case Mediterranean = 'mediterranean';
    case Bbq = 'bbq';
    case Seafood = 'seafood';
    case Pizza = 'pizza';
    case Breakfast = 'breakfast';
    case Cafe = 'cafe';
    case BarFood = 'bar_food';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::American => 'American',
            self::Italian => 'Italian',
            self::Mexican => 'Mexican',
            self::AsianGeneral => 'Asian (general)',
            self::Chinese => 'Chinese',
            self::Japanese => 'Japanese',
            self::Thai => 'Thai',
            self::Vietnamese => 'Vietnamese',
            self::Korean => 'Korean',
            self::Indian => 'Indian',
            self::Mediterranean => 'Mediterranean',
            self::Bbq => 'BBQ',
            self::Seafood => 'Seafood',
            self::Pizza => 'Pizza',
            self::Breakfast => 'Breakfast',
            self::Cafe => 'Cafe',
            self::BarFood => 'Bar food',
            self::Other => 'Other',
        };
    }
}
