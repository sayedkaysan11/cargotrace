<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments)
    {
    }

    /** GET /api/shipments  (customers automatically only see their own) */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Shipment::class);

        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'scenario' => ['nullable', Rule::in(array_keys(config('cargotrace.groups')))],
            'is_delayed' => ['nullable', 'boolean'],
            'carrier_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'origin_location_id' => ['nullable', 'integer'],
            'destination_location_id' => ['nullable', 'integer'],
            'arrival_from' => ['nullable', 'date'],
            'arrival_to' => ['nullable', 'date', 'after_or_equal:arrival_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'expected_arrival_at', 'shipment_number', 'delay_minutes'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shipments = Shipment::query()
            ->visibleTo($request->user())
            ->with(['customer:id,name,company_name', 'carrier', 'origin', 'destination'])
            ->when($request->filled('search'), fn ($q) => $q->where('shipment_number', 'like', '%' . $f['search'] . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('current_status', $f['status']))
            ->when($request->filled('scenario'), fn ($q) => $q->where('scenario', $f['scenario']))
            ->when($request->filled('is_delayed'), fn ($q) => $q->where('is_delayed', $request->boolean('is_delayed')))
            ->when($request->filled('carrier_id'), fn ($q) => $q->where('carrier_id', $f['carrier_id']))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $f['customer_id']))
            ->when($request->filled('origin_location_id'), fn ($q) => $q->where('origin_location_id', $f['origin_location_id']))
            ->when($request->filled('destination_location_id'), fn ($q) => $q->where('destination_location_id', $f['destination_location_id']))
            ->when($request->filled('arrival_from'), fn ($q) => $q->whereDate('expected_arrival_at', '>=', $f['arrival_from']))
            ->when($request->filled('arrival_to'), fn ($q) => $q->whereDate('expected_arrival_at', '<=', $f['arrival_to']))
            ->orderBy($f['sort'] ?? 'created_at', $f['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($f['per_page'] ?? 15)
            ->withQueryString();

        return ShipmentResource::collection($shipments);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        Gate::authorize('view', $shipment);

        return new ShipmentResource(
            $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items', 'latestLocatedEvent'])
        );
    }

    public function store(ShipmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Shipment::class);

        $shipment = $this->shipments->create($request->validated(), $request->user());

        return (new ShipmentResource($shipment))->response()->setStatusCode(201);
    }

    public function update(ShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('update', $shipment);

        return new ShipmentResource($this->shipments->update($shipment, $request->validated(), $request->user()));
    }

    public function cancel(Request $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('update', $shipment);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return new ShipmentResource($this->shipments->cancel($shipment, $request->user(), $data['reason'] ?? null));
    }

    public function destroy(Request $request, Shipment $shipment): Response
    {
        Gate::authorize('delete', $shipment);

        $this->shipments->delete($shipment, $request->user());

        return response()->noContent();
    }
}
