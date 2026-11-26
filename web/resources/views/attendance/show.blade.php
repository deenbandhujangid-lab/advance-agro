@extends('layouts.app')
@section('title', $att->user->name.' - '.$att->date->format('d M Y'))
@push('head')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush
@section('content')
<a href="{{ route('attendance.daily', ['date' => $att->date->toDateString()]) }}">&larr; Back to attendance</a>
<div class="row g-3 mt-1">
    <div class="col-lg-5">
        <div class="card p-3 mb-3">
            <div class="d-flex justify-content-between"><span>Login</span><b>{{ $att->login_at->format('h:i A') }}</b></div>
            <div class="d-flex justify-content-between"><span>Logout</span>
                <b>{{ $att->logout_at?->format('h:i A') ?? ($att->isPending() ? 'Pending' : 'Still working') }}</b></div>
            <div class="d-flex justify-content-between"><span>Late</span><b>{{ $att->is_late ? 'Yes' : ($att->grace_used ? 'No (grace used)' : 'No') }}</b></div>
            <div class="d-flex justify-content-between"><span>Overtime</span><b>{{ $att->ot_minutes }} min</b></div>
            <div class="d-flex justify-content-between"><span>Total time outside</span><b>{{ $att->outsideMinutes() }} min</b></div>
            @if ($att->logout_by)
                <div class="text-muted small mt-2">Changed by {{ $att->closedBy?->name }}: {{ $att->remark }}</div>
            @endif
        </div>

        <div class="card p-3 mb-3">
            <b class="mb-2">Out and In</b>
            <table class="table table-sm mb-0">
                <thead><tr><th>Went out</th><th>Came in</th><th>Time</th><th></th></tr></thead>
                <tbody>
                @forelse ($att->movements as $m)
                    <tr>
                        <td>{{ $m->out_at->format('h:i A') }}</td>
                        <td>{{ $m->in_at?->format('h:i A') ?? 'Still outside' }}</td>
                        <td>{{ $m->minutes() }} min</td>
                        <td><span class="badge text-bg-{{ $m->allowed ? 'success' : 'danger' }}">{{ $m->allowed ? 'Allowed' : 'Without permission' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Did not go outside.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($canEdit)
            <div class="card p-3">
                <b class="mb-2">Correct time</b>
                <form method="post" action="{{ route('attendance.correct', $att) }}" class="row g-2">
                    @csrf
                    <div class="col-6">
                        <label class="form-label">Login time</label>
                        <input class="form-control" type="time" name="login_time" value="{{ old('login_time', $att->login_at->format('H:i')) }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Logout time</label>
                        <input class="form-control" type="time" name="logout_time" value="{{ old('logout_time', $att->logout_at?->format('H:i')) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason for change *</label>
                        <input class="form-control" name="remark" value="{{ old('remark') }}" required>
                    </div>
                    <div class="col-12"><button class="btn btn-success">Save corrected time</button></div>
                </form>
            </div>
        @endif
    </div>
    <div class="col-lg-7">
        <div class="card p-2">
            <div id="map" style="height:520px"></div>
            <div class="small text-muted p-2">Green circle = office area ({{ $office['radius'] }} m). Blue line = route outside the office.</div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const office = @json($office);
    const login = @json([$att->login_lat ? (float) $att->login_lat : null, $att->login_lng ? (float) $att->login_lng : null]);
    const logout = @json([$att->logout_lat ? (float) $att->logout_lat : null, $att->logout_lng ? (float) $att->logout_lng : null]);
    const points = @json($att->points->map(fn ($p) => [$p->lat, $p->lng, $p->recorded_at->format('h:i A')])->values());

    const centre = (office.lat || office.lng) ? [office.lat, office.lng] : (login[0] ? login : [22.5, 78.9]);
    const map = L.map('map').setView(centre, 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(map);

    const shown = [];
    if (office.lat || office.lng) {
        L.circle([office.lat, office.lng], {radius: office.radius, color: '#16a34a'}).addTo(map).bindPopup('Office');
        shown.push([office.lat, office.lng]);
    }
    if (login[0]) { L.marker(login).addTo(map).bindPopup('Login'); shown.push(login); }
    if (logout[0]) { L.marker(logout).addTo(map).bindPopup('Logout'); shown.push(logout); }
    if (points.length) {
        L.polyline(points.map(p => [p[0], p[1]]), {color: '#2563eb'}).addTo(map);
        points.forEach(p => { L.circleMarker([p[0], p[1]], {radius: 4, color: '#2563eb'}).addTo(map).bindPopup(p[2]); shown.push([p[0], p[1]]); });
    }
    if (shown.length > 1) map.fitBounds(shown, {padding: [30, 30], maxZoom: 18});
</script>
@endpush
