@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<div class="card p-3" style="max-width:420px">
    <form method="post" action="{{ route('password') }}">
        @csrf
        <label class="form-label">Current password</label>
        <input class="form-control mb-3" type="password" name="current_password" required>
        <label class="form-label">New password (at least 6 characters)</label>
        <input class="form-control mb-3" type="password" name="new_password" required>
        <label class="form-label">New password again</label>
        <input class="form-control mb-3" type="password" name="new_password_confirmation" required>
        <button class="btn btn-success">Change password</button>
    </form>
</div>
@endsection
