@extends('layouts.app')
@section('title', 'Leave')
@section('content')
@use('App\Models\Leave')
<h6>Leave requests waiting</h6>
@forelse ($pending as $l)
    <div class="card p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <b>{{ $l->user->name }}</b> &middot; {{ Leave::TYPES[$l->type] }} &middot;
                {{ $l->from_date->format('d M') }}{{ $l->to_date->ne($l->from_date) ? ' to '.$l->to_date->format('d M') : '' }}
                ({{ $l->days }} day{{ $l->days == 1 ? '' : 's' }}{{ $l->half_day ? ', half day' : '' }})
                <div class="text-muted">{{ $l->reason }}</div>
            </div>
            @if ($canDecide)
                <form method="post" action="{{ route('leave.decide', $l) }}" class="d-flex flex-wrap gap-2">
                    @csrf
                    <input class="form-control" style="width:220px" name="remark" placeholder="Remark (needed to reject)">
                    <button class="btn btn-success" name="action" value="approve">Approve leave</button>
                    <button class="btn btn-outline-danger" name="action" value="reject">Reject</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card p-3 mb-2 text-muted">No leave requests waiting.</div>
@endforelse

<h6 class="mt-4">CCL waiting (worked on an off day)</h6>
@forelse ($credits as $c)
    <div class="card p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><b>{{ $c->user->name }}</b> worked on {{ $c->earned_date->format('d M Y (l)') }}</div>
            @if ($canDecide)
                <form method="post" action="{{ route('leave.ccl', $c) }}" class="d-flex gap-2">
                    @csrf
                    <button class="btn btn-success" name="action" value="approve">Give 1 CCL</button>
                    <button class="btn btn-outline-danger" name="action" value="reject">Reject</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card p-3 mb-2 text-muted">No CCL waiting.</div>
@endforelse

<div class="row g-3 mt-2">
    <div class="col-lg-5">
        <h6>Leave balance</h6>
        <div class="card">
            <table class="table table-sm mb-0">
                <thead><tr><th>Employee</th><th>Casual</th><th>Sick</th><th>CCL</th></tr></thead>
                <tbody>
                @foreach ($balances as $b)
                    <tr><td>{{ $b['user']->name }}</td><td>{{ $b['CL'] }}</td><td>{{ $b['SL'] }}</td><td>{{ $b['CCL'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <h6>History</h6>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($history as $l)
                        <tr>
                            <td>{{ $l->user->name }}</td>
                            <td>{{ $l->type }}</td>
                            <td>{{ $l->from_date->format('d M') }}{{ $l->to_date->ne($l->from_date) ? ' - '.$l->to_date->format('d M') : '' }}</td>
                            <td>{{ $l->days }}</td>
                            <td><span class="badge text-bg-{{ ['approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'][$l->status] ?? 'light' }}">{{ ucfirst($l->status) }}</span>
                                <span class="text-muted small">{{ $l->decide_remark }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No history yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
