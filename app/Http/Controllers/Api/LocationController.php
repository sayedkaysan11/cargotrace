<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Models\Shipment;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    use ResolvesPerPage;

    /** GET /api/locations?search=&region=&type=&country_code=&per_page= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $locations = Location::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('city', 'like', $term));
            })
            ->when($request->filled('region'), fn ($q) => $q->where('region', strtoupper($request->input('region'))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('country_code'), fn ($q) => $q->where('country_code', strtoupper($request->input('country_code'))))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return LocationResource::collection($locations);
    }

    public function show(Location $location): LocationResource
    {
        return new LocationResource($location);
    }

    public function store(LocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());

        AuditLogger::log('location.created', $location, null, $location->only(['name', 'code', 'region']));

        return (new LocationResource($location))->response()->setStatusCode(201);
    }

    public function update(LocationRequest $request, Location $location): LocationResource
    {
        $before = $location->getAttributes();

        $location->update($request->validated());

        $changes = collect($location->getChanges())->except('updated_at')->all();
        if ($changes !== []) {
            AuditLogger::log('location.updated', $location, array_intersect_key($before, $changes), $changes);
        }

        return new LocationResource($location);
    }

    /** A location used as origin or destination of any shipment cannot be deleted. */
    public function destroy(Location $location): JsonResponse|Response
    {
        $inUse = Shipment::withTrashed()
            ->where(fn ($q) => $q->where('origin_location_id', $location->id)
                ->orWhere('destination_location_id', $location->id))
            ->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Location is used by existing shipments and cannot be deleted.',
            ], 409);
        }

        $snapshot = $location->only(['name', 'code']);
        $location->delete();

        AuditLogger::log('location.deleted', $location, $snapshot, null);

        return response()->noContent();
    }
}
