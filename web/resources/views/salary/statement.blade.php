@extends('layouts.app')
@section('title', 'Advance: '.$employee->name)
@section('content')
<a href="{{ route('salary.advances') }}">&larr; Back to advances</a>
<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="card p-3 mb-3">
            <div class="d-flex justify-content-between"><span>Basic salary</span><b>Rs {{ number_format($employee->basic_salary, 2) }}</b></div>
            <div class="d-flex justify-content-between"><span>Deduction per month</span><b>Rs {{ number_format($employee->deduction_per_month, 2) }}</b></div>
            <div class="d-flex justify-content-between"><span>Due amount</span><b class="text-danger">Rs {{ number_format($employee->dueAmount(), 2) }}</b></div>
        </div>
        <div class="card p-3">
            <b class="mb-2">New entry</b>
            <form method="post" action="{{ route('salary.entry', $employee) }}">
                @csrf
                <label class="form-label">What is it? *</label>
                <select class="form-select mb-2" name="type">
                    <option value="advance" @selected(old('type') === 'advance')>Give advance (adds to due amount)</option>
                    <option value="repayment" @selected(old('type') === 'repayment')>Cash repayment (reduces due amount)</option>
                </select>
                <label class="form-label">Date *</label>
                <input class="form-control mb-2" type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
                <label class="form-label">Amount (Rs) *</label>
                <input class="form-control mb-2" type="number" step="0.01" min="1" name="amount" value="{{ old('amount') }}" required>
                <label class="form-label">Remark</label>
                <input class="form-control mb-3" name="remark" value="{{ old('remark') }}">
                <button class="btn btn-success">Save entry</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <table class="table mb-0">
                <thead><tr><th>Date</th><th>Entry</th><th>Remark</th><th class="text-end">Amount</th><th class="text-end">Due after</th></tr></thead>
                <tbody>
                @php $due = 0; @endphp
                @forelse ($entries as $e)
                    @php $due += $e->type === 'advance' ? $e->amount : -$e->amount; @endphp
                    <tr>
                        <td>{{ $e->date->format('d M Y') }}</td>
                        <td>{{ ['advance' => 'Advance given', 'deduction' => 'Cut from salary', 'repayment' => 'Cash repayment'][$e->type] }}</td>
                        <td>{{ $e->remark }}</td>
                        <td class="text-end">{{ $e->type === 'advance' ? '+' : '-' }} {{ number_format($e->amount, 2) }}</td>
                        <td class="text-end"><b>{{ number_format(max(0, $due), 2) }}</b></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No advance given yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
