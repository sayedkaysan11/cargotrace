<?php

namespace App\Http\Controllers;

use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Enums\UserRole;
use App\Models\Carrier;
use App\Models\ExternalNewsArticle;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\ShipmentService;
use App\Services\TrackingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    use AuthorizesRequests;

    public function shipments(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
            'is_delayed' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $shipments = Shipment::visibleTo($request->user())
            ->with(['customer', 'carrier', 'origin', 'destination'])
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = '%'.addcslashes($search, '\\%_').'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('shipment_number', 'like', $term)
                        ->orWhereHas('customer', function ($query) use ($term): void {
                            $query->where('name', 'like', $term)
                                ->orWhere('company_name', 'like', $term);
                        })
                        ->orWhereHas('carrier', fn ($query) => $query->where('name', 'like', $term));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('current_status', $status))
            ->when($filters['carrier_id'] ?? null, fn ($query, $carrierId) => $query->where('carrier_id', $carrierId))
            ->when(isset($filters['is_delayed']), fn ($query) => $query->where('is_delayed', $filters['is_delayed'] === '1'))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('portal.shipments', [
            'shipments' => $shipments,
            'statuses' => ShipmentStatus::cases(),
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function showShipment(Shipment $shipment): View
    {
        $this->authorize('view', $shipment);

        $shipment->load([
            'customer',
            'carrier',
            'origin',
            'destination',
            'items',
            'trackingEvents.location',
            'exceptions',
            'latestLocatedEvent.location',
        ]);

        return view('portal.tracking', [
            'shipment' => $shipment,
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('city')->get(),
            'manualEventTypes' => TrackingEventType::manualValues(),
        ]);
    }

    public function createShipment(): View
    {
        $this->authorize('create', Shipment::class);

        return view('portal.shipment-create', [
            'customers' => User::query()->where('role', UserRole::Customer->value)->where('is_active', true)->orderBy('name')->get(),
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('city')->get(),
        ]);
    }

    public function storeShipment(Request $request, ShipmentService $service): RedirectResponse
    {
        $this->authorize('create', Shipment::class);
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Customer->value)->where('is_active', true)],
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'origin_location_id' => ['required', 'integer', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id'],
            'scenario' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'cargo_type' => ['required', 'string', 'max:60'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hs_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required', 'numeric', 'min:0'],
            'items.*.volume_cbm' => ['required', 'numeric', 'min:0'],
        ]);

        $shipment = $service->create($validated, $request->user());

        return redirect()->route('shipments.show', $shipment)->with('status', 'Shipment berhasil dibuat.');
    }

    public function updateShipment(Request $request, Shipment $shipment, ShipmentService $service): RedirectResponse
    {
        $this->authorize('update', $shipment);
        $validated = $request->validate([
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'cargo_type' => ['required', 'string', 'max:60'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
        ]);

        $service->update($shipment, $validated, $request->user());

        return back()->with('status', 'Informasi shipment berhasil diperbarui.');
    }

    public function storeTrackingEvent(Request $request, Shipment $shipment, TrackingService $service): RedirectResponse
    {
        $this->authorize('addTrackingEvent', $shipment);
        $validated = $request->validate([
            'event_type' => ['required', Rule::in(TrackingEventType::manualValues())],
            'location_id' => ['required_unless:event_type,BOOKED', 'nullable', 'integer', 'exists:locations,id'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $service->record($shipment, $request->user(), $validated);

        return back()->with('status', 'Pembaruan tracking berhasil dicatat.');
    }

    public function map(Request $request): View
    {
        $shipments = Shipment::visibleTo($request->user())
            ->with(['origin', 'destination', 'latestLocatedEvent.location'])
            ->whereNotIn('current_status', [ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        $selectedId = filter_var($request->query('selected'), FILTER_VALIDATE_INT);
        $selectedShipment = $shipments->firstWhere('id', $selectedId) ?? $shipments->first();

        return view('portal.map', [
            'shipments' => $shipments,
            'selectedShipment' => $selectedShipment,
            'locations' => Location::query()->whereIn('id', $shipments->flatMap(
                static function ($shipment): array {
                    /** @var Shipment $shipment */
                    return [
                        $shipment->origin_location_id,
                        $shipment->destination_location_id,
                        $shipment->latestLocatedEvent?->location_id,
                    ];
                }
            )->unique())->orderBy('name')->get(),
        ]);
    }

    public function exceptions(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(ExceptionStatus::values())],
            'severity' => ['nullable', Rule::in(ExceptionSeverity::values())],
        ]);

        $exceptions = ShipmentException::query()
            ->whereHas('shipment', fn ($query) => $query->visibleTo($request->user()))
            ->with(['shipment.origin', 'shipment.destination', 'reporter'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->latest('detected_at')
            ->paginate(12)
            ->withQueryString();

        return view('portal.exceptions', [
            'exceptions' => $exceptions,
            'statuses' => ExceptionStatus::cases(),
            'severities' => ExceptionSeverity::cases(),
            'filters' => $filters,
        ]);
    }

    public function reports(Request $request): View
    {
        $base = Shipment::visibleTo($request->user());
        $total = (clone $base)->count();
        $statuses = (clone $base)
            ->selectRaw('current_status, COUNT(*) as aggregate')
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status');

        $carrierStats = Carrier::query()
            ->whereHas('shipments', fn ($query) => $query->visibleTo($request->user()))
            ->withCount(['shipments as visible_shipments_count' => fn ($query) => $query->visibleTo($request->user())])
            ->orderByDesc('visible_shipments_count')
            ->limit(8)
            ->get();

        $from = now()->startOfMonth()->subMonths(5);
        $monthlyCounts = (clone $base)
            ->where('created_at', '>=', $from)
            ->get(['created_at'])
            ->groupBy(static function ($shipment): string {
                /** @var Shipment $shipment */
                return $shipment->created_at->format('Y-m');
            })
            ->map(static function ($shipments): int {
                /** @var Collection<int, Shipment> $shipments */
                return $shipments->count();
            });
        $months = collect(range(0, 5))->map(function (int $offset) use ($from, $monthlyCounts): array {
            $month = $from->copy()->addMonths($offset);

            return ['label' => $month->format('M'), 'count' => $monthlyCounts->get($month->format('Y-m'), 0)];
        });

        return view('portal.reports', [
            'total' => $total,
            'statuses' => $statuses,
            'statusCases' => ShipmentStatus::cases(),
            'carrierStats' => $carrierStats,
            'months' => $months,
            'maxMonthlyCount' => max(1, (int) $months->max('count')),
            'delayedCount' => (clone $base)->where('is_delayed', true)->count(),
            'exceptionsCount' => ShipmentException::query()
                ->whereHas('shipment', fn ($query) => $query->visibleTo($request->user()))
                ->count(),
        ]);
    }

    public function news(Request $request): View
    {
        $articles = ExternalNewsArticle::query()
            ->latestPublished()
            ->cursorPaginate(config('news.page_size'));

        $events = TrackingEvent::query()
            ->whereHas('shipment', fn ($query) => $query->visibleTo($request->user()))
            ->with(['shipment.origin', 'shipment.destination', 'location'])
            ->latest('occurred_at')
            ->paginate(15, ['*'], 'activity_page');

        $lastUpdatedAt = cache()->get('news_last_sync_at');
        $successfulFeeds = cache()->get('news_last_sync_sources', []);

        return view('portal.news', [
            'articles' => $articles,
            'events' => $events,
            'lastUpdatedAt' => $lastUpdatedAt,
            'successfulFeeds' => $successfulFeeds,
            'failedFeeds' => cache()->get('news_last_sync_failures', []),
            'latestArticleId' => ExternalNewsArticle::query()->max('id') ?? 0,
            'isLive' => $successfulFeeds !== []
                && $lastUpdatedAt !== null
                && Carbon::parse($lastUpdatedAt)->greaterThan(now()->subMinutes(5)),
        ]);
    }

    public function newsArticles(): JsonResponse
    {
        $articles = ExternalNewsArticle::query()
            ->latestPublished()
            ->cursorPaginate(config('news.page_size'));

        return response()->json([
            'html' => view('portal.partials.external-news-cards', ['articles' => $articles])->render(),
            'next' => $articles->nextPageUrl(),
        ]);
    }

    public function latestNewsArticles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['required', 'integer', 'min:0'],
        ]);

        $articles = ExternalNewsArticle::query()
            ->where('id', '>', $validated['after_id'])
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->sortByDesc('published_at')
            ->values();

        $lastUpdatedAt = cache()->get('news_last_sync_at');
        $successfulFeeds = cache()->get('news_last_sync_sources', []);
        $latestBatchId = $articles->max('id') ?? $validated['after_id'];

        return response()->json([
            'html' => view('portal.partials.external-news-cards', ['articles' => $articles])->render(),
            'latest_id' => $latestBatchId,
            'last_updated_at' => $lastUpdatedAt,
            'is_live' => $successfulFeeds !== []
                && $lastUpdatedAt !== null
                && Carbon::parse($lastUpdatedAt)->greaterThan(now()->subMinutes(5)),
        ]);
    }

    public function showNews(Request $request, TrackingEvent $event): View
    {
        $event = TrackingEvent::query()
            ->whereKey($event->id)
            ->whereHas('shipment', fn ($query) => $query->visibleTo($request->user()))
            ->with([
                'shipment.customer',
                'shipment.carrier',
                'shipment.origin',
                'shipment.destination',
                'shipment.trackingEvents.location',
                'location',
                'creator',
            ])
            ->firstOrFail();

        return view('portal.news-show', ['event' => $event]);
    }

    public function users(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(UserRole::values())],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.addcslashes($search, '\\%_').'%')
                        ->orWhere('email', 'like', '%'.addcslashes($search, '\\%_').'%')
                        ->orWhere('company_name', 'like', '%'.addcslashes($search, '\\%_').'%');
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
            ->withCount('shipments')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('portal.users', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => $filters,
        ]);
    }

    public function settings(Request $request): View
    {
        return view('portal.settings', ['user' => $request->user()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'current_password' => ['nullable', 'string', 'required_with:password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($validated['password']) && ! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])->withInput();
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return back()->with('status', 'Pengaturan akun berhasil disimpan.');
    }
}
