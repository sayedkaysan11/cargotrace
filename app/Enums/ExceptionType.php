<?php

namespace App\Enums;

enum ExceptionType: string
{
    case CustomsHold = 'CUSTOMS_HOLD';
    case WeatherDelay = 'WEATHER_DELAY';
    case PortCongestion = 'PORT_CONGESTION';
    case Damaged = 'DAMAGED';
    case Misrouted = 'MISROUTED';
    case DocumentationIssue = 'DOCUMENTATION_ISSUE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
