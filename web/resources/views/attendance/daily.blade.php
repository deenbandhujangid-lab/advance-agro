@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
@use('App\Services\AttendanceService', 'A')
<form class="d-flex gap-2 mb-3" method="get">
    <input class="form-control" style="max-width:200px" type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}">
    <button class="btn btn-outline-secondary">Show</button>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Login</th><th>Logout</th><th>Status</th><th>Worked</th><th>Outside</th><th>Overtime</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                @php $att = $r['att']; $code = $r['code']; @endphp
                <tr>
                    <td>{{ $r['user']->emp_code }}</td>
                    <td>{{ $r['user']->name }}</td>
                    <td>{{ ucfirst((string) $r['user']->emp_type) }}</td>
                    <td>{{ $att?->login_at->format('h:i A') ?? '-' }}
                        @if ($att?->grace_used) <span class="badge text-bg-light border">Grace</span> @endif</td>
                    <td>{{ $att?->logout_at?->format('h:i A') ?? '-' }}
                        @if ($att?->logout_by) <span class="badge text-bg-light border">By manager</span> @endif</td>
                    <td>
                        @if ($code !== '-')
                            <span class="badge text-bg-{{ A::COLORS[$code] }}">{{ A::CODES[$code] }}</span>
                        @elseif ($att)
                            <span class="badge text-bg-success">Working</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $att && $att->logout_at ? intdiv($att->workedMinutes(), 60).'h '.($att->workedMinutes() % 60).'m' : '-' }}</td>
                    <td>{{ $att && $att->movements->count() ? $att->outsideMinutes().' min' : '-' }}</td>
                    <td>{{ $att && $att->ot_minutes ? $att->ot_minutes.' min' : '-' }}</td>
                    <td>@if ($att) <a class="btn btn-sm btn-outline-primary" href="{{ route('attendance.show', $att) }}">Details</a> @endif</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No employees.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
