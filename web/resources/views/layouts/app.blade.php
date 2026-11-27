<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - GK Buildcon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('app.css') }}?v=2" rel="stylesheet">
    @stack('head')
</head>
<body>
@php
    $me = auth()->user();
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'active' : '';
    $canAct = in_array($me->role, ['super_admin', 'manager']);
    $canMoney = in_array($me->role, ['super_admin', 'account_manager']);
    $initials = collect(explode(' ', $me->name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->implode('');
@endphp
<div class="shell">
    <aside class="side" id="side">
        <div class="brand"><span class="logo"><i class="bi bi-flower1"></i></span> GK Buildcon</div>
        <nav>
            <a class="{{ $is('dashboard') }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a class="{{ $is('employees.*') }}" href="{{ route('employees.index') }}"><i class="bi bi-people"></i> Employees</a>

            <div class="group">Attendance</div>
            <a class="{{ $is('attendance.daily', 'attendance.show') }}" href="{{ route('attendance.daily') }}"><i class="bi bi-calendar-check"></i> Daily attendance</a>
            <a class="{{ $is('attendance.monthly') }}" href="{{ route('attendance.monthly') }}"><i class="bi bi-table"></i> Monthly sheet</a>
            @if ($canAct)
                <a class="{{ $is('attendance.pending') }}" href="{{ route('attendance.pending') }}"><i class="bi bi-exclamation-circle"></i> Pending logouts</a>
                <a class="{{ $is('attendance.outside') }}" href="{{ route('attendance.outside') }}"><i class="bi bi-signpost-split"></i> Allow outside</a>
            @endif
            <a class="{{ $is('leave.*') }}" href="{{ route('leave.index') }}"><i class="bi bi-calendar-x"></i> Leave</a>

            @if ($canMoney)
                <div class="group">Salary</div>
                <a class="{{ $is('salary.advances', 'salary.statement') }}" href="{{ route('salary.advances') }}"><i class="bi bi-cash-coin"></i> Advances</a>
                <a class="{{ $is('salary.sheet') }}" href="{{ route('salary.sheet') }}"><i class="bi bi-receipt"></i> Salary sheet</a>
            @endif

            <div class="group">More</div>
            <a class="{{ $is('reports.*') }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart"></i> Reports</a>
            @if ($me->isSuperAdmin())
                <a class="{{ $is('settings.*') }}" href="{{ route('settings.edit') }}"><i class="bi bi-gear"></i> Settings</a>
            @endif
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-light d-lg-none me-2" type="button" onclick="document.getElementById('side').classList.toggle('open')"><i class="bi bi-list"></i></button>
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="ms-auto d-flex align-items-center gap-2">
                <div class="text-end d-none d-sm-block lh-sm">
                    <div class="fw-semibold">{{ $me->name }}</div>
                    <div class="text-muted small">{{ $me->roleLabel() }}</div>
                </div>
                <div class="avatar">{{ $initials }}</div>
                <a class="btn btn-light btn-sm" href="{{ route('password') }}" title="Change password"><i class="bi bi-key"></i></a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-light btn-sm" title="Sign out"><i class="bi bi-box-arrow-right"></i> Sign out</button>
                </form>
            </div>
        </header>

        <div class="content">
            @if (session('ok'))
                <div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill"></i> {{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @yield('content')
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
