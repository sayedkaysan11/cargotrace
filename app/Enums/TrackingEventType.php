<?php

namespace App\Enums;

enum TrackingEventType: string
{
    case Created = 'CREATED';
    case Booked = 'BOOKED';
    case PickedUp = 'PICKED_UP';
    case DepartedPort = 'DEPARTED_PORT';
    case Transshipment = 'TRANSSHIPMENT';
    case InTransit = 'IN_TRANSIT';
    case ArrivedPort = 'ARRIVED_PORT';
    case CustomsClearance = 'CUSTOMS_CLEARANCE';
    case Delivered = 'DELIVERED';
    case Exception = 'EXCEPTION';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
