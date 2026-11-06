<?php

namespace App\Services;

use App\Enums\ExceptionSeverity;
use App\Models\Shipment;
use Carbon\Carbon;

/**
 * ETA / delay rule (single source of truth):
 *   delay      = estimated arrival - expected arrival (whole minutes, never negative)
 *   is_delayed = delay > 0
 *   severity   = LOW / MEDIUM / HIGH, bands come from the scenario's threshold
 *                (see ExceptionSeverity::fromDelayHours and config/cargotrace.php)
 */
class EtaService
{
    /** Pure calculation, no database access. Easy to unit-test. */
    public function evaluate(Carbon $expectedArrival, Carbon $estimatedArrival, string $scenario): array
    {
        $seconds = max(0, $estimatedArrival->getTimestamp() - $expectedArrival->getTimestamp());
        $minutes = intdiv($seconds, 60);
        $delayed = $minutes > 0;

        return [
            'is_delayed' => $delayed,
            'delay_minutes' => $minutes,
            'delay_severity' => $delayed ? ExceptionSeverity::fromDelayHours($minutes / 60, $scenario) : null,
        ];
    }

    /** Set the ETA on the shipment and refresh its cached delay fields (does not save). */
    public function apply(Shipment $shipment, Carbon $estimatedArrival): void
    {
        $shipment->estimated_arrival_at = $estimatedArrival;
        $shipment->fill($this->evaluate($shipment->expected_arrival_at, $estimatedArrival, $shipment->scenario));
    }

    /** A late departure pushes the ETA back by the same amount (unless an operator gives an explicit ETA). */
    public function etaAfterDeparture(Shipment $shipment, Carbon $actualDeparture): Carbon
    {
        $lateness = max(0, $actualDeparture->getTimestamp() - $shipment->expected_departure_at->getTimestamp());

        return $shipment->expected_arrival_at->copy()->addSeconds($lateness);
    }
}
