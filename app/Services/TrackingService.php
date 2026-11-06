<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Exceptions\BusinessRuleException;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only way operators move a shipment forward. Each call appends ONE tracking
 * event (history is never edited) and updates the shipment in the same transaction.
 */
class TrackingService
{
    /** Events that change the shipment status. */
    private const STATUS_FOR_EVENT = [
        'BOOKED' => ShipmentStatus::Booked,
        'PICKED_UP' => ShipmentStatus::PickedUp,
        'DEPARTED_PORT' => ShipmentStatus::InTransit,
        'DELIVERED' => ShipmentStatus::Delivered,
    ];

    /** Events that only make sense while the cargo is on its way (status stays IN_TRANSIT). */
    private const ONLY_WHILE_IN_TRANSIT = ['TRANSSHIPMENT', 'IN_TRANSIT', 'ARRIVED_PORT', 'CUSTOMS_CLEARANCE'];

    public function __construct(private readonly EtaService $eta)
    {
    }

    /**
     * @param array{event_type:string, location_id?:int|null, occurred_at?:string|null, description?:string|null,
     *              latitude?:float|null, longitude?:float|null, estimated_arrival_at?:string|null} $data
     */
    public function record(Shipment $shipment, User $actor, array $data): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $actor, $data) {
            // Lock the row so two operators cannot move the same shipment at the same moment.
            $shipment = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();

            $type = TrackingEventType::from($data['event_type']);
            $occurredAt = isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
            $from = $shipment->current_status;

            // Rule 1: finished shipments are closed.
            if ($from->isTerminal()) {
                throw new BusinessRuleException("Shipment is {$from->value}; no further events can be recorded.", 409);
            }

            // Rule 2: the timeline must stay chronological and cannot describe the future.
            if ($occurredAt->gt(now()->addMinutes(5))) {
                throw new BusinessRuleException('occurred_at cannot be in the future.');
            }
            $last = $shipment->trackingEvents()->reorder()->orderByDesc('occurred_at')->orderByDesc('id')->first();
            if ($last && $occurredAt->lt($last->occurred_at)) {
                throw new BusinessRuleException(
                    "occurred_at cannot be earlier than the latest recorded event ({$last->occurred_at->toIso8601String()})."
                );
            }

            // Rule 3: only legal status transitions.
            if (in_array($type->value, self::ONLY_WHILE_IN_TRANSIT, true)) {
                if ($from !== ShipmentStatus::InTransit) {
                    throw new BusinessRuleException("{$type->value} is only valid while the shipment is IN_TRANSIT (now {$from->value}).", 409);
                }
                $to = $from;
            } else {
                $to = self::STATUS_FOR_EVENT[$type->value];
                if (! $from->canTransitionTo($to)) {
                    throw new BusinessRuleException("Cannot move shipment from {$from->value} to {$to->value}.", 409);
                }
            }

            // Rule 4: ETA. Delivery fixes it; otherwise an explicit ETA wins; a late departure shifts it.
            $providedEta = isset($data['estimated_arrival_at']) ? Carbon::parse($data['estimated_arrival_at']) : null;
            if ($providedEta && $providedEta->lt($occurredAt)) {
                throw new BusinessRuleException('estimated_arrival_at cannot be earlier than the event time.');
            }

            $shipment->current_status = $to;
            if ($type === TrackingEventType::DepartedPort) {
                $shipment->actual_departure_at = $occurredAt;
            }

            if ($type === TrackingEventType::Delivered) {
                $shipment->actual_arrival_at = $occurredAt;
                $eta = $occurredAt;
            } elseif ($providedEta) {
                $eta = $providedEta;
            } elseif ($type === TrackingEventType::DepartedPort) {
                $eta = $this->eta->etaAfterDeparture($shipment, $occurredAt);
            } else {
                $eta = $shipment->estimated_arrival_at ?? $shipment->expected_arrival_at;
            }
            $this->eta->apply($shipment, $eta);
            $shipment->save();

            $location = isset($data['location_id']) ? Location::find($data['location_id']) : null;

            $event = TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'location_id' => $location?->id,
                'created_by' => $actor->id,
                'event_type' => $type,
                'status' => $to,
                'is_exception' => false,
                'occurred_at' => $occurredAt,
                'latitude' => $data['latitude'] ?? $location?->latitude,
                'longitude' => $data['longitude'] ?? $location?->longitude,
                'description' => $data['description']
                    ?? str_replace('_', ' ', $type->value) . ($location ? " at {$location->name}" : '') . '.',
            ]);

            if ($from !== $to) {
                AuditLogger::log('shipment.status_changed', $shipment,
                    ['status' => $from->value],
                    ['status' => $to->value, 'event_type' => $type->value]);
            } else {
                AuditLogger::log('shipment.tracking_recorded', $shipment, null, ['event_type' => $type->value]);
            }

            return $event->load('location');
        });
    }
}
