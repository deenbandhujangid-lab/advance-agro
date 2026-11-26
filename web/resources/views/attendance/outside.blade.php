@extends('layouts.app')
@section('title', 'Allow outside')
@section('content')
<p class="text-muted">Allow an employee to go outside the office area. The employee is still tracked, and you can see when they left and came back.</p>
<div class="card p-3 mb-3">
    <form method="post" action="{{ route('attendance.outside.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-3">
            <label class="form-label">Employee *</label>
            <select class="form-select" name="user_id" required>
                <option value="">Choose</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} ({{ $u->emp_code }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Date *</label>
            <input class="form-control" type="date" name="date" value="{{ old('date', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">From *</label>
            <input class="form-control" type="time" name="from_time" value="{{ old('from_time') }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">To *</label>
            <input class="form-control" type="time" name="to_time" value="{{ old('to_time') }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Reason *</label>
            <input class="form-control" name="reason" value="{{ old('reason') }}" required>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="allow_outside_logout" value="1" id="aol" @checked(old('allow_outside_logout'))>
                <label class="form-check-label" for="aol">May logout from outside (no need to come back to office)</label>
            </div>
        </div>
        <div class="col-12"><button class="btn btn-success">Allow to go outside</button></div>
    </form>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Employee</th><th>From</th><th>To</th><th>Reason</th><th>Logout outside</th><th>Given by</th><th></th></tr></thead>
            <tbody>
            @forelse ($permissions as $p)
                <tr>
                    <td>{{ $p->date->format('d M Y') }}</td>
                    <td>{{ $p->user->name }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->from_time)->format('h:i A') }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->to_time)->format('h:i A') }}</td>
                    <td>{{ $p->reason }}</td>
                    <td>{{ $p->allow_outside_logout ? 'Yes' : 'No' }}</td>
                    <td>{{ $p->creator?->name }}</td>
                    <td>
                        @if ($p->date->gte(today()))
                            <form method="post" action="{{ route('attendance.outside.delete', $p) }}" onsubmit="return confirm('Remove this permission?')">
                                @csrf @method('delete') <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No permissions given.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
