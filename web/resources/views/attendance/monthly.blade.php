@extends('layouts.app')
@section('title', 'Monthly sheet')
@section('content')
@use('App\Services\AttendanceService', 'A')
<form class="d-flex gap-2 mb-2" method="get">
    <input class="form-control" style="max-width:200px" type="month" name="month" value="{{ $month }}" max="{{ today()->format('Y-m') }}">
    <button class="btn btn-outline-secondary">Show</button>
</form>
<div class="mb-2 small">
    @foreach (A::CODES as $code => $label)
        <span class="code text-bg-{{ A::COLORS[$code] }}">{{ $code }}</span> {{ $label }} &nbsp;
    @endforeach
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" style="font-size:.8rem">
            <thead>
            <tr>
                <th style="min-width:150px">Employee</th>
                @for ($d = 1; $d <= $daysInMonth; $d++) <th class="text-center">{{ $d }}</th> @endfor
                <th>P</th><th>A</th><th>OT h</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['user']->name }}<div class="text-muted">{{ $r['user']->emp_code }}</div></td>
                    @foreach ($r['days'] as $code)
                        <td class="text-center p-1">@if ($code !== '-')<span class="code text-bg-{{ A::COLORS[$code] }}" title="{{ A::CODES[$code] }}">{{ $code }}</span>@endif</td>
                    @endforeach
                    <td>{{ $r['totals']['present'] }}</td>
                    <td>{{ $r['totals']['absent'] }}</td>
                    <td>{{ round($r['totals']['ot_minutes'] / 60, 1) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $daysInMonth + 4 }}" class="text-center text-muted py-4">No employees.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
