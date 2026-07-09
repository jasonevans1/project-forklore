<?php

namespace App\Enums;

enum ServiceOption: string
{
    case DineIn = 'dine_in';
    case Takeout = 'takeout';
    case Delivery = 'delivery';
    case DriveThru = 'drive_thru';
    case Curbside = 'curbside';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine in',
            self::Takeout => 'Takeout',
            self::Delivery => 'Delivery',
            self::DriveThru => 'Drive thru',
            self::Curbside => 'Curbside',
        };
    }
}
