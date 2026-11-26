@extends('layouts.app')
@section('title', 'Settings')
@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush
@section('content')
@php $v = fn (string $key) => old($key, $s[$key]); @endphp
<form method="post" action="{{ route('settings.update') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card p-3">
                <h6>Office location</h6>
                <p class="text-muted small mb-2">Click on the map at the office, or press the button while you are sitting in the office.</p>
                <div id="map" style="height:340px" class="mb-2"></div>
                <button type="button" class="btn btn-sm btn-outline-success mb-3" style="width:max-content" id="here">Use my current location</button>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label">Office name</label><input class="form-control" name="office_name" value="{{ $v('office_name') }}" required></div>
                    <div class="col-md-3"><label class="form-label">Latitude</label><input class="form-control" name="office_lat" id="lat" value="{{ $v('office_lat') }}" required></div>
                    <div class="col-md-3"><label class="form-label">Longitude</label><input class="form-control" name="office_lng" id="lng" value="{{ $v('office_lng') }}" required></div>
                    <div class="col-md-2"><label class="form-label">Area (m)</label><input class="form-control" type="number" name="office_radius" id="radius" value="{{ $v('office_radius') }}" required></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-3">
                <h6>Rules</h6>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Grace minutes</label><input class="form-control" type="number" name="grace_minutes" value="{{ $v('grace_minutes') }}" required></div>
                    <div class="col-6"><label class="form-label">Grace days per month</label><input class="form-control" type="number" name="grace_days" value="{{ $v('grace_days') }}" required></div>
                    <div class="col-6"><label class="form-label">Overtime counts from (minutes)</label><input class="form-control" type="number" name="ot_min_minutes" value="{{ $v('ot_min_minutes') }}" required></div>
                    <div class="col-6"><label class="form-label">Overtime rate: days in month</label><input class="form-control" type="number" name="ot_days" value="{{ $v('ot_days') }}" required></div>
                    <div class="col-6"><label class="form-label">Casual leave per year</label><input class="form-control" type="number" name="cl_quota" value="{{ $v('cl_quota') }}" required></div>
                    <div class="col-6"><label class="form-label">Sick leave per year</label><input class="form-control" type="number" name="sl_quota" value="{{ $v('sl_quota') }}" required></div>
                </div>
                <div class="text-muted small mt-2">Overtime hourly rate = basic salary / days in month / shift hours.</div>
            </div>
        </div>
    </div>
    <button class="btn btn-success mt-3">Save settings</button>
</form>

<h6 class="mt-4">Holidays</h6>
<div class="card p-3" style="max-width:640px">
    <form method="post" action="{{ route('holidays.store') }}" class="d-flex flex-wrap gap-2 mb-3">
        @csrf
        <input class="form-control" style="width:170px" type="date" name="date" required>
        <input class="form-control" style="width:240px" name="name" placeholder="Holiday name" required>
        <button class="btn btn-success">Add holiday</button>
    </form>
    <table class="table table-sm mb-0">
        @forelse ($holidays as $h)
            <tr>
                <td>{{ $h->date->format('d M Y (D)') }}</td>
                <td>{{ $h->name }}</td>
                <td class="text-end">
                    <form method="post" action="{{ route('holidays.delete', $h) }}" onsubmit="return confirm('Remove this holiday?')">
                        @csrf @method('delete') <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td class="text-muted">No holidays added.</td></tr>
        @endforelse
    </table>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const lat = document.getElementById('lat'), lng = document.getElementById('lng'), radius = document.getElementById('radius');
    const isSet = parseFloat(lat.value) !== 0 || parseFloat(lng.value) !== 0;
    const map = L.map('map').setView(isSet ? [lat.value, lng.value] : [22.5, 78.9], isSet ? 18 : 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);
    let circle = isSet ? L.circle([lat.value, lng.value], {radius: radius.value, color: '#16a34a'}).addTo(map) : null;

    function place(a, b, zoom) {
        lat.value = (+a).toFixed(7); lng.value = (+b).toFixed(7);
        if (circle) circle.remove();
        circle = L.circle([a, b], {radius: radius.value || 35, color: '#16a34a'}).addTo(map);
        if (zoom) map.setView([a, b], 18);
    }
    map.on('click', e => place(e.latlng.lat, e.latlng.lng, false));
    radius.addEventListener('input', () => circle && circle.setRadius(radius.value || 35));
    document.getElementById('here').addEventListener('click', () => {
        if (!navigator.geolocation) return alert('This browser cannot give location.');
        navigator.geolocation.getCurrentPosition(p => place(p.coords.latitude, p.coords.longitude, true),
            () => alert('Could not get location. Allow location for this page and try again.'), {enableHighAccuracy: true});
    });
</script>
@endpush
