@extends('layouts.app')
@section('title', $employee->exists ? 'Edit employee' : 'Add employee')
@section('content')
@php
    $v = fn (string $field) => old($field, $employee->$field);
    $time = fn (string $field) => substr((string) old($field, $employee->$field), 0, 5);
    $offSundays = old('off_sundays', explode(',', (string) $employee->off_sundays));
@endphp
<form method="post" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" style="max-width:900px">
    @csrf
    @if ($employee->exists) @method('put') @endif

    <div class="card p-3 mb-3">
        <h6>Personal</h6>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Employee code *</label>
                <input class="form-control" name="emp_code" value="{{ $v('emp_code') }}" required @readonly($employee->exists)>
            </div>
            <div class="col-md-5">
                <label class="form-label">Name *</label>
                <input class="form-control" name="name" value="{{ $v('name') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contact number *</label>
                <input class="form-control" name="phone" value="{{ $v('phone') }}" maxlength="10" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input class="form-control" type="email" name="email" value="{{ $v('email') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password {{ $employee->exists ? '(leave empty to keep the same)' : '*' }}</label>
                <input class="form-control" type="text" name="password" minlength="6" @required(! $employee->exists)>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <h6>Work</h6>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Role *</label>
                <select class="form-select" name="role">
                    @foreach (['employee' => 'Employee', 'manager' => 'Manager', 'account_manager' => 'Account Manager'] as $key => $label)
                        <option value="{{ $key }}" @selected($v('role') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type *</label>
                <select class="form-select" name="emp_type">
                    <option value="operation" @selected($v('emp_type') === 'operation')>Operation (office only)</option>
                    <option value="sales" @selected($v('emp_type') === 'sales')>Sales (can work outside)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Manager</label>
                <select class="form-select" name="manager_id">
                    <option value="">None (Super Admin)</option>
                    @foreach ($managers as $m)
                        <option value="{{ $m->id }}" @selected((string) $v('manager_id') === (string) $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Joining date *</label>
                <input class="form-control" type="date" name="joined_on" value="{{ old('joined_on', optional($employee->joined_on)->toDateString()) }}" required>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <h6>Timing</h6>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Morning time *</label>
                <input class="form-control" type="time" name="morning_time" value="{{ $time('morning_time') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Evening time *</label>
                <input class="form-control" type="time" name="evening_time" value="{{ $time('evening_time') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sunday rule *</label>
                <select class="form-select" name="sunday_rule" id="sunday_rule">
                    <option value="all" @selected($v('sunday_rule') === 'all')>All Sundays off</option>
                    <option value="two" @selected($v('sunday_rule') === 'two')>Only 2 Sundays off</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label d-block">Overtime</label>
                <div class="form-check mt-2">
                    <input type="hidden" name="ot_allowed" value="0">
                    <input class="form-check-input" type="checkbox" name="ot_allowed" value="1" id="ot" @checked($v('ot_allowed'))>
                    <label class="form-check-label" for="ot">Overtime allowed</label>
                </div>
            </div>
            <div class="col-12" id="two_sundays">
                <label class="form-label d-block">Which 2 Sundays are off?</label>
                @foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'] as $n => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="off_sundays[]" value="{{ $n }}" id="sun{{ $n }}" @checked(in_array($n, array_map('intval', (array) $offSundays)))>
                        <label class="form-check-label" for="sun{{ $n }}">{{ $label }} Sunday</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <h6>Salary</h6>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Basic salary per month (Rs) *</label>
                <input class="form-control" type="number" step="0.01" min="0" name="basic_salary" value="{{ $v('basic_salary') ?? 0 }}" required>
            </div>
            <div class="col-md-8 text-muted small d-flex align-items-end">
                Advance, deduction per month and due amount are managed in Salary &gt; Advances.
            </div>
        </div>
    </div>

    <button class="btn btn-success">Save employee</button>
    <a class="btn btn-link" href="{{ route('employees.index') }}">Cancel</a>
</form>
@endsection
@push('scripts')
<script>
    const rule = document.getElementById('sunday_rule'), two = document.getElementById('two_sundays');
    const show = () => two.style.display = rule.value === 'two' ? '' : 'none';
    rule.addEventListener('change', show); show();
</script>
@endpush
