<?php

namespace Database\Seeders;

use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Synthetic but internally consistent demo data for the three global
 * scenarios in config/cargotrace.php (A: SEA->Europe, B: Asia->North America,
 * C: SEA->Australia). Shipments rotate A, B, C, A, B, C ...
 *
 * Timeline per shipment (D = departure, T = final delivery):
 *   CREATED D-10d, BOOKED D-7d, PICKED_UP D-1d, DEPARTED_PORT D,
 *   waypoints at configured fractions of the voyage,
 *   ARRIVED_PORT T-30h, CUSTOMS_CLEARANCE T-12h, DELIVERED T.
 * Delay = hours added to T versus the expected arrival; the delay pool is a
 * multiple of the scenario's threshold. mt_srand makes the data repeatable.
 */
class ShipmentSeeder extends Seeder
{
    private const TOTAL = 90; // 30 per scenario

    /** Multipliers of the scenario delay threshold (0 = on time). */
    private const DELAY_FACTORS = [0, 0, 0, 0, 0.25, 0.5, 0.75, 1.2, 1.7, 2.5, 4, 6];

    private const CARGO = [
        ['Garments & Textiles', '6109', 0.4, 0.02],
        ['Consumer Electronics', '8517', 0.25, 0.03],
        ['Wooden Furniture', '9403', 0.9, 0.35],
        ['Natural Rubber Products', '4008', 1.2, 0.12],
        ['Machinery Parts', '8483', 2.5, 0.2],
        ['Coffee & Spices', '0901', 0.9, 0.1],
    ];

    public function run(): void
    {
        mt_srand(2026);

        $customers = User::where('role', UserRole::Customer->value)->get();
        $operators = User::where('role', UserRole::Operator->value)->get();
        $carriers = Carrier::all();
        $locations = Location::all()->keyBy('code');
        $scenarios = array_keys(config('cargotrace.groups'));

        for ($i = 1; $i <= self::TOTAL; $i++) {
            $scenario = $scenarios[($i - 1) % count($scenarios)];
            $this->seedShipment($i, $scenario, $customers, $operators, $carriers, $locations);
        }
    }

    private function seedShipment(
        int $i,
        string $scenario,
        Collection $customers,
        Collection $operators,
        Collection $carriers,
        Collection $locations,
    ): void {
        $cfg = config("cargotrace.groups.{$scenario}");
        $threshold = (int) $cfg['delay_threshold_hours'];

        $now = now();
        $status = $this->pickStatus();
        $transitDays = mt_rand($cfg['transit_days'][0], $cfg['transit_days'][1]);
        $origin = $this->pick($locations->filter(fn ($l) => in_array($l->region, $cfg['origin_regions'], true)));
        $dest = $this->pick($locations->filter(fn ($l) => $l->region === $cfg['destination_region']));
        $operator = $this->pick($operators);
        $waypoints = array_map(fn ($w) => [$locations[$w[0]], $w[1]], $cfg['waypoints']);

        $early = [ShipmentStatus::Created, ShipmentStatus::Booked, ShipmentStatus::PickedUp];
        $delayHours = in_array($status, $early, true)
            ? 0
            : (int) round($threshold * self::DELAY_FACTORS[mt_rand(0, count(self::DELAY_FACTORS) - 1)]);

        $departure = match ($status) {
            ShipmentStatus::Created => $now->copy()->addDays(12),
            ShipmentStatus::Booked => $now->copy()->addDays(5),
            ShipmentStatus::PickedUp => $now->copy()->addDay(),
            ShipmentStatus::InTransit => $now->copy()->subHours(mt_rand(
                (int) ($transitDays * 0.15 * 24),
                // keep "now" before the ARRIVED_PORT event (T-30h) even on short routes
                min((int) ($transitDays * 0.85 * 24), $transitDays * 24 + $delayHours - 40)
            )),
            default => $now->copy()->subHours($transitDays * 24 + $delayHours + mt_rand(48, 600)),
        };

        $expectedArrival = $departure->copy()->addDays($transitDays);
        $finalAt = $expectedArrival->copy()->addHours($delayHours); // T
        $span = $finalAt->getTimestamp() - $departure->getTimestamp();
        $at = fn (float $fraction) => Carbon::createFromTimestamp($departure->getTimestamp() + (int) ($span * $fraction));

        // ---- timeline stages: [type, location, status, when, text]
        $stages = [
            [TrackingEventType::Created, $origin, ShipmentStatus::Created, $departure->copy()->subDays(10), 'Shipment created.'],
            [TrackingEventType::Booked, $origin, ShipmentStatus::Booked, $departure->copy()->subDays(7), 'Booking confirmed with carrier.'],
            [TrackingEventType::PickedUp, $origin, ShipmentStatus::PickedUp, $departure->copy()->subDay(), 'Cargo picked up from shipper.'],
            [TrackingEventType::DepartedPort, $origin, ShipmentStatus::InTransit, $departure->copy(), "Vessel departed {$origin->name}."],
        ];
        $waypointTimes = [];
        foreach ($waypoints as $k => [$wp, $fraction]) {
            $when = $at($fraction);
            $waypointTimes[] = [$wp, $when];
            $stages[] = [
                $k === 0 ? TrackingEventType::Transshipment : TrackingEventType::InTransit,
                $wp, ShipmentStatus::InTransit, $when, "Passing {$wp->name}.",
            ];
        }
        $stages[] = [TrackingEventType::ArrivedPort, $dest, ShipmentStatus::InTransit, $finalAt->copy()->subHours(30), "Arrived at {$dest->name}."];
        $stages[] = [TrackingEventType::CustomsClearance, $dest, ShipmentStatus::InTransit, $finalAt->copy()->subHours(12), 'Customs clearance completed.'];
        $stages[] = [TrackingEventType::Delivered, $dest, ShipmentStatus::Delivered, $finalAt->copy(), 'Delivered to consignee.'];

        if ($status === ShipmentStatus::Created) {
            $stages = [array_replace($stages[0], [3 => $now->copy()->subDays(2)])];
        } elseif ($status === ShipmentStatus::Booked) {
            $stages = [
                array_replace($stages[0], [3 => $now->copy()->subDays(4)]),
                array_replace($stages[1], [3 => $now->copy()->subDay()]),
            ];
        } elseif ($status === ShipmentStatus::PickedUp) {
            $stages = [
                array_replace($stages[0], [3 => $now->copy()->subDays(9)]),
                array_replace($stages[1], [3 => $now->copy()->subDays(6)]),
                array_replace($stages[2], [3 => $now->copy()->subHours(3)]),
            ];
        } else {
            $stages = array_values(array_filter($stages, fn ($s) => $s[3]->lte($now)));
        }

        // ---- items
        $cargo = self::CARGO[mt_rand(0, count(self::CARGO) - 1)];
        $items = [];
        $totalWeight = 0.0;
        $totalVolume = 0.0;
        for ($n = 0, $count = mt_rand(1, 3); $n < $count; $n++) {
            $qty = mt_rand(50, 800);
            $weight = round($qty * $cargo[2] * (mt_rand(90, 110) / 100), 2);
            $volume = round($qty * $cargo[3] * (mt_rand(90, 110) / 100), 2);
            $items[] = [
                'description' => $cargo[0] . ' - lot ' . ($n + 1),
                'hs_code' => $cargo[1],
                'quantity' => $qty,
                'weight_kg' => $weight,
                'volume_cbm' => $volume,
            ];
            $totalWeight += $weight;
            $totalVolume += $volume;
        }

        $createdAt = $stages[0][3];
        $isDelayed = $delayHours > 0;

        $shipment = Shipment::create([
            'shipment_number' => sprintf('CT-2026-%06d', $i),
            'scenario' => $scenario,
            'customer_id' => $this->pick($customers)->id,
            'carrier_id' => $this->pick($carriers)->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'created_by' => $operator->id,
            'cargo_type' => $cargo[0],
            'total_weight_kg' => round($totalWeight, 2),
            'total_volume_cbm' => round($totalVolume, 2),
            'current_status' => $status,
            'expected_departure_at' => $departure,
            'expected_arrival_at' => $expectedArrival,
            'estimated_arrival_at' => $finalAt,
            'actual_departure_at' => in_array($status, [ShipmentStatus::InTransit, ShipmentStatus::Delivered], true) ? $departure : null,
            'actual_arrival_at' => $status === ShipmentStatus::Delivered ? $finalAt : null,
            'is_delayed' => $isDelayed,
            'delay_minutes' => $delayHours * 60,
            'delay_severity' => $isDelayed ? ExceptionSeverity::fromDelayHours($delayHours, $scenario) : null,
        ]);
        $shipment->forceFill(['created_at' => $createdAt, 'updated_at' => $now])->saveQuietly();

        foreach ($items as $item) {
            $shipment->items()->create($item);
        }

        $lastLocated = null;
        foreach ($stages as [$type, $location, $evStatus, $when, $text]) {
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'location_id' => $location->id,
                'created_by' => $operator->id,
                'event_type' => $type,
                'status' => $evStatus,
                'is_exception' => false,
                'occurred_at' => $when,
                'latitude' => round($location->latitude + mt_rand(-40, 40) / 1000, 7),
                'longitude' => round($location->longitude + mt_rand(-40, 40) / 1000, 7),
                'description' => $text,
            ]);
            $lastLocated = $location;
        }

        $this->seedException($shipment, $scenario, $cfg, $status, $delayHours, $threshold, $finalAt, $now, $dest, $lastLocated, $waypointTimes, $operator);

        AuditLog::create([
            'user_id' => $operator->id,
            'action' => 'shipment.created',
            'auditable_type' => Shipment::class,
            'auditable_id' => $shipment->id,
            'new_values' => ['shipment_number' => $shipment->shipment_number, 'scenario' => $scenario],
            'ip_address' => '127.0.0.1',
            'created_at' => $createdAt,
        ]);
    }

    private function seedException(
        Shipment $shipment,
        string $scenario,
        array $cfg,
        ShipmentStatus $status,
        int $delayHours,
        int $threshold,
        Carbon $finalAt,
        Carbon $now,
        Location $dest,
        ?Location $lastLocated,
        array $waypointTimes,
        User $operator,
    ): void {
        $primary = ExceptionType::from($cfg['primary_exception']);
        $atDestinationTypes = [ExceptionType::CustomsHold, ExceptionType::DocumentationIssue];
        $significant = $delayHours >= (int) round($threshold * 0.75);

        $type = null;
        $location = $lastLocated;
        $detectedAt = null;
        $resolvedAt = null;
        $exStatus = ExceptionStatus::Open;
        $severity = $delayHours > 0
            ? ExceptionSeverity::fromDelayHours($delayHours, $scenario)
            : ExceptionSeverity::Medium;

        if ($status === ShipmentStatus::Delivered && $significant) {
            // 70% the scenario's primary exception, otherwise a secondary one.
            $type = mt_rand(1, 100) <= 70
                ? $primary
                : ($primary === ExceptionType::DocumentationIssue ? ExceptionType::PortCongestion : ExceptionType::DocumentationIssue);
            $exStatus = ExceptionStatus::Resolved;

            if (in_array($type, $atDestinationTypes, true)) {
                $location = $dest;
                $detectedAt = $finalAt->copy()->subHours(28);
                $resolvedAt = $finalAt->copy()->subHours(13);
            } else {
                [$wp, $wpTime] = $waypointTimes[0];
                $location = $wp;
                $detectedAt = $wpTime->copy()->addHours(2);
                $resolvedAt = $wpTime->copy()->addHours(2 + mt_rand(6, 30));
            }
        } elseif ($status === ShipmentStatus::InTransit && $significant) {
            // Customs hold only makes sense at the destination, so in transit use an en-route type.
            $type = in_array($primary, $atDestinationTypes, true) && $primary === ExceptionType::CustomsHold
                ? (mt_rand(0, 1) ? ExceptionType::WeatherDelay : ExceptionType::PortCongestion)
                : (mt_rand(1, 100) <= 60 ? $primary : ExceptionType::PortCongestion);
            $detectedAt = $now->copy()->subHours(mt_rand(2, 60));
            $exStatus = mt_rand(0, 1) ? ExceptionStatus::Open : ExceptionStatus::Investigating;
        } elseif ($status === ShipmentStatus::InTransit && mt_rand(1, 100) <= 8) {
            $type = mt_rand(0, 1) ? ExceptionType::Damaged : ExceptionType::Misrouted;
            $detectedAt = $now->copy()->subHours(mt_rand(2, 36));
        }

        if ($type === null || $location === null) {
            return;
        }

        $event = TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $location->id,
            'created_by' => $operator->id,
            'event_type' => TrackingEventType::Exception,
            'status' => $status === ShipmentStatus::Delivered ? ShipmentStatus::InTransit : $status,
            'is_exception' => true,
            'occurred_at' => $detectedAt,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'description' => str_replace('_', ' ', $type->value) . ' detected.',
        ]);

        ShipmentException::create([
            'shipment_id' => $shipment->id,
            'tracking_event_id' => $event->id,
            'reported_by' => $operator->id,
            'resolved_by' => $resolvedAt ? $operator->id : null,
            'type' => $type,
            'severity' => $severity,
            'status' => $exStatus,
            'description' => 'Seeded demo exception: ' . $type->value,
            'detected_at' => $detectedAt,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private function pickStatus(): ShipmentStatus
    {
        $r = mt_rand(1, 100);

        return match (true) {
            $r <= 40 => ShipmentStatus::Delivered,
            $r <= 75 => ShipmentStatus::InTransit,
            $r <= 80 => ShipmentStatus::PickedUp,
            $r <= 90 => ShipmentStatus::Booked,
            default => ShipmentStatus::Created,
        };
    }

    private function pick(Collection $items): mixed
    {
        return $items->values()[mt_rand(0, $items->count() - 1)];
    }
}
