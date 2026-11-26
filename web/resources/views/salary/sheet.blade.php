@extends('layouts.app')
@section('title', 'Salary sheet')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <form class="d-flex gap-2" method="get">
        <input class="form-control" style="max-width:200px" type="month" name="month" value="{{ $month }}" max="{{ today()->format('Y-m') }}">
        <button class="btn btn-outline-secondary">Show</button>
    </form>
    @if ($finalized)
        <span class="badge text-bg-success fs-6">Finalized</span>
    @else
        <form method="post" action="{{ route('salary.generate') }}">
            @csrf <input type="hidden" name="month" value="{{ $month }}">
            <button class="btn btn-success">{{ $sheets->count() ? 'Generate again' : 'Generate salary sheet' }}</button>
        </form>
        @if ($sheets->count())
            <form method="post" action="{{ route('salary.finalize') }}" onsubmit="return confirm('Finalize salary for this month? Attendance of this month cannot be changed after this.')">
                @csrf <input type="hidden" name="month" value="{{ $month }}">
                <button class="btn btn-danger">Finalize</button>
            </form>
        @endif
    @endif
</div>
<p class="text-muted">Salary to pay = Basic - unpaid days amount + overtime amount - advance deduction.</p>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th class="text-end">Basic</th><th class="text-end">Unpaid days</th><th class="text-end">Unpaid amount</th><th class="text-end">Overtime</th><th class="text-end">Advance cut</th><th class="text-end">Salary to pay</th></tr></thead>
            <tbody>
            @forelse ($sheets as $s)
                <tr>
                    <td>{{ $s->user->emp_code }}</td>
                    <td>{{ $s->user->name }}</td>
                    <td class="text-end">{{ number_format($s->basic, 2) }}</td>
                    <td class="text-end">{{ $s->unpaid_days }}</td>
                    <td class="text-end">- {{ number_format($s->unpaid_amount, 2) }}</td>
                    <td class="text-end">+ {{ number_format($s->ot_amount, 2) }} <span class="text-muted small">({{ round($s->ot_minutes / 60, 1) }} h)</span></td>
                    <td class="text-end">- {{ number_format($s->advance_deduction, 2) }}</td>
                    <td class="text-end"><b>{{ number_format($s->net, 2) }}</b></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No salary sheet for this month yet. Press "Generate salary sheet".</td></tr>
            @endforelse
            </tbody>
            @if ($sheets->count())
                <tfoot><tr><th colspan="7" class="text-end">Total to pay</th><th class="text-end">{{ number_format($sheets->sum('net'), 2) }}</th></tr></tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
