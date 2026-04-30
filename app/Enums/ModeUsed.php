<?php

namespace App\Enums;

enum ModeUsed: string
{
    case QuickPick = 'quick_pick';
    case Event = 'event';
    case Quiz = 'quiz';
    case Tournament = 'tournament';
}
