@extends('layouts.portal')

@section('title', 'Exceptions')

@section('content')
    <div class="page-heading">
        <div><h1>Exceptions</h1><p>Tinjau kendala pengiriman yang tercatat pada shipment yang dapat kamu akses.</p></div>
    </div>
    <section class="stat-grid" aria-label="Ringkasan exceptions">
        <article class="stat-card"><small>Total exception terdaftar</small><strong>{{ number_format($exceptions->total()) }}</strong></article>
        <article class="stat-card"><small>Filter status</small><strong style="font-size:15px">{{ isset($filters['status']) ? str($filters['status'])->title() : 'Semua status' }}</strong></article>
        <article class="stat-card"><small>Filter severity</small><strong style="font-size:15px">{{ isset($filters['severity']) ? str($filters['severity'])->title() : 'Semua tingkat' }}</strong></article>
        <article class="stat-card"><small>Halaman</small><strong>{{ $exceptions->currentPage() }}<span class="secondary-text">dari {{ $exceptions->lastPage() }}</span></strong></article>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Daftar Exception</h2><span class="text-link">Dari catatan sistem</span></div>
        <div class="panel-body">
            <form class="toolbar" method="GET" action="{{ route('exceptions.index') }}">
                <div class="filters">
                    <select name="status" aria-label="Filter status exception"><option value="">Semua status</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->title() }}</option>@endforeach</select>
                    <select name="severity" aria-label="Filter severity"><option value="">Semua severity</option>@foreach ($severities as $severity)<option value="{{ $severity->value }}" @selected(($filters['severity'] ?? '') === $severity->value)>{{ str($severity->value)->title() }}</option>@endforeach</select>
                </div>
                <button class="btn primary" type="submit">Terapkan</button><a class="btn subtle" href="{{ route('exceptions.index') }}">Reset</a>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table"><thead><tr><th>Shipment</th><th>Jenis kendala</th><th>Detail</th><th>Severity</th><th>Status</th><th>Terdeteksi</th><th></th></tr></thead><tbody>
            @forelse ($exceptions as $exception)
                <tr>
                    <td><a class="primary-text" href="{{ route('tracking.show', $exception->shipment) }}">{{ $exception->shipment->shipment_number }}</a><span class="secondary-text">{{ $exception->shipment->origin?->city }} → {{ $exception->shipment->destination?->city }}</span></td>
                    <td>{{ str($exception->type->value)->replace('_', ' ')->title() }}</td>
                    <td style="max-width:250px">{{ $exception->description ?: 'Tidak ada catatan tambahan' }}</td>
                    <td><span class="status {{ strtolower($exception->severity->value) }}">{{ str($exception->severity->value)->title() }}</span></td>
                    <td><span class="status {{ strtolower($exception->status->value) }}">{{ str($exception->status->value)->title() }}</span></td>
                    <td>{{ $exception->detected_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td><a class="btn" href="{{ route('tracking.show', $exception->shipment) }}">Lihat →</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty">Tidak ada exception untuk filter ini.</div></td></tr>
            @endforelse
            </tbody></table>
        </div>
        <div class="pagination">{{ $exceptions->links() }}</div>
    </section>
@endsection
