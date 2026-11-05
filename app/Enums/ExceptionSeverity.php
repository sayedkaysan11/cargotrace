<?php

namespace App\Enums;

enum ExceptionSeverity: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';

    /** Deterministic delay rule; bands come from the scenario's delay threshold. */
    public static function fromDelayHours(float $hours, string $scenario = 'A'): self
    {
        $threshold = (float) config(
            "cargotrace.groups.{$scenario}.delay_threshold_hours",
            config('cargotrace.groups.A.delay_threshold_hours', 12)
        );

        return match (true) {
            $hours <= $threshold / 2 => self::Low,
            $hours <= $threshold * 2 => self::Medium,
            default => self::High,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
