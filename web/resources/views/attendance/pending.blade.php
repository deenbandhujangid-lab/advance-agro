@extends('layouts.app')
@section('title', 'Pending logouts')
@section('content')
<p class="text-muted">These employees did not logout. They cannot login again until you enter the logout time.</p>
@forelse ($pending as $att)
    <div class="card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
            <div>
                <b>{{ $att->user->name }}</b> ({{ $att->user->emp_code }})
                <div>{{ $att->date->format('d M Y') }} &middot; Logged in at <b>{{ $att->login_at->format('h:i A') }}</b>
                    &middot; Shift ends {{ \Carbon\Carbon::parse($att->user->evening_time)->format('h:i A') }}</div>
            </div>
            <form method="post" action="{{ route('attendance.close', $att) }}" class="d-flex flex-wrap gap-2 align-items-end">
                @csrf
                <div>
                    <label class="form-label mb-0 small">Logout time *</label>
                    <input class="form-control" type="time" name="logout_time" value="{{ substr($att->user->evening_time, 0, 5) }}" required>
                </div>
                <div>
                    <label class="form-label mb-0 small">Remark *</label>
                    <input class="form-control" name="remark" value="Forgot to logout" required>
                </div>
                <button class="btn btn-success">Save logout time</button>
            </form>
        </div>
        @error('logout_time_'.$att->id) <div class="text-danger mt-2">{{ $message }}</div> @enderror
    </div>
@empty
    <div class="card p-4 text-center text-muted">No pending logouts. Everyone is clear.</div>
@endforelse
@endsection
