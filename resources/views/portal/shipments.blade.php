@extends('layouts.portal')

@section('title', 'Shipments')

@section('content')
    <div class="page-heading">
        <div><h1>Shipments</h1><p>Manage and monitor all shipments in your logistics network.</p></div>
        @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
            <a class="btn primary" href="{{ route('shipments.create') }}">＋ Create Shipment</a>
        @endif
    </div>

    <section class="panel">
        <div class="panel-head"><h2>All Shipments</h2><span class="text-link">{{ number_format($shipments->total()) }} shipments</span></div>
        <div class="panel-body">
            <form class="toolbar" method="GET" action="{{ route('shipments.index') }}">
                <div class="filters">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by shipment, customer, carrier...">
                    <select name="status" aria-label="Filter status">
                        <option value="">All Status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    <select name="carrier_id" aria-label="Filter carrier">
                        <option value="">All Carriers</option>
                        @foreach ($carriers as $carrier)
                            <option value="{{ $carrier->id }}" @selected((string) ($filters['carrier_id'] ?? '') === (string) $carrier->id)>{{ $carrier->name }}</option>
                        @endforeach
                    </select>
                    <select name="is_delayed" aria-label="Filter keterlambatan">
                        <option value="">Delay Range</option>
                        <option value="1" @selected(($filters['is_delayed'] ?? '') === '1')>Delayed</option>
                        <option value="0" @selected(($filters['is_delayed'] ?? '') === '0')>On Time</option>
                    </select>
                </div>
                <button class="btn primary" type="submit">Filter</button>
                <a class="btn subtle" href="{{ route('shipments.index') }}">Reset</a>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Shipment No.</th><th>Customer</th><th>Route</th><th>Cargo / Weight</th><th>Carrier</th><th>Status</th><th>ETA</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse ($shipments as $shipment)
                    @php
                        $statusValue = $shipment->current_status->value;
                        $statusClass = $shipment->is_delayed ? 'delayed' : ($statusValue === 'DELIVERED' ? 'delivered' : (in_array($statusValue, ['CANCELLED', 'CREATED']) ? 'other' : ''));
                    @endphp
                    <tr>
                        <td><a class="primary-text" href="{{ route('shipments.show', $shipment) }}">{{ $shipment->shipment_number }}</a><span class="secondary-text">{{ $shipment->created_at->format('d M Y') }}</span></td>
                        <td>{{ $shipment->customer?->company_name ?: $shipment->customer?->name }}<span class="secondary-text">{{ $shipment->customer?->email }}</span></td>
                        <td class="route-cell">{{ $shipment->origin?->city ?? $shipment->origin?->name }}<span>→</span>{{ $shipment->destination?->city ?? $shipment->destination?->name }}</td>
                        <td>{{ $shipment->cargo_type }}<span class="secondary-text">{{ number_format((float) $shipment->total_weight_kg, 1) }} kg</span></td>
                        <td>{{ $shipment->carrier?->name ?? '—' }}</td>
                        <td><span class="status {{ $statusClass }}">{{ $shipment->is_delayed ? 'Terlambat' : str($statusValue)->replace('_', ' ')->title() }}</span></td>
                        <td>{{ ($shipment->estimated_arrival_at ?? $shipment->expected_arrival_at)?->format('d M Y') ?? '—' }}</td>
                        <td><a class="btn" href="{{ route('tracking.show', $shipment) }}" aria-label="View {{ $shipment->shipment_number }}">View →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">{{ isset($filters['search']) ? 'No shipments match your search.' : 'No shipments available.' }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $shipments->links() }}</div>
    </section>
@endsection
