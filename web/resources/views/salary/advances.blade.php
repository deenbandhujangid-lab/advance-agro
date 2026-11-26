@extends('layouts.app')
@section('title', 'Advances')
@section('content')
<p class="text-muted">Due amount = advance given - amount recovered. The deduction per month is cut from salary automatically until the due amount is zero.</p>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th class="text-end">Basic salary</th><th class="text-end">Advance given</th><th class="text-end">Recovered</th><th class="text-end">Due amount</th><th>Deduction per month</th><th></th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['user']->emp_code }}</td>
                    <td>{{ $r['user']->name }}</td>
                    <td class="text-end">{{ number_format($r['user']->basic_salary, 2) }}</td>
                    <td class="text-end">{{ number_format($r['given'], 2) }}</td>
                    <td class="text-end">{{ number_format($r['back'], 2) }}</td>
                    <td class="text-end"><b class="{{ $r['due'] > 0 ? 'text-danger' : '' }}">{{ number_format($r['due'], 2) }}</b></td>
                    <td>
                        <form method="post" action="{{ route('salary.deduction', $r['user']) }}" class="d-flex gap-1">
                            @csrf
                            <input class="form-control form-control-sm" style="width:110px" type="number" step="0.01" min="0" name="deduction_per_month" value="{{ $r['user']->deduction_per_month }}">
                            <button class="btn btn-sm btn-outline-secondary">Save</button>
                        </form>
                    </td>
                    <td><a class="btn btn-sm btn-success" href="{{ route('salary.statement', $r['user']) }}">Give advance / Statement</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No employees.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
