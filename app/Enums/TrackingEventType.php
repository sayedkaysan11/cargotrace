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
    case Cancelled = 'CANCELLED';

    /** Event types an operator may record by hand (CREATED / CANCELLED / EXCEPTION are system-generated). */
    public static function manualValues(): array
    {
        return [
            self::Booked->value, self::PickedUp->value, self::DepartedPort->value,
            self::Transshipment->value, self::InTransit->value, self::ArrivedPort->value,
            self::CustomsClearance->value, self::Delivered->value,
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
