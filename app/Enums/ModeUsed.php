<?php

namespace App\Enums;

enum ModeUsed: string
{
    case QuickPick = 'quick_pick';
    case Tonight = 'tonight';
    case Event = 'event';
    case Quiz = 'quiz';
    case Tournament = 'tournament';
}
