@extends('layouts.portal')

@section('title', 'Peta Posisi Shipment')

@section('content')
    <div class="page-heading">
        <div><h1>Peta Posisi Shipment</h1><p>Pilih marker kapal untuk melihat koordinat terakhir yang tercatat, waktu pembaruan, dan detail rutenya.</p></div>
        <a class="btn" href="{{ route('shipments.index') }}">Daftar shipment →</a>
    </div>
    <section class="map-layout">
        <div class="panel map-primary">
            <div class="panel-head"><h2>Peta posisi shipment</h2><span class="live">Posisi terakhir tercatat</span></div>
            <div class="panel-body">
                <div class="map-canvas interactive-map" role="group" aria-label="Peta interaktif shipment aktif">
                    <svg viewBox="0 0 1000 500" preserveAspectRatio="none" aria-hidden="true">
                        <g fill="#28518b" fill-opacity=".54" stroke="#558ac3" stroke-opacity=".35" stroke-width="2">
                            <path d="m75 102 55-42 80 7 48 30 36-3 27 35-33 34-40 3-25 35-31 8-23 42-33-14-23-45-32-18-18-36-30-15Z"/>
                            <path d="m275 252 39 6 30 36-6 50-28 38-17 57-27-12-10-48-21-33 8-47Z"/>
                            <path d="m435 105 43-33 62 3 38 32-21 26-40-3-14 27-32-8-15-27-30 3Z"/>
                            <path d="m453 180 49-21 51 12 32 44-25 62-42 45-29-17-5-55-35-21-16-36Z"/>
                            <path d="m547 113 55-48 96 6 52 26 78-8 71 33-25 33-77 2-51 32-54-16-42 27-38-17-33-41-49-6Z"/>
                            <path d="m758 250 48-23 64 21 39 38-22 26-66-8-46-24Z"/>
                            <path d="m897 354 22-11 19 8-12 15-21 2Z"/>
                        </g>
                        @foreach ($shipments as $shipment)
                            @php
                                $originX = (int) round(($shipment->origin->longitude + 180) / 360 * 1000);
                                $originY = (int) round((90 - $shipment->origin->latitude) / 180 * 500);
                                $destinationX = (int) round(($shipment->destination->longitude + 180) / 360 * 1000);
                                $destinationY = (int) round((90 - $shipment->destination->latitude) / 180 * 500);
                            @endphp
                            <line x1="{{ $originX }}" y1="{{ $originY }}" x2="{{ $destinationX }}" y2="{{ $destinationY }}" stroke="{{ $shipment->is_delayed ? '#ffbd4a' : '#38b8ff' }}" stroke-width="2" stroke-dasharray="7 7" opacity=".7"/>
                            <circle cx="{{ $originX }}" cy="{{ $originY }}" r="4" fill="#9beeff" stroke="#1b67c5" stroke-width="2"/>
                            <circle cx="{{ $destinationX }}" cy="{{ $destinationY }}" r="4" fill="{{ $shipment->is_delayed ? '#ffbd4a' : '#43df9a' }}" stroke="#1b67c5" stroke-width="2"/>
                        @endforeach
                    </svg>
                    @foreach ($shipments as $shipment)
                        @php
                            $locatedEvent = $shipment->latestLocatedEvent;
                            $currentLatitude = $locatedEvent?->latitude ?? $shipment->origin->latitude;
                            $currentLongitude = $locatedEvent?->longitude ?? $shipment->origin->longitude;
                            $currentX = ($currentLongitude + 180) / 360 * 100;
                            $currentY = (90 - $currentLatitude) / 180 * 100;
                            $isSelected = $selectedShipment?->is($shipment) ?? false;
                            $locationName = $locatedEvent?->location?->name ?? $locatedEvent?->location?->city ?? $shipment->origin->name;
                        @endphp
                        <a class="map-marker {{ $isSelected ? 'selected' : '' }}" data-map-x="{{ $currentX }}" data-map-y="{{ $currentY }}" style="left:{{ $currentX }}%;top:{{ $currentY }}%" href="{{ route('map', ['selected' => $shipment->id]) }}" aria-label="Pilih kapal {{ $shipment->shipment_number }} di {{ $locationName }}">
                            <span class="map-marker-pulse"></span><span class="map-marker-dot">✦</span>
                            <span class="map-marker-label">{{ $shipment->shipment_number }}<small>{{ $locationName }}@if (! $locatedEvent) · lokasi asal @endif</small></span>
                        </a>
                    @endforeach
                    <div class="map-legend"><span>✦ Kapal / shipment</span><span><i class="legend-origin"></i> Asal</span><span><i class="legend-destination"></i> Tujuan</span><span><i class="legend-delay"></i> Rute terlambat</span></div>
                    @if ($shipments->isEmpty())
                        <div class="map-empty">Belum ada shipment aktif untuk dipetakan.</div>
                    @endif
                </div>
            </div>
        </div>
        <aside class="map-side">
            @if ($selectedShipment)
                @php
                    $lastPosition = $selectedShipment->latestLocatedEvent;
                    $positionLocation = $lastPosition?->location?->name ?? $lastPosition?->location?->city ?? $selectedShipment->origin->name;
                    $positionLatitude = $lastPosition?->latitude ?? $selectedShipment->origin->latitude;
                    $positionLongitude = $lastPosition?->longitude ?? $selectedShipment->origin->longitude;
                @endphp
                <section class="panel position-card">
                    <div class="panel-head"><h2>Posisi kapal terpilih</h2><span class="status {{ $selectedShipment->is_delayed ? 'delayed' : '' }}">{{ $selectedShipment->is_delayed ? 'Terlambat' : 'Aktif' }}</span></div>
                    <div class="panel-body">
                        <div class="position-number">{{ $selectedShipment->shipment_number }}</div>
                        <div class="position-location">✦ &nbsp;{{ $positionLocation }}</div>
                        <div class="position-route">{{ $selectedShipment->origin->city }}, {{ $selectedShipment->origin->country }} <span>→</span> {{ $selectedShipment->destination->city }}, {{ $selectedShipment->destination->country }}</div>
                        @if ($lastPosition)
                            <div class="position-meta"><span>GPS terakhir tercatat</span><strong>{{ $lastPosition->occurred_at->format('d M Y · H:i') }}</strong></div>
                            <div class="position-meta"><span>Koordinat</span><strong>{{ number_format($positionLatitude, 5) }}, {{ number_format($positionLongitude, 5) }}</strong></div>
                        @else
                            <div class="position-note">Belum ada koordinat GPS untuk shipment ini. Marker menunjukkan lokasi asal, bukan posisi GPS live.</div>
                        @endif
                        <a class="btn primary position-action" href="{{ route('tracking.show', $selectedShipment) }}">Buka detail tracking →</a>
                    </div>
                </section>
            @else
                <section class="panel"><div class="empty">Tidak ada posisi shipment untuk ditampilkan.</div></section>
            @endif
            <section class="panel">
                <div class="panel-head"><h2>Shipment aktif</h2><span class="text-link">{{ $shipments->count() }} kapal</span></div>
                <div class="map-vessel-list">
                    @forelse ($shipments as $shipment)
                        @php
                            $event = $shipment->latestLocatedEvent;
                            $currentName = $event?->location?->city ?? $event?->location?->name ?? $shipment->origin->city;
                        @endphp
                        <a class="vessel-row {{ $selectedShipment?->is($shipment) ? 'active' : '' }}" href="{{ route('map', ['selected' => $shipment->id]) }}">
                            <span class="vessel-symbol">✦</span>
                            <span class="vessel-info"><strong>{{ $shipment->shipment_number }}</strong><small>{{ $currentName }} → {{ $shipment->destination->city }}</small></span>
                            <span class="vessel-chevron">›</span>
                        </a>
                    @empty
                        <div class="empty">Tidak ada kapal aktif.</div>
                    @endforelse
                </div>
                <div class="vessel-footer"><a class="text-link" href="{{ route('shipments.index') }}">Lihat semua shipment →</a></div>
            </section>
        </aside>
    </div>
    <section class="panel" style="margin-top:13px">
        <div class="panel-head"><h2>Lokasi Logistik</h2><span class="text-link">{{ $locations->count() }} lokasi</span></div>
        <div class="table-wrap">
            <table class="data-table"><thead><tr><th>Nama lokasi</th><th>Kota</th><th>Negara</th><th>Tipe</th><th>Koordinat</th></tr></thead><tbody>
                @forelse ($locations as $location)
                    <tr><td class="primary-text">{{ $location->name }}<span class="secondary-text">{{ $location->code }}</span></td><td>{{ $location->city }}</td><td>{{ $location->country }} ({{ $location->country_code }})</td><td>{{ str($location->type)->title() }}</td><td>{{ $location->latitude }}, {{ $location->longitude }}</td></tr>
                @empty
                    <tr><td colspan="5"><div class="empty">Belum ada lokasi dalam shipment aktif.</div></td></tr>
                @endforelse
            </tbody></table>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const canvas = document.querySelector('.interactive-map');
            if (!canvas) return;

            const markers = Array.from(canvas.querySelectorAll('.map-marker'));
            const spreadOverlaps = () => {
                const bounds = canvas.getBoundingClientRect();
                const points = markers.map(marker => ({
                    marker,
                    x: bounds.left + bounds.width * Number(marker.dataset.mapX) / 100,
                    y: bounds.top + bounds.height * Number(marker.dataset.mapY) / 100,
                }));
                const remaining = new Set(points);
                const placed = [];

                markers.forEach(marker => {
                    marker.classList.remove('clustered');
                    marker.style.left = `${marker.dataset.mapX}%`;
                    marker.style.top = `${marker.dataset.mapY}%`;
                });

                const findOpenPosition = center => {
                    for (let ring = 0; ring < 100; ring++) {
                        const radius = ring === 0 ? 0 : 40 + (ring - 1) * 34;
                        const capacity = radius === 0 ? 1 : Math.floor(2 * Math.PI * radius / 32);

                        for (let slot = 0; slot < capacity; slot++) {
                            const angle = 2 * Math.PI * slot / capacity - Math.PI / 2;
                            const x = Math.max(14, Math.min(bounds.width - 14, center.x - bounds.left + Math.cos(angle) * radius));
                            const y = Math.max(14, Math.min(bounds.height - 14, center.y - bounds.top + Math.sin(angle) * radius));

                            if (placed.every(point => Math.abs(point.x - x) >= 28 || Math.abs(point.y - y) >= 28)) {
                                return { x, y };
                            }
                        }
                    }

                    for (let y = 14; y <= bounds.height - 14; y += 32) {
                        for (let x = 14; x <= bounds.width - 14; x += 32) {
                            if (placed.every(point => Math.abs(point.x - x) >= 28 || Math.abs(point.y - y) >= 28)) {
                                return { x, y };
                            }
                        }
                    }

                    return { x: 14, y: 14 };
                };

                while (remaining.size) {
                    const cluster = [remaining.values().next().value];
                    remaining.delete(cluster[0]);

                    for (let index = 0; index < cluster.length; index++) {
                        Array.from(remaining).forEach(point => {
                            if (cluster.some(member => Math.abs(member.x - point.x) < 28 && Math.abs(member.y - point.y) < 28)) {
                                cluster.push(point);
                                remaining.delete(point);
                            }
                        });
                    }

                    if (cluster.length < 2) continue;

                    const center = {
                        x: cluster.reduce((sum, point) => sum + point.x, 0) / cluster.length,
                        y: cluster.reduce((sum, point) => sum + point.y, 0) / cluster.length,
                    };

                    cluster.forEach(point => {
                        const position = findOpenPosition(center);
                        placed.push(position);
                        point.marker.style.left = `${position.x}px`;
                        point.marker.style.top = `${position.y}px`;

                        const length = Math.hypot(center.x - bounds.left - position.x, center.y - bounds.top - position.y);
                        if (cluster.length > 1 || length > 1) {
                            point.marker.classList.add('clustered');
                        }

                        point.marker.style.setProperty('--marker-leader-length', `${length}px`);
                        point.marker.style.setProperty('--marker-leader-angle', `${Math.atan2(center.y - bounds.top - position.y, center.x - bounds.left - position.x) * 180 / Math.PI}deg`);
                    });
                }
            };

            spreadOverlaps();
            window.addEventListener('resize', spreadOverlaps);
        })();
    </script>
@endpush
