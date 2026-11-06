@extends('layouts.portal')

@section('title', 'Buat Shipment')

@section('content')
    <div class="page-heading">
        <div><h1>Buat Shipment</h1><p>Masukkan informasi pengiriman untuk membuat shipment baru.</p></div>
        <a class="btn subtle" href="{{ route('shipments.index') }}">← Kembali</a>
    </div>

    <form class="panel" method="POST" action="{{ route('shipments.store') }}">
        @csrf
        <div class="panel-head"><h2>Informasi pengiriman</h2><span class="text-link">Data customer dan rute</span></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="customer_id">Customer</label>
                    <select class="form-control" id="customer_id" name="customer_id" required>
                        <option value="">Pilih customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->company_name ?: $customer->name }} — {{ $customer->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="carrier_id">Carrier</label>
                    <select class="form-control" id="carrier_id" name="carrier_id" required>
                        <option value="">Pilih carrier</option>
                        @foreach ($carriers as $carrier)
                            <option value="{{ $carrier->id }}" @selected(old('carrier_id') == $carrier->id)>{{ $carrier->name }} ({{ $carrier->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="origin_location_id">Asal</label>
                    <select class="form-control" id="origin_location_id" name="origin_location_id" required>
                        <option value="">Pilih lokasi asal</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('origin_location_id') == $location->id)>{{ $location->city }}, {{ $location->country }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="destination_location_id">Tujuan</label>
                    <select class="form-control" id="destination_location_id" name="destination_location_id" required>
                        <option value="">Pilih lokasi tujuan</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('destination_location_id') == $location->id)>{{ $location->city }}, {{ $location->country }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="cargo_type">Jenis kargo</label>
                    <input class="form-control" id="cargo_type" name="cargo_type" maxlength="60" value="{{ old('cargo_type') }}" placeholder="Contoh: Elektronik" required>
                </div>
                <div class="form-group">
                    <label for="scenario">Kategori operasional</label>
                    <select class="form-control" id="scenario" name="scenario">
                        <option value="A" @selected(old('scenario', 'A') === 'A')>A — Standard</option>
                        <option value="B" @selected(old('scenario') === 'B')>B — Priority</option>
                        <option value="C" @selected(old('scenario') === 'C')>C — Special handling</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="expected_departure_at">Jadwal keberangkatan</label>
                    <input class="form-control" id="expected_departure_at" type="datetime-local" name="expected_departure_at" value="{{ old('expected_departure_at') }}" required>
                </div>
                <div class="form-group">
                    <label for="expected_arrival_at">Estimasi tiba</label>
                    <input class="form-control" id="expected_arrival_at" type="datetime-local" name="expected_arrival_at" value="{{ old('expected_arrival_at') }}" required>
                </div>
            </div>
        </div>
        <div class="panel-head"><h2>Detail barang</h2><span class="text-link">Minimal satu item</span></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-group full"><label for="item_description">Deskripsi</label><input class="form-control" id="item_description" name="items[0][description]" value="{{ old('items.0.description') }}" required></div>
                <div class="form-group"><label for="item_hs">Kode HS <span class="form-help">(opsional)</span></label><input class="form-control" id="item_hs" name="items[0][hs_code]" value="{{ old('items.0.hs_code') }}"></div>
                <div class="form-group"><label for="item_quantity">Jumlah</label><input class="form-control" id="item_quantity" type="number" min="1" name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" required></div>
                <div class="form-group"><label for="item_weight">Berat (kg)</label><input class="form-control" id="item_weight" type="number" min="0" step="0.01" name="items[0][weight_kg]" value="{{ old('items.0.weight_kg', 0) }}" required></div>
                <div class="form-group"><label for="item_volume">Volume (m³)</label><input class="form-control" id="item_volume" type="number" min="0" step="0.01" name="items[0][volume_cbm]" value="{{ old('items.0.volume_cbm', 0) }}" required></div>
            </div>
            <div class="toolbar" style="justify-content:flex-end;margin-top:18px">
                <a class="btn subtle" href="{{ route('shipments.index') }}">Batal</a>
                <button class="btn primary" type="submit">Buat Shipment</button>
            </div>
        </div>
    </form>
@endsection
