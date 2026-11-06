@extends('layouts.portal')

@section('title', 'News Detail')

@section('content')
    @php
        $shipment = $event->shipment;
        $headline = $event->description ?: str($event->event_type->value)->replace('_', ' ')->title().' — '.$shipment->shipment_number;
        $locationName = $event->location?->name ?? $event->location?->city ?? 'Lokasi tidak dicatat';
    @endphp
    <div class="page-heading">
        <div><h1>News Detail</h1><p>Informasi lengkap tentang pembaruan shipment.</p></div>
        <a class="btn subtle" href="{{ route('news.index') }}">← Kembali ke News</a>
    </div>
    <article class="panel">
        <div class="panel-body">
            <img class="news-article-image" src="{{ $event->newsImage() }}" alt="Ilustrasi {{ str($event->event_type->value)->replace('_', ' ')->lower() }}">
            <div class="news-card-body" style="padding:18px 0 0">
                <span class="news-card-type" style="position:static;align-self:start">{{ $event->is_exception ? 'Peringatan shipment' : str($event->event_type->value)->replace('_', ' ')->title() }}</span>
                <h1 class="news-card-title" style="font-size:21px">{{ $headline }}</h1>
                <div class="news-article-meta">
                    <span>◷ {{ $event->occurred_at->format('d M Y · H:i') }}</span>
                    <span>⌖ {{ $locationName }}</span>
                    @if ($event->creator)<span>Oleh {{ $event->creator->name }}</span>@endif
                </div>
                <p class="news-article-copy">{{ $event->description ?: 'Pembaruan status shipment ini dicatat di CargoTrace untuk menjaga informasi perjalanan tetap tersedia bagi tim operasional dan customer.' }}</p>
            </div>
        </div>
    </article>
    <section class="panel" style="margin-top:13px">
        <div class="panel-head"><h2>Shipment terkait</h2><a class="text-link" href="{{ route('tracking.show', $shipment) }}">Lihat tracking lengkap →</a></div>
        <div class="panel-body grid-three">
            <div><span class="form-help">SHIPMENT NUMBER</span><div class="primary-text">{{ $shipment->shipment_number }}</div><span class="secondary-text">{{ str($shipment->current_status->value)->replace('_', ' ')->title() }}</span></div>
            <div><span class="form-help">RUTE</span><div class="primary-text">{{ $shipment->origin?->city }} → {{ $shipment->destination?->city }}</div><span class="secondary-text">{{ $shipment->origin?->country }} → {{ $shipment->destination?->country }}</span></div>
            <div><span class="form-help">CARRIER</span><div class="primary-text">{{ $shipment->carrier?->name ?? 'Belum ditentukan' }}</div><span class="secondary-text">{{ $shipment->cargo_type }}</span></div>
        </div>
    </section>
    <section class="panel" style="margin-top:13px">
        <div class="panel-head"><h2>Aktivitas shipment lainnya</h2></div>
        <div class="panel-body">
            @forelse ($shipment->trackingEvents->where('id', '!=', $event->id)->sortByDesc('occurred_at')->take(4) as $relatedEvent)
                <div class="timeline-event">
                    <time>{{ $relatedEvent->occurred_at->format('d M Y H:i') }}</time><span class="event-mark">●</span>
                    <span><strong>{{ str($relatedEvent->event_type->value)->replace('_', ' ')->title() }}</strong><span class="secondary-text">{{ $relatedEvent->description ?: $relatedEvent->location?->name }}</span></span>
                </div>
            @empty
                <div class="empty">Belum ada aktivitas lain untuk shipment ini.</div>
            @endforelse
        </div>
    </section>
@endsection
