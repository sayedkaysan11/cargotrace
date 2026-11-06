<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackingEventRequest;
use App\Http\Resources\ShipmentResource;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrackingEventController extends Controller
{
    public function __construct(private readonly TrackingService $tracking)
    {
    }

    /** GET /api/shipments/{id}/tracking : the full timeline, oldest first. */
    public function index(Shipment $shipment): AnonymousResourceCollection
    {
        Gate::authorize('view', $shipment);

        return TrackingEventResource::collection($shipment->trackingEvents()->with('location')->get());
    }

    /** POST /api/shipments/{id}/tracking-events */
    public function store(TrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('addTrackingEvent', $shipment);

        $event = $this->tracking->record($shipment, $request->user(), $request->validated());

        return (new TrackingEventResource($event))
            ->additional(['shipment' => new ShipmentResource($shipment->refresh()->load(['carrier', 'origin', 'destination']))])
            ->response()
            ->setStatusCode(201);
    }
}
