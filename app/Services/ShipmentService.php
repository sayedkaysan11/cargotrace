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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Create / edit / cancel / delete shipments. Moving a shipment forward is TrackingService's job. */
class ShipmentService
{
    private const EDITABLE = ['carrier_id', 'cargo_type', 'expected_departure_at', 'expected_arrival_at'];

    public function __construct(private readonly EtaService $eta)
    {
    }

    public function create(array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($data, $actor) {
            $items = $data['items'];
            $expectedArrival = Carbon::parse($data['expected_arrival_at']);

            $shipment = Shipment::create([
                // temporary unique value; the real number is derived from the id below (race-free)
                'shipment_number' => 'TMP-' . Str::random(20),
                'scenario' => $data['scenario'] ?? config('cargotrace.default_scenario', 'A'),
                'customer_id' => $data['customer_id'],
                'carrier_id' => $data['carrier_id'],
                'origin_location_id' => $data['origin_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'created_by' => $actor->id,
                'cargo_type' => $data['cargo_type'],
                'total_weight_kg' => round(array_sum(array_column($items, 'weight_kg')), 2),
                'total_volume_cbm' => round(array_sum(array_column($items, 'volume_cbm')), 2),
                'current_status' => ShipmentStatus::Created,
                'expected_departure_at' => Carbon::parse($data['expected_departure_at']),
                'expected_arrival_at' => $expectedArrival,
                'estimated_arrival_at' => $expectedArrival, // no delay at the start
                'is_delayed' => false,
                'delay_minutes' => 0,
            ]);

            $shipment->update(['shipment_number' => sprintf('CT-%d-%06d', now()->year, $shipment->id)]);

            foreach ($items as $item) {
                $shipment->items()->create(Arr::only($item, ['description', 'hs_code', 'quantity', 'weight_kg', 'volume_cbm']));
            }

            $origin = Location::find($data['origin_location_id']);
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'location_id' => $origin->id,
                'created_by' => $actor->id,
                'event_type' => TrackingEventType::Created,
                'status' => ShipmentStatus::Created,
                'occurred_at' => now(),
                'latitude' => $origin->latitude,
                'longitude' => $origin->longitude,
                'description' => 'Shipment created.',
            ]);

            AuditLogger::log('shipment.created', $shipment, null, [
                'shipment_number' => $shipment->shipment_number,
                'scenario' => $shipment->scenario,
                'customer_id' => $shipment->customer_id,
                'carrier_id' => $shipment->carrier_id,
            ]);

            return $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items']);
        });
    }

    /** Planning data can only change before pickup. After that the promise to the customer is fixed. */
    public function update(Shipment $shipment, array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $data) {
            $shipment = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($shipment->current_status, [ShipmentStatus::Created, ShipmentStatus::Booked], true)) {
                throw new BusinessRuleException('Shipment can only be edited before pickup.', 409);
            }

            $before = AuditLogger::normalize($shipment->only(self::EDITABLE));
            $shipment->fill(Arr::only($data, self::EDITABLE));

            if ($shipment->expected_arrival_at->lte($shipment->expected_departure_at)) {
                throw new BusinessRuleException('expected_arrival_at must be after expected_departure_at.');
            }

            // Nothing has moved yet, so ETA simply follows the new promised arrival.
            $this->eta->apply($shipment, $shipment->expected_arrival_at);
            $shipment->save();

            $after = AuditLogger::normalize($shipment->only(self::EDITABLE));
            $changed = array_keys(array_diff_assoc($after, $before));
            if ($changed !== []) {
                AuditLogger::log('shipment.updated', $shipment, Arr::only($before, $changed), Arr::only($after, $changed));
            }

            return $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items']);
        });
    }

    public function cancel(Shipment $shipment, User $actor, ?string $reason = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $actor, $reason) {
            $shipment = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();
            $from = $shipment->current_status;

            if (! $from->canTransitionTo(ShipmentStatus::Cancelled)) {
                throw new BusinessRuleException("A {$from->value} shipment cannot be cancelled.", 409);
            }

            $shipment->update(['current_status' => ShipmentStatus::Cancelled]);

            $origin = $shipment->origin;
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'location_id' => $origin->id,
                'created_by' => $actor->id,
                'event_type' => TrackingEventType::Cancelled,
                'status' => ShipmentStatus::Cancelled,
                'occurred_at' => now(),
                'latitude' => $origin->latitude,
                'longitude' => $origin->longitude,
                'description' => $reason ? "Cancelled: {$reason}" : 'Shipment cancelled.',
            ]);

            AuditLogger::log('shipment.cancelled', $shipment,
                ['status' => $from->value], ['status' => ShipmentStatus::Cancelled->value, 'reason' => $reason]);

            return $shipment->load(['customer', 'carrier', 'origin', 'destination']);
        });
    }

    /** Soft delete. Only shipments that never left (CREATED) or were cancelled may be removed. */
    public function delete(Shipment $shipment, User $actor): void
    {
        if (! in_array($shipment->current_status, [ShipmentStatus::Created, ShipmentStatus::Cancelled], true)) {
            throw new BusinessRuleException('Only CREATED or CANCELLED shipments can be deleted.', 409);
        }

        $snapshot = ['shipment_number' => $shipment->shipment_number, 'status' => $shipment->current_status->value];
        $shipment->delete();

        AuditLogger::log('shipment.deleted', $shipment, $snapshot, null);
    }
}
