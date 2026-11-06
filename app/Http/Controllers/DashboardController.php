<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Models\Carrier;
use App\Models\ExternalNewsArticle;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Services\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function currencyRates(CurrencyRateService $rates): JsonResponse
    {
        return response()->json($rates->rates())
            ->header('Cache-Control', 'private, max-age=900');
    }

    public function mapData(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'in_transit', 'delivered', 'delayed', 'exception', 'other'])],
        ]);
        $user = $request->user();
        $statusFilter = $filters['status'] ?? 'all';
        $openExceptions = fn ($query) => $query->where('status', 'OPEN');
        $shipmentsQuery = Shipment::visibleTo($user)
            ->with([
                'origin',
                'destination',
                'latestLocatedEvent.location',
                'exceptions' => $openExceptions,
            ])
            ->when($statusFilter === 'exception', fn ($query) => $query->whereHas('exceptions', $openExceptions))
            ->when($statusFilter === 'delayed', fn ($query) => $query->where('is_delayed', true)->whereDoesntHave('exceptions', $openExceptions))
            ->when($statusFilter === 'delivered', fn ($query) => $query
                ->where('current_status', ShipmentStatus::Delivered)
                ->where('is_delayed', false)
                ->whereDoesntHave('exceptions', $openExceptions))
            ->when($statusFilter === 'other', fn ($query) => $query
                ->whereIn('current_status', [ShipmentStatus::Created, ShipmentStatus::Booked, ShipmentStatus::Cancelled])
                ->where('is_delayed', false)
                ->whereDoesntHave('exceptions', $openExceptions))
            ->when($statusFilter === 'in_transit', fn ($query) => $query
                ->whereNotIn('current_status', [ShipmentStatus::Delivered, ShipmentStatus::Cancelled, ShipmentStatus::Created, ShipmentStatus::Booked])
                ->where('is_delayed', false)
                ->whereDoesntHave('exceptions', $openExceptions))
            ->latest('updated_at');
        $shipments = $shipmentsQuery->limit(501)->get();
        $truncated = $shipments->count() > 500;

        $features = $shipments->take(500)->map(function (Shipment $shipment): array {
            $positionEvent = $shipment->latestLocatedEvent;
            $hasPosition = $this->hasValidCoordinates($positionEvent?->latitude, $positionEvent?->longitude);
            $statusKey = $shipment->exceptions->isNotEmpty()
                ? 'exception'
                : ($shipment->is_delayed
                    ? 'delayed'
                    : match ($shipment->current_status) {
                        ShipmentStatus::Delivered => 'delivered',
                        ShipmentStatus::Cancelled, ShipmentStatus::Created, ShipmentStatus::Booked => 'other',
                        default => 'in_transit',
                    });
            $eta = $shipment->estimated_arrival_at ?? $shipment->expected_arrival_at;

            return [
                'id' => $shipment->id,
                'tracking_number' => $shipment->shipment_number,
                'status' => $shipment->current_status->value,
                'status_label' => $shipment->is_delayed
                    ? 'Delayed'
                    : ($statusKey === 'exception' ? 'Exception' : str($shipment->current_status->value)->replace('_', ' ')->title()),
                'status_key' => $statusKey,
                'url' => route('tracking.show', $shipment),
                'origin' => $this->mapLocation($shipment->origin),
                'destination' => $this->mapLocation($shipment->destination),
                'position' => $hasPosition ? [
                    'latitude' => (float) $positionEvent->latitude,
                    'longitude' => (float) $positionEvent->longitude,
                    'location' => $positionEvent->location?->city ?? $positionEvent->location?->name ?? 'Recorded coordinates',
                    'updated_at' => $positionEvent->occurred_at?->toIso8601String(),
                ] : null,
                'eta' => $eta?->timezone('UTC')->toIso8601String(),
                'shipment_updated_at' => $shipment->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $features,
            'meta' => [
                'fetched_at' => now()->toIso8601String(),
                'shipment_count' => $features->count(),
                'truncated' => $truncated,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    private function mapLocation(?Location $location): ?array
    {
        if (! $location) {
            return null;
        }

        $name = implode(', ', array_filter([$location->city, $location->country]));

        return [
            'name' => $name !== '' ? $name : $location->name,
            'latitude' => $this->hasValidCoordinates($location->latitude, $location->longitude) ? (float) $location->latitude : null,
            'longitude' => $this->hasValidCoordinates($location->latitude, $location->longitude) ? (float) $location->longitude : null,
        ];
    }

    private function hasValidCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180;
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $visibleShipments = Shipment::visibleTo($user);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'sort' => ['nullable', Rule::in(['updated_desc', 'updated_asc', 'eta_asc', 'eta_desc'])],
            'period' => ['nullable', Rule::in(['7d', '30d', '90d'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $shipmentStatus = $filters['status'] ?? '';
        $shipmentSort = $filters['sort'] ?? 'updated_desc';
        $period = in_array($request->query('period'), ['7d', '30d', '90d'], true)
            ? $request->query('period')
            : '7d';
        $rangeDays = ['7d' => 7, '30d' => 30, '90d' => 90][$period];
        $bucketDays = ['7d' => 1, '30d' => 5, '90d' => 15][$period];
        $bucketCount = (int) ceil($rangeDays / $bucketDays);
        $rangeStart = now()->startOfDay()->subDays($rangeDays - 1);

        $shipments = (clone $visibleShipments)
            ->with([
                'origin',
                'destination',
                'carrier',
                'latestLocatedEvent.location',
                'exceptions' => fn ($query) => $query->where('status', 'OPEN'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('shipment_number', 'like', "%{$search}%")
                        ->orWhereHas('origin', fn ($location) => $location->where('city', 'like', "%{$search}%"))
                        ->orWhereHas('destination', fn ($location) => $location->where('city', 'like', "%{$search}%"));
                });
            })
            ->when($shipmentStatus !== '', fn ($query) => $query->where('current_status', $shipmentStatus))
            ->when($shipmentSort === 'updated_asc', fn ($query) => $query->orderBy('updated_at')->orderBy('id'))
            ->when($shipmentSort === 'eta_asc', fn ($query) => $query->orderByRaw('COALESCE(estimated_arrival_at, expected_arrival_at) ASC')->orderBy('id'))
            ->when($shipmentSort === 'eta_desc', fn ($query) => $query->orderByRaw('COALESCE(estimated_arrival_at, expected_arrival_at) DESC')->orderBy('id'))
            ->when($shipmentSort === 'updated_desc', fn ($query) => $query->orderByDesc('updated_at')->orderByDesc('id'))
            ->paginate(5)
            ->withQueryString();

        $recentEvents = TrackingEvent::query()
            ->whereHas('shipment', fn ($query) => $query->visibleTo($user))
            ->with(['shipment', 'location'])
            ->latest('occurred_at')
            ->limit(5)
            ->get();

        $timelineEvents = TrackingEvent::query()
            ->join('shipments', 'tracking_events.shipment_id', '=', 'shipments.id')
            ->whereIn('tracking_events.shipment_id', (clone $visibleShipments)->select('shipments.id'))
            ->where('occurred_at', '>=', $rangeStart)
            ->selectRaw('DATE(tracking_events.occurred_at) as event_date, tracking_events.event_type, tracking_events.is_exception, shipments.is_delayed as shipment_is_delayed, COUNT(*) as aggregate')
            ->groupByRaw('DATE(tracking_events.occurred_at), tracking_events.event_type, tracking_events.is_exception, shipments.is_delayed')
            ->get();
        $chartSeries = [
            'Created' => array_fill(0, $bucketCount, 0),
            'In Transit' => array_fill(0, $bucketCount, 0),
            'Delivered' => array_fill(0, $bucketCount, 0),
            'Exceptions' => array_fill(0, $bucketCount, 0),
        ];
        $onTimeDeliveryBuckets = array_fill(0, $bucketCount, 0);
        $deliveredBuckets = array_fill(0, $bucketCount, 0);
        $chartLabels = [];

        for ($index = 0; $index < $bucketCount; $index++) {
            $bucketStart = $rangeStart->copy()->addDays($index * $bucketDays);
            $chartLabels[] = $bucketStart->format($rangeDays <= 7 ? 'D' : 'M j');
        }

        foreach ($timelineEvents as $event) {
            $eventDate = Carbon::parse($event->event_date)->startOfDay();
            $dayOffset = max(0, $rangeStart->diffInDays($eventDate));
            $bucketIndex = min($bucketCount - 1, intdiv($dayOffset, $bucketDays));
            $eventType = $event->event_type;
            $eventCount = (int) $event->aggregate;

            if ($eventType === TrackingEventType::Created) {
                $chartSeries['Created'][$bucketIndex] += $eventCount;
            } elseif (in_array($eventType, [TrackingEventType::PickedUp, TrackingEventType::InTransit, TrackingEventType::DepartedPort, TrackingEventType::Transshipment], true)) {
                $chartSeries['In Transit'][$bucketIndex] += $eventCount;
            } elseif ($eventType === TrackingEventType::Delivered) {
                $chartSeries['Delivered'][$bucketIndex] += $eventCount;
                $deliveredBuckets[$bucketIndex] += $eventCount;
                if (! $event->shipment_is_delayed) {
                    $onTimeDeliveryBuckets[$bucketIndex] += $eventCount;
                }
            }

            if ($event->is_exception) {
                $chartSeries['Exceptions'][$bucketIndex] += $eventCount;
            }
        }

        $periodShipments = (clone $visibleShipments)->where('shipments.created_at', '>=', $rangeStart);
        $regionDistribution = (clone $periodShipments)
            ->join('locations as destinations', 'shipments.destination_location_id', '=', 'destinations.id')
            ->selectRaw("COALESCE(NULLIF(destinations.region, ''), 'Other') as label, COUNT(*) as value")
            ->groupBy('label')
            ->orderByDesc('value')
            ->limit(6)
            ->get();
        $carrierDistribution = (clone $periodShipments)
            ->join('carriers', 'shipments.carrier_id', '=', 'carriers.id')
            ->selectRaw('carriers.name as label, COUNT(*) as value')
            ->groupBy('carriers.name')
            ->orderByDesc('value')
            ->limit(6)
            ->get();
        $periodShipmentCount = (clone $periodShipments)->count();

        $deliveredCount = (clone $visibleShipments)
            ->where('current_status', ShipmentStatus::Delivered->value)
            ->count();
        $onTimeDeliveredCount = (clone $visibleShipments)
            ->where('current_status', ShipmentStatus::Delivered->value)
            ->where('is_delayed', false)
            ->count();
        $exceptionShipmentCount = ShipmentException::query()
            ->whereHas('shipment', fn ($query) => $query->visibleTo($user))
            ->distinct('shipment_id')
            ->count('shipment_id');
        $carrierStats = Carrier::query()
            ->whereHas('shipments', fn ($query) => $query->visibleTo($user))
            ->withCount(['shipments as visible_shipments_count' => fn ($query) => $query->visibleTo($user)])
            ->orderByDesc('visible_shipments_count')
            ->limit(4)
            ->get();

        return view('dashboard', [
            'user' => $user,
            'shipmentCount' => (clone $visibleShipments)->count(),
            'activeCount' => (clone $visibleShipments)
                ->whereNotIn('current_status', [ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value])
                ->count(),
            'inTransitCount' => (clone $visibleShipments)
                ->where('current_status', ShipmentStatus::InTransit->value)
                ->count(),
            'deliveredCount' => $deliveredCount,
            'onTimeDeliveredCount' => $onTimeDeliveredCount,
            'onTimeDeliveryRate' => $deliveredCount > 0
                ? round($onTimeDeliveredCount / $deliveredCount * 100, 1)
                : null,
            'carrierStats' => $carrierStats,
            'delayedCount' => (clone $visibleShipments)->where('is_delayed', true)->count(),
            'totalExceptionCount' => ShipmentException::query()
                ->whereHas('shipment', fn ($query) => $query->visibleTo($user))
                ->count(),
            'exceptionShipmentCount' => $exceptionShipmentCount,
            'openExceptionCount' => ShipmentException::query()
                ->where('status', 'OPEN')
                ->whereHas('shipment', fn ($query) => $query->visibleTo($user))
                ->count(),
            'shipments' => $shipments,
            'recentEvents' => $recentEvents,
            'search' => $search,
            'shipmentStatus' => $shipmentStatus,
            'shipmentSort' => $shipmentSort,
            'shipmentStatuses' => ShipmentStatus::cases(),
            'period' => $period,
            'periodShipmentCount' => $periodShipmentCount,
            'chartLabels' => $chartLabels,
            'chartSeries' => $chartSeries,
            'analyticsData' => [
                'labels' => $chartLabels,
                'series' => $chartSeries,
                'region' => $regionDistribution,
                'carrier' => $carrierDistribution,
            ],
            'chartSparklines' => [
                'total' => $this->sparklinePoints($chartSeries['Created']),
                'transit' => $this->sparklinePoints($chartSeries['In Transit']),
                'delivered' => $this->sparklinePoints($chartSeries['Delivered']),
                'exceptions' => $this->sparklinePoints($chartSeries['Exceptions']),
                'onTime' => $this->sparklinePoints(array_map(
                    fn (int $delivered, int $onTime): int => $delivered > 0 ? (int) round($onTime / $delivered * 100) : 0,
                    $deliveredBuckets,
                    $onTimeDeliveryBuckets,
                )),
            ],
            'regionDistribution' => $regionDistribution,
            'carrierDistribution' => $carrierDistribution,
            'latestNews' => ExternalNewsArticle::query()->latestPublished()->limit(4)->get(),
            'latestNewsId' => ExternalNewsArticle::query()->max('id') ?? 0,
            'newsLastUpdatedAt' => cache()->get('news_last_sync_at'),
            'newsIsLive' => cache()->get('news_last_sync_sources', []) !== []
                && cache()->get('news_last_sync_at') !== null
                && Carbon::parse(cache()->get('news_last_sync_at'))->greaterThan(now()->subMinutes(5)),
        ]);
    }

    private function sparklinePoints(array $values): string
    {
        $maximum = max(1, ...$values);
        $lastIndex = max(1, count($values) - 1);

        return collect($values)
            ->map(fn (int $value, int $index) => round($index / $lastIndex * 100, 1).','.round(27 - ($value / $maximum * 22), 1))
            ->implode(' ');
    }
}
