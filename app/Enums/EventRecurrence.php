<?php

namespace App\Enums;

enum EventRecurrence: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case OneOff = 'one_off';
}
