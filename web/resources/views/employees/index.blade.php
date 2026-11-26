@extends('layouts.app')
@section('title', 'Employees')
@section('content')
@php $admin = auth()->user()->isSuperAdmin(); @endphp
<div class="d-flex gap-2 mb-3">
    <form class="d-flex gap-2 flex-grow-1" method="get">
        <input class="form-control" style="max-width:320px" name="q" value="{{ $q }}" placeholder="Search name, code or contact number">
        <button class="btn btn-outline-secondary">Search</button>
    </form>
    @if ($admin)
        <a class="btn btn-success" href="{{ route('employees.create') }}">Add employee</a>
    @endif
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Role</th><th>Type</th><th>Manager</th><th>Contact</th><th>Phone</th><th>Status</th>@if ($admin)<th></th>@endif</tr></thead>
            <tbody>
            @forelse ($employees as $e)
                <tr>
                    <td>{{ $e->emp_code }}</td>
                    <td>{{ $e->name }}<div class="text-muted small">{{ $e->email }}</div></td>
                    <td>{{ $e->roleLabel() }}</td>
                    <td>{{ ucfirst((string) $e->emp_type) }}</td>
                    <td>{{ $e->manager?->name ?? '-' }}</td>
                    <td>{{ $e->phone }}</td>
                    <td>{!! $e->device_id ? '<span class="badge text-bg-success">Registered</span>' : '<span class="badge text-bg-secondary">Not yet</span>' !!}</td>
                    <td><span class="badge text-bg-{{ $e->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($e->status) }}</span></td>
                    @if ($admin)
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('employees.edit', $e) }}">Edit</a>
                            @if ($e->device_id)
                                <form class="d-inline" method="post" action="{{ route('employees.reset', $e) }}" onsubmit="return confirm('Reset the registered phone of {{ $e->name }}?')">
                                    @csrf <button class="btn btn-sm btn-outline-warning">Reset phone</button>
                                </form>
                            @endif
                            <form class="d-inline" method="post" action="{{ route('employees.toggle', $e) }}" onsubmit="return confirm('Change status of {{ $e->name }}?')">
                                @csrf <button class="btn btn-sm btn-outline-secondary">{{ $e->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No employees yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
