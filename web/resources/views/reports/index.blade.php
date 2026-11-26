@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<form class="d-flex gap-2 mb-3" method="get">
    <input class="form-control" style="max-width:200px" type="month" name="month" value="{{ $month }}" max="{{ today()->format('Y-m') }}">
    <button class="btn btn-outline-secondary">Show</button>
    <button class="btn btn-success" name="download" value="1">Download Excel (CSV)</button>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Present</th><th>Late</th><th>Grace used</th><th>Half day</th><th>Absent</th><th>Leave</th><th>Logout pending</th><th>Unpaid days</th><th>Overtime h</th><th>Outside h</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                @php $t = $r['t']; @endphp
                <tr>
                    <td>{{ $r['user']->emp_code }}</td>
                    <td>{{ $r['user']->name }}</td>
                    <td>{{ $t['present'] }}</td>
                    <td>{{ $t['late'] }}</td>
                    <td>{{ $t['grace'] }}</td>
                    <td>{{ $t['half'] }}</td>
                    <td class="{{ $t['absent'] ? 'text-danger fw-bold' : '' }}">{{ $t['absent'] }}</td>
                    <td>{{ $t['leave'] }}</td>
                    <td class="{{ $t['pending'] ? 'text-danger fw-bold' : '' }}">{{ $t['pending'] }}</td>
                    <td>{{ $t['unpaid_days'] }}</td>
                    <td>{{ round($t['ot_minutes'] / 60, 1) }}</td>
                    <td>{{ round($t['outside_minutes'] / 60, 1) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-4">No employees.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
