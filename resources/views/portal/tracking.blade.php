@extends('layouts.portal')

@section('title', 'Tracking ' . $shipment->shipment_number)

@section('content')
    <div class="page-heading">
        <div><h1>Tracking Details</h1><p>Pantau status, rute, dan riwayat perjalanan shipment.</p></div>
        <a class="btn subtle" href="{{ route('shipments.index') }}">← Semua shipment</a>
    </div>

    @php
        $statusOrder = ['CREATED' => 0, 'BOOKED' => 0, 'PICKED_UP' => 1, 'IN_TRANSIT' => 2, 'DELIVERED' => 3];
        $currentOrder = $statusOrder[$shipment->current_status->value] ?? -1;
    @endphp
    <section class="panel detail-hero">
        <div><div class="detail-number">{{ $shipment->shipment_number }}</div><div class="detail-sub">{{ $shipment->origin?->city }}, {{ $shipment->origin?->country }} → {{ $shipment->destination?->city }}, {{ $shipment->destination?->country }}</div></div>
        <span class="status {{ $shipment->is_delayed ? 'delayed' : ($shipment->current_status->value === 'DELIVERED' ? 'delivered' : '') }}">{{ $shipment->is_delayed ? 'Terlambat' : str($shipment->current_status->value)->replace('_', ' ')->title() }}</span>
    </section>

    <section class="stat-grid" style="margin-top:13px">
        <article class="stat-card"><small>Customer</small><strong style="font-size:13px">{{ $shipment->customer?->company_name ?: $shipment->customer?->name }}</strong></article>
        <article class="stat-card"><small>Carrier</small><strong style="font-size:13px">{{ $shipment->carrier?->name ?? '—' }}</strong></article>
        <article class="stat-card"><small>Berat / Volume</small><strong style="font-size:13px">{{ number_format((float) $shipment->total_weight_kg, 1) }} kg <span class="secondary-text">{{ number_format((float) $shipment->total_volume_cbm, 2) }} m³</span></strong></article>
        <article class="stat-card"><small>Estimasi tiba</small><strong style="font-size:13px">{{ ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('d M Y') ?? '—' }}<span class="secondary-text">{{ ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('H:i') }}</span></strong></article>
    </section>

    <div class="grid-two">
        <section class="panel">
            <div class="panel-head"><h2>Tracking Timeline</h2><span class="text-link">{{ $shipment->trackingEvents->count() }} pembaruan</span></div>
            <div class="panel-body">
                <div class="timeline" aria-label="Progress shipment">
                    @foreach (['CREATED' => 'Dibuat', 'PICKED_UP' => 'Diambil', 'IN_TRANSIT' => 'Transit', 'DELIVERED' => 'Terkirim'] as $stepStatus => $stepLabel)
                        @php $stepOrder = $statusOrder[$stepStatus]; @endphp
                        <div class="timeline-step {{ $stepOrder < $currentOrder ? 'done' : '' }} {{ $stepOrder === $currentOrder ? 'current' : '' }}"><span class="timeline-dot"></span><span>{{ $stepLabel }}</span></div>
                    @endforeach
                </div>
                @forelse ($shipment->trackingEvents->sortByDesc('occurred_at') as $event)
                    <div class="timeline-event">
                        <time>{{ $event->occurred_at->format('d M Y H:i') }}</time>
                        <span class="event-mark">●</span>
                        <span><strong>{{ str($event->event_type->value)->replace('_', ' ')->title() }}</strong><span class="secondary-text">{{ $event->location?->name ?? $event->location?->city }}{{ $event->description ? ' · ' . $event->description : '' }}</span></span>
                    </div>
                @empty
                    <div class="empty">Belum ada pembaruan tracking.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Rute &amp; Kargo</h2><a class="text-link" href="{{ route('map') }}">Buka peta →</a></div>
            <div class="panel-body">
                <div class="grid-two">
                    <div><span class="form-help">ASAL</span><div class="primary-text">{{ $shipment->origin?->city }}, {{ $shipment->origin?->country }}</div><span class="secondary-text">{{ $shipment->origin?->name }} · {{ $shipment->origin?->code }}</span></div>
                    <div><span class="form-help">TUJUAN</span><div class="primary-text">{{ $shipment->destination?->city }}, {{ $shipment->destination?->country }}</div><span class="secondary-text">{{ $shipment->destination?->name }} · {{ $shipment->destination?->code }}</span></div>
                </div>
                <div style="margin-top:16px"><span class="form-help">JENIS KARGO</span><div class="primary-text">{{ $shipment->cargo_type }}</div></div>
                @if ($shipment->latestLocatedEvent?->latitude && $shipment->latestLocatedEvent?->longitude)
                    <div style="margin-top:16px"><span class="form-help">LOKASI TERAKHIR</span><div class="primary-text">{{ $shipment->latestLocatedEvent->location?->name ?? 'Koordinat terakhir' }}</div><span class="secondary-text">{{ $shipment->latestLocatedEvent->latitude }}, {{ $shipment->latestLocatedEvent->longitude }}</span></div>
                @endif
            </div>
        </section>
    </div>

    @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
        @if (in_array($shipment->current_status->value, ['CREATED', 'BOOKED'], true))
            <section class="panel" style="margin-top:13px">
                <div class="panel-head"><h2>Perbarui Detail Shipment</h2><span class="text-link">Tersedia sebelum barang dijemput</span></div>
                <form class="panel-body" method="POST" action="{{ route('shipments.update', $shipment) }}">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <div class="form-group"><label for="edit-carrier">Carrier</label><select class="form-control" id="edit-carrier" name="carrier_id" required>@foreach ($carriers as $carrier)<option value="{{ $carrier->id }}" @selected($shipment->carrier_id === $carrier->id)>{{ $carrier->name }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="edit-cargo">Jenis kargo</label><input class="form-control" id="edit-cargo" name="cargo_type" value="{{ $shipment->cargo_type }}" required></div>
                        <div class="form-group"><label for="edit-departure">Keberangkatan</label><input class="form-control" id="edit-departure" type="datetime-local" name="expected_departure_at" value="{{ $shipment->expected_departure_at?->format('Y-m-d\TH:i') }}" required></div>
                        <div class="form-group"><label for="edit-arrival">Estimasi tiba</label><input class="form-control" id="edit-arrival" type="datetime-local" name="expected_arrival_at" value="{{ $shipment->expected_arrival_at?->format('Y-m-d\TH:i') }}" required></div>
                    </div>
                    <div class="toolbar" style="justify-content:flex-end;margin-top:15px"><button class="btn primary" type="submit">Simpan Perubahan</button></div>
                </form>
            </section>
        @endif
        @if (! $shipment->current_status->isTerminal())
            <section class="panel" style="margin-top:13px">
                <div class="panel-head"><h2>Tambah Pembaruan Tracking</h2><span class="text-link">Dicatat ke riwayat shipment</span></div>
                <form class="panel-body" method="POST" action="{{ route('shipments.tracking-events.store', $shipment) }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-group"><label for="event_type">Jenis pembaruan</label><select class="form-control" id="event_type" name="event_type" required>@foreach ($manualEventTypes as $eventType)<option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ str($eventType)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="event-location">Lokasi</label><select class="form-control" id="event-location" name="location_id"><option value="">Pilih lokasi</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->city }}, {{ $location->country }} — {{ $location->name }}</option>@endforeach</select></div>
                        <div class="form-group"><label for="occurred_at">Waktu kejadian</label><input class="form-control" id="occurred_at" type="datetime-local" name="occurred_at" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}"></div>
                        <div class="form-group"><label for="estimated_arrival_at">Estimasi tiba baru <span class="form-help">(opsional)</span></label><input class="form-control" id="estimated_arrival_at" type="datetime-local" name="estimated_arrival_at"></div>
                        <div class="form-group full"><label for="event-description">Catatan</label><textarea class="form-control" id="event-description" name="description" maxlength="2000">{{ old('description') }}</textarea></div>
                    </div>
                    <div class="toolbar" style="justify-content:flex-end;margin-top:15px"><button class="btn primary" type="submit">Simpan Pembaruan</button></div>
                </form>
            </section>
        @endif
    @endif
@endsection
