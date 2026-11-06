<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\CarrierRequest;
use App\Http\Resources\CarrierResource;
use App\Models\Carrier;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CarrierController extends Controller
{
    use ResolvesPerPage;

    /** GET /api/carriers?search=&is_active=&per_page= */
    public function index(Request $request): AnonymousResourceCollection
    {
        $carriers = Carrier::query()
            ->withCount('shipments')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CarrierResource::collection($carriers);
    }

    public function show(Carrier $carrier): CarrierResource
    {
        return new CarrierResource($carrier->loadCount('shipments'));
    }

    public function store(CarrierRequest $request): JsonResponse
    {
        $carrier = Carrier::create($request->validated());

        AuditLogger::log('carrier.created', $carrier, null, $carrier->only(['name', 'code', 'is_active']));

        return (new CarrierResource($carrier))->response()->setStatusCode(201);
    }

    public function update(CarrierRequest $request, Carrier $carrier): CarrierResource
    {
        $before = $carrier->getAttributes();

        $carrier->update($request->validated());

        $changes = collect($carrier->getChanges())->except('updated_at')->all();
        if ($changes !== []) {
            AuditLogger::log('carrier.updated', $carrier, array_intersect_key($before, $changes), $changes);
        }

        return new CarrierResource($carrier);
    }

    /** A carrier that already carries shipments cannot be deleted: deactivate it instead. */
    public function destroy(Carrier $carrier): JsonResponse|Response
    {
        if ($carrier->shipments()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Carrier already has shipments and cannot be deleted. Set is_active to false instead.',
            ], 409);
        }

        $snapshot = $carrier->only(['name', 'code']);
        $carrier->delete();

        AuditLogger::log('carrier.deleted', $carrier, $snapshot, null);

        return response()->noContent();
    }
}
