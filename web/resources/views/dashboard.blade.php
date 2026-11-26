@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $cards = [
        'present' => ['Present', 'green', 'bi-person-check'],
        'absent' => ['Not logged in', 'red', 'bi-person-x'],
        'late' => ['Late', 'amber', 'bi-alarm'],
        'leave' => ['On leave', 'blue', 'bi-calendar-x'],
        'outside' => ['Outside now', 'amber', 'bi-geo-alt'],
        'pending' => ['Logout pending', 'red', 'bi-exclamation-circle'],
    ];
@endphp
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <div class="fw-semibold fs-5">Today, {{ now()->format('l d M Y') }}</div>
        <div class="text-muted">Click a card to see the names.</div>
    </div>
    <a class="btn btn-light" href="{{ route('attendance.daily') }}"><i class="bi bi-calendar-check"></i> Open attendance</a>
</div>

<div class="row g-3 mb-3">
    @foreach ($cards as $key => [$label, $tone, $icon])
        <div class="col-6 col-md-4 col-xxl-2">
            <a class="card stat" data-bs-toggle="collapse" href="#list-{{ $key }}">
                <span class="ico {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                <span>
                    <span class="num d-block">{{ count($lists[$key]) }}</span>
                    <span class="lbl d-block">{{ $label }}</span>
                </span>
            </a>
        </div>
    @endforeach
</div>

@foreach ($cards as $key => [$label, $tone, $icon])
    <div class="collapse mb-3" id="list-{{ $key }}">
        <div class="card p-3">
            <h6><i class="bi {{ $icon }} me-1"></i> {{ $label }}</h6>
            @forelse ($lists[$key] as $name)
                <div class="kv"><span>{{ $name }}</span></div>
            @empty
                <div class="text-muted">Nobody.</div>
            @endforelse
            @if ($key === 'pending' && count($lists[$key]) && auth()->user()->role !== 'account_manager')
                <a class="btn btn-sm btn-success mt-3" style="width:max-content" href="{{ route('attendance.pending') }}">Enter logout time</a>
            @endif
        </div>
    </div>
@endforeach

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card p-3 h-100">
            <h6>Waiting for approval</h6>
            <div class="kv"><span><i class="bi bi-calendar-x me-2 text-muted"></i>Leave requests</span><span class="badge text-bg-{{ $pendingLeaves ? 'warning' : 'light' }}">{{ $pendingLeaves }}</span></div>
            <div class="kv"><span><i class="bi bi-award me-2 text-muted"></i>CCL credits</span><span class="badge text-bg-{{ $pendingCcl ? 'warning' : 'light' }}">{{ $pendingCcl }}</span></div>
            <a class="btn btn-outline-success btn-sm mt-3" style="width:max-content" href="{{ route('leave.index') }}">Open leave</a>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card p-3 h-100">
            <h6>Alerts</h6>
            @forelse ($notices as $n)
                <div class="kv">
                    <span><span class="fw-semibold">{{ $n->title }}</span><br><span class="text-muted">{{ $n->body }}</span></span>
                    <span class="text-muted small text-nowrap">{{ $n->created_at->format('d M, h:i A') }}</span>
                </div>
            @empty
                <div class="empty"><i class="bi bi-bell-slash"></i>No alerts.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
