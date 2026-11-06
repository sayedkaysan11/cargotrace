@extends('layouts.portal')

@section('title', 'Reports & Analytics')

@section('content')
    <div class="page-heading">
        <div><h1>Reports &amp; Analytics</h1><p>Ringkasan operasional berdasarkan shipment yang dapat diakses akunmu.</p></div>
        <div class="toolbar"><a class="btn {{ request()->routeIs('reports.*') ? 'primary' : '' }}" href="{{ route('reports.index') }}">Reports</a><a class="btn {{ request()->routeIs('analytics.*') ? 'primary' : '' }}" href="{{ route('analytics.index') }}">Analytics</a></div>
    </div>
    <section class="stat-grid">
        <article class="stat-card"><small>Total Shipment</small><strong>{{ number_format($total) }}</strong></article>
        <article class="stat-card"><small>Dalam Perjalanan</small><strong>{{ number_format($statuses->get('IN_TRANSIT', 0) + $statuses->get('PICKED_UP', 0)) }}</strong></article>
        <article class="stat-card"><small>Terkirim</small><strong>{{ number_format($statuses->get('DELIVERED', 0)) }}</strong></article>
        <article class="stat-card"><small>Perlu Perhatian</small><strong>{{ number_format($delayedCount + $exceptionsCount) }}</strong><span class="secondary-text">{{ $delayedCount }} terlambat · {{ $exceptionsCount }} exception</span></article>
    </section>
    <div class="grid-two">
        <section class="panel">
            <div class="panel-head"><h2>Shipment per Bulan</h2><span class="text-link">6 bulan terakhir</span></div>
            <div class="panel-body">
                <div class="bar-chart" aria-label="Grafik shipment dibuat per bulan">
                    @foreach ($months as $month)
                        <div class="bar-column"><strong>{{ $month['count'] }}</strong><i style="height:{{ max(3, (int) round($month['count'] / $maxMonthlyCount * 75)) }}%"></i><span>{{ $month['label'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Shipment berdasarkan Status</h2><span class="text-link">Ringkasan terkini</span></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Status</th><th>Jumlah</th><th>Persentase</th></tr></thead><tbody>
                @foreach ($statusCases as $status)
                    @php $count = (int) $statuses->get($status->value, 0); $percentage = $total ? round($count / $total * 100) : 0; @endphp
                    <tr><td><span class="status {{ $status->value === 'DELIVERED' ? 'delivered' : (in_array($status->value, ['CANCELLED', 'CREATED']) ? 'other' : '') }}">{{ str($status->value)->replace('_', ' ')->title() }}</span></td><td>{{ number_format($count) }}</td><td>{{ $percentage }}%</td></tr>
                @endforeach
            </tbody></table></div>
        </section>
    </div>
    <section class="panel" style="margin-top:13px">
        <div class="panel-head"><h2>Performa Carrier</h2><span class="text-link">{{ $carrierStats->count() }} carrier</span></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Carrier</th><th>Shipment</th><th>Distribusi</th><th></th></tr></thead><tbody>
            @forelse ($carrierStats as $carrier)
                @php $share = $total ? round($carrier->visible_shipments_count / $total * 100) : 0; @endphp
                <tr><td class="primary-text">{{ $carrier->name }}<span class="secondary-text">{{ $carrier->code }}</span></td><td>{{ number_format($carrier->visible_shipments_count) }}</td><td>{{ $share }}%</td><td><div style="width:{{ max(3, $share) }}%;max-width:160px;height:7px;border-radius:6px;background:linear-gradient(90deg,#2874ff,#45d6ed)"></div></td></tr>
            @empty
                <tr><td colspan="4"><div class="empty">Belum ada data carrier.</div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
