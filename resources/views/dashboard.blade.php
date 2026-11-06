@extends('layouts.portal')

@section('title', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
@endpush

@section('content')
    @php
        $statusLabel = fn ($shipment) => $shipment->exceptions->isNotEmpty()
            ? 'Exception'
            : ($shipment->is_delayed
                ? 'Delayed'
                : str($shipment->current_status->value)->replace('_', ' ')->title());
        $statusClass = fn ($shipment) => $shipment->exceptions->isNotEmpty()
            ? 'exception'
            : ($shipment->is_delayed
                ? 'delayed'
                : match ($shipment->current_status->value) {
                    'IN_TRANSIT', 'PICKED_UP' => 'transit',
                    'DELIVERED' => 'delivered',
                    'CANCELLED' => 'cancelled',
                    default => 'booked',
                });
        $share = fn (int $value) => $shipmentCount > 0 ? round($value / $shipmentCount * 100, 1) : 0;
        $isNewsLive = $newsIsLive;
    @endphp

    <div class="dashboard-page">
        <section class="dashboard-hero-grid" aria-label="Global logistics overview">
            <article class="hero-visual">
                <div class="hero-globe-stage" id="hero-globe" data-endpoint="{{ route('dashboard.map-data') }}" aria-label="Interactive 3D globe. Recorded shipment routes appear when coordinates are available.">
                    <img class="hero-globe-fallback" src="{{ asset('images/dashboard/hero-digital-globe.svg') }}?v={{ filemtime(public_path('images/dashboard/hero-digital-globe.svg')) }}" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
                    <div class="hero-globe-popover" id="hero-globe-popover" role="status" hidden></div>
                    <span class="hero-globe-hint" id="hero-globe-hint">Drag / touch · Scroll / pinch to zoom</span>
                </div>
                <div class="hero-content">
                    <span class="hero-eyebrow"><i></i><span data-en="GLOBAL LOGISTICS PLATFORM" data-id="PLATFORM LOGISTIK GLOBAL">GLOBAL LOGISTICS PLATFORM</span></span>
                    <h1 data-en="Track Your Shipment<br>Across <em>The World</em>" data-id="Lacak Shipment<br>ke <em>Seluruh Dunia</em>">Track Your Shipment<br>Across <em>The World</em></h1>
                    <p data-en="Global logistics visibility, powered by your latest recorded shipment updates." data-id="Visibilitas logistik global dengan pembaruan shipment terakhir yang tercatat.">Global logistics visibility, powered by your latest recorded shipment updates.</p>
                    <div class="hero-actions">
                        <a class="dashboard-button primary" href="{{ route('shipments.index') }}"><span data-en="Track Shipment" data-id="Lacak Shipment">Track Shipment</span><span aria-hidden="true">→</span></a>
                        <a class="dashboard-button ghost" href="#global-map"><span data-en="View Map" data-id="Lihat Peta">View Map</span><span aria-hidden="true">↗</span></a>
                    </div>
                </div>
                <div class="hero-orbit-label"><span class="orbit-pulse"></span>Illustrative routes · recorded locations shown on map</div>
            </article>

            <aside class="panel live-news-panel" id="dashboard-live-news" data-latest-id="{{ $latestNewsId }}" data-live="{{ $isNewsLive ? 'true' : 'false' }}">
                <div class="panel-heading">
                    <div><span class="heading-kicker"><i class="live-dot"></i><span data-en="LOGISTICS NEWS" data-id="BERITA LOGISTIK">LOGISTICS NEWS</span></span><h2 data-en="Global logistics" data-id="Logistik global">Global logistics</h2></div>
                    <a class="view-all-link" href="{{ route('news.index') }}"><span data-en="View All" data-id="Semua">View All</span> <span aria-hidden="true">→</span></a>
                </div>
                <div class="live-news-list" id="dashboard-news-list">
                    @forelse ($latestNews as $article)
                        <a class="live-news-item" href="{{ $article->article_url }}" target="_blank" rel="noopener noreferrer" data-article-id="{{ $article->id }}" data-published-at="{{ $article->published_at->toIso8601String() }}" data-category="{{ $article->category }}">
                            @if ($article->image_url)
                                <span class="live-news-thumb"><img src="{{ $article->image_url }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('.live-news-thumb').remove()"></span>
                            @else
                                <span class="live-news-thumb news-no-image" aria-hidden="true"><span>{{ strtoupper(substr($article->source, 0, 1)) }}</span></span>
                            @endif
                            <span class="live-news-copy">
                                <span><strong>{{ $article->source }}</strong><time datetime="{{ $article->published_at->toIso8601String() }}" title="{{ $article->published_at->timezone('UTC')->format('d M Y, H:i:s') }} UTC">{{ $article->published_at->diffForHumans() }}</time></span>
                                <b>{{ $article->title }}</b>
                                <span class="news-category">{{ $article->category }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="news-empty-state"><span class="news-empty-icon">◈</span><strong>No news yet</strong><span>Latest publisher headlines will appear here when available.</span></div>
                    @endforelse
                </div>
                <div class="news-footer">
                    <span class="news-sync-status"><span id="dashboard-news-live" class="{{ $isNewsLive ? 'is-live' : 'is-stale' }}" title="Feed synchronization status"><i></i>{{ $isNewsLive ? 'SYNCED' : 'SYNC STALE' }}</span><small>Feed status</small></span>
                    <span><small>Last sync </small><time id="dashboard-news-updated">{{ $newsLastUpdatedAt ? \Illuminate\Support\Carbon::parse($newsLastUpdatedAt)->timezone('UTC')->format('H:i').' UTC' : 'Awaiting sync' }}</time></span>
                </div>
            </aside>
        </section>

        <section class="kpi-grid" aria-label="Shipment statistics">
            <article class="kpi-card accent-blue" title="All shipments visible to your account. Sparkline shows shipment-created events in the selected analytics period.">
                <div class="kpi-top"><span class="kpi-icon" aria-hidden="true">▣</span><span class="kpi-share">{{ $share($shipmentCount) }}%</span></div>
                <span class="kpi-label" data-en="Total Shipments" data-id="Total Shipment">Total Shipments</span>
                <div class="kpi-value">{{ number_format($shipmentCount) }}</div>
                <div class="kpi-bottom"><span>All visible records · new events</span><svg viewBox="0 0 100 30" role="img" aria-label="Shipment creation events in selected period"><polyline points="{{ $chartSparklines['total'] }}"/></svg></div>
            </article>
            <article class="kpi-card accent-blue" title="Share of visible shipments whose current status is In Transit. Sparkline shows recorded transit events in the selected period.">
                <div class="kpi-top"><span class="kpi-icon">↗</span><span class="kpi-share">{{ $share($inTransitCount) }}%</span></div>
                <span class="kpi-label" data-en="In Transit" data-id="Dalam Perjalanan">In Transit</span>
                <div class="kpi-value">{{ number_format($inTransitCount) }}</div>
                <div class="kpi-bottom"><span>Current status · transit events</span><svg viewBox="0 0 100 30" role="img" aria-label="Transit events in selected period"><polyline points="{{ $chartSparklines['transit'] }}"/></svg></div>
            </article>
            <article class="kpi-card accent-green" title="Share of visible shipments currently delivered. Sparkline shows recorded delivery events in the selected period.">
                <div class="kpi-top"><span class="kpi-icon">✓</span><span class="kpi-share">{{ $share($deliveredCount) }}%</span></div>
                <span class="kpi-label" data-en="Delivered" data-id="Terkirim">Delivered</span>
                <div class="kpi-value">{{ number_format($deliveredCount) }}</div>
                <div class="kpi-bottom"><span>Current status · delivery events</span><svg viewBox="0 0 100 30" role="img" aria-label="Delivery events in selected period"><polyline points="{{ $chartSparklines['delivered'] }}"/></svg></div>
            </article>
            <article class="kpi-card accent-red" title="Exception count is incident records, not unique shipments. The percentage is the share of visible shipments with at least one exception; delayed shipments may overlap.">
                <div class="kpi-top"><span class="kpi-icon" aria-hidden="true">!</span><span class="kpi-share">{{ $share($exceptionShipmentCount) }}% shipments</span></div>
                <span class="kpi-label" data-en="Exceptions" data-id="Pengecualian">Exceptions</span>
                <div class="kpi-value">{{ number_format($totalExceptionCount) }}</div>
                <div class="kpi-bottom"><span>{{ number_format($openExceptionCount) }} open · {{ number_format($delayedCount) }} delayed</span><svg viewBox="0 0 100 30" role="img" aria-label="Exception events in selected period"><polyline points="{{ $chartSparklines['exceptions'] }}"/></svg></div>
            </article>
            <article class="kpi-card accent-violet" title="On-time deliveries divided by all currently delivered shipments. Sparkline shows the on-time rate of delivery events per selected-period bucket, based on the recorded shipment delay flag.">
                <div class="kpi-top"><span class="kpi-icon" aria-hidden="true">◎</span><span class="kpi-share">{{ $deliveredCount > 0 ? number_format($onTimeDeliveredCount).'/'.number_format($deliveredCount).' on time' : 'No data' }}</span></div>
                <span class="kpi-label" data-en="On-Time Delivery" data-id="Pengiriman Tepat Waktu">On-Time Delivery</span>
                <div class="kpi-value">{{ $onTimeDeliveryRate !== null ? number_format($onTimeDeliveryRate, 1).'%' : '—' }}</div>
                <div class="kpi-bottom"><span>Historical on-time delivery rate</span><svg viewBox="0 0 100 30" role="img" aria-label="On-time rate for delivery events in selected period"><polyline points="{{ $chartSparklines['onTime'] }}"/></svg></div>
            </article>
        </section>

        <section class="dashboard-main-grid">
            <article class="panel map-panel" id="global-map">
                <div class="panel-heading map-heading">
                    <div class="panel-title-group"><span class="panel-icon">⌖</span><div><h2 data-en="Global Shipment Tracking" data-id="Pelacakan Shipment Global">Global Shipment Tracking</h2><p>Recorded GPS positions · polling does not imply live GPS</p></div><span class="position-badge">RECORDED DATA</span></div>
                    <span class="map-type-label">Interactive world map</span>
                </div>
                <div class="map-toolbar">
                    <label class="map-filter-label" for="shipment-map-status">Status</label>
                    <select id="shipment-map-status" class="map-filter">
                        <option value="all">All shipments</option>
                        <option value="in_transit">In Transit</option>
                        <option value="delivered">Delivered</option>
                        <option value="delayed">Delayed</option>
                        <option value="exception">Exception</option>
                        <option value="other">Other statuses</option>
                    </select>
                    <label class="map-filter-label" for="shipment-map-layer">Map</label>
                    <select id="shipment-map-layer" class="map-filter">
                        <option value="standard">Standard</option>
                        <option value="dark" selected>Dark</option>
                    </select>
                    <button class="map-control-button" id="map-fit-all" type="button">Fit shipments</button>
                    <button class="map-control-button" id="map-reset-view" type="button">Reset view</button>
                    <button class="map-control-button" id="map-fullscreen" type="button" aria-pressed="false">Fullscreen</button>
                </div>
                <div class="map-canvas geographic-map" id="dashboard-map"
                    data-endpoint="{{ route('dashboard.map-data') }}"
                    data-tile-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
                    data-tracking-url="{{ url('/tracking') }}"
                    role="region" aria-label="Interactive geographic map of shipment routes">
                    <div class="map-loading" id="map-loading"><span></span>Loading shipment map…</div>
                    <div class="map-error" id="map-error" role="alert" hidden>
                        <strong>Map data unavailable</strong>
                        <span id="map-error-message">Check your connection and try again.</span>
                        <button type="button" id="map-retry">Retry</button>
                    </div>
                    <div class="map-empty" id="map-empty" role="status" hidden>
                        <strong>No mapped shipment locations</strong>
                        <span>Location coordinates are not available for the selected shipments. No GPS positions have been inferred.</span>
                    </div>
                    <div class="map-limit-notice" id="map-limit-notice" hidden>Showing the latest 500 shipments. Use the shipment status filter to narrow results.</div>
                </div>
                <div class="map-footer">
                    <div class="map-legend">
                        <span><i class="legend-transit"></i>In Transit</span>
                        <span><i class="legend-delivered"></i>Delivered</span>
                        <span><i class="legend-delayed"></i>Delayed</span>
                        <span><i class="legend-exception"></i>Exception</span>
                        <span><i class="legend-origin"></i>Origin</span>
                        <span><i class="legend-destination"></i>Destination</span>
                        <span><i class="legend-position"></i>Recorded GPS</span>
                    </div>
                    <div class="map-footer-meta"><span id="map-shipment-count">Loading shipment records…</span><span class="map-updated">Data checked <time id="map-last-updated">Waiting…</time> · positions reflect last recorded GPS</span><span class="map-tile-status" id="map-tile-status" hidden><span id="map-tile-message"></span><button type="button" id="map-tile-retry">Retry tiles</button></span></div>
                </div>
            </article>

            <article class="panel recent-panel" id="recent-shipments">
                <div class="panel-heading recent-heading">
                    <div><span class="heading-kicker">OPERATIONS</span><h2 data-en="Recent Shipments" data-id="Shipment Terbaru">Recent Shipments</h2></div>
                    <a class="view-all-link" href="{{ route('shipments.index') }}">View All <span aria-hidden="true">→</span></a>
                </div>
                <form class="shipment-filters" method="GET" action="{{ route('dashboard') }}">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <label class="sr-only" for="shipment-search">Search shipments</label>
                    <input id="shipment-search" type="search" name="search" value="{{ $search }}" placeholder="Tracking no. or city" aria-label="Search shipments by tracking number or city">
                    <label class="sr-only" for="shipment-status-filter">Filter by shipment status</label>
                    <select id="shipment-status-filter" name="status" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        @foreach ($shipmentStatuses as $status)
                            <option value="{{ $status->value }}" @selected($shipmentStatus === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="shipment-sort">Sort shipments</label>
                    <select id="shipment-sort" name="sort" aria-label="Sort shipments">
                        <option value="updated_desc" @selected($shipmentSort === 'updated_desc')>Recently updated</option>
                        <option value="updated_asc" @selected($shipmentSort === 'updated_asc')>Least recently updated</option>
                        <option value="eta_asc" @selected($shipmentSort === 'eta_asc')>ETA earliest</option>
                        <option value="eta_desc" @selected($shipmentSort === 'eta_desc')>ETA latest</option>
                    </select>
                    <button class="filter-submit" type="submit">Apply</button>
                </form>
                <div class="shipment-table-wrap">
                    <table class="shipment-table">
                        <thead><tr><th>Tracking No.</th><th>Origin</th><th>Destination</th><th>Status</th><th>ETA</th><th><span class="sr-only">Action</span></th></tr></thead>
                        <tbody>
                            @forelse ($shipments as $shipment)
                                @php $eta = $shipment->estimated_arrival_at ?? $shipment->expected_arrival_at; @endphp
                                <tr>
                                    <td><a href="{{ route('tracking.show', $shipment) }}">{{ $shipment->shipment_number }}</a></td>
                                    <td><span class="table-route" title="{{ $shipment->origin->city }}, {{ $shipment->origin->country }}">{{ $shipment->origin->city }}</span></td>
                                    <td><span class="table-route" title="{{ $shipment->destination->city }}, {{ $shipment->destination->country }}">{{ $shipment->destination->city }}</span></td>
                                    <td><span class="shipment-status {{ $statusClass($shipment) }}"><i></i>{{ $statusLabel($shipment) }}</span></td>
                                    <td>{{ $eta ? $eta->timezone('UTC')->format('M d, H:i').' UTC' : '—' }}</td>
                                    <td><a class="table-action" href="{{ route('tracking.show', $shipment) }}" aria-label="View shipment {{ $shipment->shipment_number }}">↗</a></td>
                                </tr>
                            @empty
                                <tr><td class="table-empty" colspan="6">{{ $search || $shipmentStatus ? 'No shipment matched the selected filters.' : 'No shipments available yet.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="shipment-pagination">
                    <span>{{ $shipments->total() }} matching shipment{{ $shipments->total() === 1 ? '' : 's' }}</span>
                    {{ $shipments->links() }}
                </div>
            </article>

            <article class="panel analytics-panel">
                <div class="panel-heading analytics-heading">
                    <div><span class="heading-kicker">PERFORMANCE</span><h2 data-en="Shipment Analytics" data-id="Analitik Shipment">Shipment Analytics</h2></div>
                    <select class="period-select" id="analytics-period" aria-label="Analytics date range">
                        @foreach (['7d' => '7D', '30d' => '30D', '90d' => '90D'] as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="analytics-tabs" role="tablist" aria-label="Analytics dimensions">
                    <button class="analytics-tab active" type="button" role="tab" aria-selected="true" data-chart="overview">Overview</button>
                    <button class="analytics-tab" type="button" role="tab" aria-selected="false" data-chart="region">By Region</button>
                    <button class="analytics-tab" type="button" role="tab" aria-selected="false" data-chart="carrier">By Carrier</button>
                </div>
                <div class="chart-summary"><strong id="chart-total">{{ number_format($periodShipmentCount) }}</strong><span>shipments created in selected period</span><span class="chart-live"><i></i> database</span></div>
                <div class="chart-wrap"><svg id="analytics-chart" viewBox="0 0 440 210" role="img" aria-label="Shipment events in the selected date range"></svg></div>
                <div class="chart-empty-state" id="chart-empty-state" hidden>No shipment activity is available for this period.</div>
                <div class="chart-legend" id="analytics-legend"></div>
                <script type="application/json" id="analytics-data">@json($analyticsData)</script>
            </article>
        </section>

        <section class="dashboard-bottom-grid">
            <article class="panel carriers-panel">
                <div class="panel-heading"><div><span class="heading-kicker">NETWORK</span><h2 data-en="Top Carriers" data-id="Carrier Teratas">Top Carriers</h2></div><a class="view-all-link" href="{{ route('reports.index') }}">View All <span aria-hidden="true">→</span></a></div>
                <div class="carrier-list">
                    @forelse ($carrierStats as $index => $carrier)
                        @php $carrierShare = $shipmentCount > 0 ? round($carrier->visible_shipments_count / $shipmentCount * 100, 1) : 0; @endphp
                        <div class="carrier-item">
                            <span class="carrier-rank">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="carrier-logo" aria-hidden="true">{{ strtoupper(substr($carrier->code ?: $carrier->name, 0, 2)) }}</span>
                            <span class="carrier-detail"><strong>{{ $carrier->name }}</strong><small>{{ number_format($carrier->visible_shipments_count) }} shipments · {{ $carrier->code }}</small><i role="progressbar" aria-label="{{ $carrier->name }} shipment contribution" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $carrierShare }}"><b style="width:{{ $carrierShare }}%"></b></i></span>
                            <strong class="carrier-percent">{{ $carrierShare }}%</strong>
                        </div>
                    @empty
                        <div class="empty">Carrier performance data will appear here.</div>
                    @endforelse
                </div>
            </article>

            <article class="panel timeline-panel">
                <div class="panel-heading"><div><span class="heading-kicker">LATEST UPDATES</span><h2 data-en="Tracking Timeline" data-id="Linimasa Tracking">Tracking Timeline</h2></div><a class="view-all-link" href="{{ route('shipments.index') }}">View Shipments <span aria-hidden="true">→</span></a></div>
                <div class="tracking-timeline">
                    @forelse ($recentEvents as $event)
                        <a class="timeline-item {{ $event->is_exception ? 'timeline-exception' : '' }}" href="{{ route('tracking.show', $event->shipment) }}">
                            <time datetime="{{ $event->occurred_at->toIso8601String() }}" title="{{ $event->occurred_at->timezone('UTC')->format('M d, Y H:i:s') }} UTC">{{ $event->occurred_at->timezone('UTC')->format('H:i') }} UTC</time>
                            <span class="timeline-marker"><i aria-hidden="true">{{ $event->is_exception ? '!' : '•' }}</i></span>
                            <span class="timeline-copy">
                                <strong>{{ $event->shipment->shipment_number }} · {{ str($event->event_type->value)->replace('_', ' ')->lower()->title() }}</strong>
                                <span>{{ $event->location?->city ?? $event->location?->name ?? 'Location not recorded' }} · {{ $event->description ?: str($event->event_type->value)->replace('_', ' ')->lower()->title() }}</span>
                            </span>
                            <span class="timeline-date">{{ $event->occurred_at->timezone('UTC')->format('M d, Y') }}</span>
                        </a>
                    @empty
                        <div class="empty">No tracking activity recorded yet.</div>
                    @endforelse
                </div>
            </article>

            <article class="connected-banner">
                <img src="{{ asset('images/news/cargo-vessel.svg') }}" alt="" loading="lazy">
                <div class="connected-shade"></div>
                <div class="connected-copy"><span class="heading-kicker">CARGOTRACE GLOBAL</span><h2 data-en="Smarter Logistics.<br>A More Connected World." data-id="Logistik Lebih Cerdas.<br>Dunia Lebih Terhubung.">Smarter Logistics.<br>A More Connected World.</h2><p>One clear view of every journey, from origin to destination.</p><a class="dashboard-button light" href="{{ route('map') }}">Explore the map <span aria-hidden="true">→</span></a></div>
            </article>
        </section>

        <section class="dashboard-currency-section" aria-label="Currency converter">
            <article class="panel currency-converter" id="currency-converter" data-endpoint="{{ route('dashboard.currency-rates') }}" aria-busy="true">
                <div class="panel-heading">
                    <div><span class="heading-kicker">CURRENCY TOOLS</span><h2 data-en="International currency converter" data-id="Konverter mata uang internasional">International currency converter</h2></div>
                </div>
                <div class="currency-converter-body">
                    <label class="currency-field" for="currency-amount">
                        <span>Amount</span>
                        <input class="form-control" id="currency-amount" type="number" min="0" step="any" value="1" inputmode="decimal">
                    </label>
                    <label class="currency-field" for="currency-from">
                        <span>From</span>
                        <select class="form-control" id="currency-from" disabled aria-label="Convert from currency"></select>
                    </label>
                    <button class="currency-swap" id="currency-swap" type="button" aria-label="Swap currencies" disabled>⇄</button>
                    <label class="currency-field" for="currency-to">
                        <span>To</span>
                        <select class="form-control" id="currency-to" disabled aria-label="Convert to currency"></select>
                    </label>
                    <div class="currency-result-wrap">
                        <span class="currency-result-label">Converted amount</span>
                        <output class="currency-result" id="currency-result" aria-live="polite">Loading exchange rates…</output>
                    </div>
                </div>
                <div class="currency-footer">
                    <span id="currency-rate-status" role="status">Fetching daily reference rates…</span>
                    <a href="https://www.exchangerate-api.com/" target="_blank" rel="noopener noreferrer">Rates by ExchangeRate-API ↗</a>
                </div>
            </article>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const search = document.getElementById('dashboard-search-input');
            const clock = document.getElementById('dashboard-clock');
            const language = document.getElementById('dashboard-language');
            const newsPanel = document.getElementById('dashboard-live-news');
            const newsList = document.getElementById('dashboard-news-list');
            const newsLive = document.getElementById('dashboard-news-live');
            const newsUpdated = document.getElementById('dashboard-news-updated');
            const data = JSON.parse(document.getElementById('analytics-data').textContent);
            const chart = document.getElementById('analytics-chart');
            const legend = document.getElementById('analytics-legend');
            const palette = ['#33a9ff', '#35d5b1', '#f7a94b', '#a97aff'];
            const svgNamespace = 'http://www.w3.org/2000/svg';

            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    search.focus();
                    search.select();
                }
            });

            const updateClock = () => {
                clock.textContent = new Intl.DateTimeFormat('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    timeZone: 'UTC',
                    hour12: false
                }).format(new Date());
            };
            updateClock();
            window.setInterval(updateClock, 1000);

            language.addEventListener('change', () => {
                document.documentElement.lang = language.value === 'id' ? 'id' : 'en';
                document.querySelectorAll('[data-en][data-id]').forEach((element) => {
                    element.innerHTML = element.dataset[language.value === 'id' ? 'id' : 'en'];
                });
                try {
                    localStorage.setItem('cargotrace-dashboard-language', language.value);
                } catch (error) {
                    console.error('Could not save dashboard language preference.', error);
                }
            });
            try {
                language.value = localStorage.getItem('cargotrace-dashboard-language') === 'id' ? 'id' : 'en';
            } catch (error) {
                language.value = 'en';
                console.error('Could not load dashboard language preference.', error);
            }
            language.dispatchEvent(new Event('change'));

            const svgElement = (name, attributes = {}) => {
                const element = document.createElementNS(svgNamespace, name);
                Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
                return element;
            };

            const renderChart = (mode = 'overview') => {
                chart.replaceChildren();
                legend.replaceChildren();
                const isOverview = mode === 'overview';
                const labels = isOverview ? data.labels : (data[mode] || []).map((item) => item.label);
                const series = isOverview
                    ? Object.entries(data.series).map(([name, values], index) => ({ name, values, color: palette[index] }))
                    : [{ name: mode === 'region' ? 'Shipments by region' : 'Shipments by carrier', values: (data[mode] || []).map((item) => item.value), color: '#3a9dff' }];
                const hasActivity = series.some((item) => item.values.some((value) => Number(value) > 0));
                const emptyState = document.getElementById('chart-empty-state');
                chart.hidden = !hasActivity;
                emptyState.hidden = hasActivity;
                if (!hasActivity) return;
                const width = 440;
                const height = 210;
                const left = 34;
                const right = 12;
                const top = 14;
                const bottom = 30;
                const plotWidth = width - left - right;
                const plotHeight = height - top - bottom;
                const maxValue = Math.max(1, ...series.flatMap((item) => item.values));

                [0, .33, .66, 1].forEach((ratio) => {
                    const y = top + plotHeight * ratio;
                    chart.append(svgElement('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'chart-grid-line' }));
                    const label = svgElement('text', { x: left - 8, y: y + 3, 'text-anchor': 'end', class: 'chart-axis-label' });
                    label.textContent = Math.round(maxValue * (1 - ratio));
                    chart.append(label);
                });

                if (isOverview) {
                    series.forEach((item) => {
                        const points = item.values.map((value, index) => {
                            const x = left + (item.values.length <= 1 ? plotWidth / 2 : index / (item.values.length - 1) * plotWidth);
                            const y = top + plotHeight - value / maxValue * plotHeight;
                            return `${x},${y}`;
                        }).join(' ');
                        chart.append(svgElement('polyline', { points, class: 'chart-line', stroke: item.color }));
                        item.values.forEach((value, index) => {
                            const x = left + (item.values.length <= 1 ? plotWidth / 2 : index / (item.values.length - 1) * plotWidth);
                            const y = top + plotHeight - value / maxValue * plotHeight;
                            const point = svgElement('circle', { cx: x, cy: y, r: 2.5, fill: item.color, class: 'chart-point' });
                            const title = svgElement('title');
                            title.textContent = `${item.name} · ${labels[index] || ''}: ${value}`;
                            point.append(title);
                            chart.append(point);
                        });
                    });
                    labels.forEach((label, index) => {
                        if (labels.length > 10 && index % Math.ceil(labels.length / 7) !== 0 && index !== labels.length - 1) return;
                        const x = left + (labels.length <= 1 ? plotWidth / 2 : index / (labels.length - 1) * plotWidth);
                        const text = svgElement('text', { x, y: height - 8, 'text-anchor': 'middle', class: 'chart-axis-label' });
                        text.textContent = label;
                        chart.append(text);
                    });
                } else {
                    const count = labels.length;
                    const step = plotWidth / Math.max(1, count);
                    series[0].values.forEach((value, index) => {
                        const barHeight = value / maxValue * plotHeight;
                        const rect = svgElement('rect', {
                            x: left + index * step + step * .2,
                            y: top + plotHeight - barHeight,
                            width: step * .6,
                            height: Math.max(2, barHeight),
                            rx: 4,
                            fill: series[0].color,
                            class: 'chart-bar'
                        });
                        const title = svgElement('title');
                        title.textContent = `${labels[index]}: ${value} shipments`;
                        rect.append(title);
                        chart.append(rect);
                        const text = svgElement('text', { x: left + index * step + step / 2, y: height - 8, 'text-anchor': 'middle', class: 'chart-axis-label' });
                        text.textContent = labels[index].length > 9 ? `${labels[index].slice(0, 8)}…` : labels[index];
                        chart.append(text);
                    });
                }

                series.forEach((item) => {
                    const label = document.createElement('span');
                    const swatch = document.createElement('i');
                    swatch.style.setProperty('--legend-color', item.color);
                    label.append(swatch, document.createTextNode(item.name));
                    legend.append(label);
                });
            };

            renderChart();
            document.querySelectorAll('.analytics-tab').forEach((tab) => tab.addEventListener('click', () => {
                document.querySelectorAll('.analytics-tab').forEach((item) => {
                    item.classList.toggle('active', item === tab);
                    item.setAttribute('aria-selected', item === tab ? 'true' : 'false');
                });
                renderChart(tab.dataset.chart);
            }));

            document.getElementById('analytics-period').addEventListener('change', (event) => {
                const url = new URL(window.location.href);
                url.searchParams.set('period', event.target.value);
                url.searchParams.delete('page');
                window.location.assign(url);
            });

            const relativePublishedTime = (publishedAt) => {
                if (!publishedAt || Number.isNaN(Date.parse(publishedAt))) return 'Publication time unavailable';
                const seconds = Math.round((Date.parse(publishedAt) - Date.now()) / 1000);
                const absSeconds = Math.abs(seconds);
                const [value, unit] = absSeconds < 60 ? [seconds, 'second']
                    : absSeconds < 3600 ? [Math.round(seconds / 60), 'minute']
                        : absSeconds < 86400 ? [Math.round(seconds / 3600), 'hour']
                            : [Math.round(seconds / 86400), 'day'];
                return new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(value, unit);
            };
            const createNewsItem = (article) => {
                const link = document.createElement('a');
                link.className = 'live-news-item';
                link.href = article.getAttribute('href');
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.dataset.articleId = article.dataset.articleId;
                link.dataset.publishedAt = article.dataset.publishedAt || article.querySelector('time')?.dateTime || '';
                const image = article.querySelector('img');
                const thumb = document.createElement('span');
                thumb.className = image ? 'live-news-thumb' : 'live-news-thumb news-no-image';
                if (image) {
                    const imageCopy = document.createElement('img');
                    imageCopy.src = image.src;
                    imageCopy.alt = '';
                    imageCopy.loading = 'lazy';
                    imageCopy.referrerPolicy = 'no-referrer';
                    thumb.append(imageCopy);
                } else {
                    const fallback = document.createElement('span');
                    fallback.textContent = article.querySelector('.external-news-meta strong')?.textContent?.slice(0, 1) || 'N';
                    thumb.append(fallback);
                }

                const copy = document.createElement('span');
                copy.className = 'live-news-copy';
                const metadata = document.createElement('span');
                const source = document.createElement('strong');
                source.textContent = article.querySelector('.external-news-meta strong')?.textContent || 'News';
                const time = document.createElement('time');
                time.dateTime = link.dataset.publishedAt;
                time.textContent = relativePublishedTime(link.dataset.publishedAt);
                time.title = link.dataset.publishedAt
                    ? `${new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' }).format(new Date(link.dataset.publishedAt))} UTC`
                    : 'Publication time unavailable';
                metadata.append(source, time);
                const title = document.createElement('b');
                title.textContent = article.querySelector('.external-news-title')?.textContent || '';
                const category = document.createElement('span');
                category.className = 'news-category';
                category.textContent = article.querySelector('.external-news-category')?.textContent || article.dataset.category || 'News';
                copy.append(metadata, title, category);
                link.append(thumb, copy);
                return link;
            };

            const refreshNews = async () => {
                try {
                    const response = await fetch(`{{ route('news.latest') }}?after_id=${newsPanel.dataset.latestId}`, {
                        headers: { Accept: 'application/json' },
                        cache: 'no-store'
                    });
                    if (!response.ok) throw new Error(`News refresh failed (${response.status}).`);
                    const result = await response.json();
                    if (result.html) {
                        const parsed = new DOMParser().parseFromString(result.html, 'text/html');
                        const existingIds = new Set([...newsList.querySelectorAll('.live-news-item')].map((item) => item.dataset.articleId));
                        [...parsed.querySelectorAll('.external-news-card')].reverse().forEach((article) => {
                            if (!existingIds.has(article.dataset.articleId)) {
                                newsList.prepend(createNewsItem(article));
                                existingIds.add(article.dataset.articleId);
                            }
                        });
                        const items = [...newsList.querySelectorAll('.live-news-item')];
                        items.sort((first, second) => Date.parse(second.dataset.publishedAt || '') - Date.parse(first.dataset.publishedAt || ''));
                        items.forEach((item) => newsList.append(item));
                        items.slice(4).forEach((item) => item.remove());
                        newsPanel.dataset.latestId = result.latest_id;
                    }
                    newsLive.className = result.is_live ? 'is-live' : 'is-stale';
                    newsLive.replaceChildren(document.createElement('i'), document.createTextNode(result.is_live ? 'SYNCED' : 'SYNC STALE'));
                    if (result.last_updated_at) {
                        newsUpdated.textContent = `${new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' }).format(new Date(result.last_updated_at))} UTC`;
                    }
                } catch (error) {
                    newsLive.className = 'is-stale';
                    newsLive.replaceChildren(document.createElement('i'), document.createTextNode('SYNC ERROR'));
                    console.error(error);
                }
            };
            refreshNews();
            window.setInterval(refreshNews, 120000);
        })();
    </script>
    @vite('resources/js/dashboard-map.js')
    @vite('resources/js/dashboard-globe.js')
    @vite('resources/js/dashboard-currency.js')
@endpush
