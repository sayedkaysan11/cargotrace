<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'OPEN';
    case Investigating = 'INVESTIGATING';
    case Resolved = 'RESOLVED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
