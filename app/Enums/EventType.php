<?php

namespace App\Enums;

enum EventType: string
{
    case Trivia = 'trivia';
    case Bingo = 'bingo';
    case LiveMusic = 'live_music';
    case HappyHour = 'happy_hour';
    case Special = 'special';
    case Other = 'other';
}
