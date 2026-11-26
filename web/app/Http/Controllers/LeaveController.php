<?php

namespace App\Http\Controllers;

use App\Models\CclCredit;
use App\Models\Leave;
use App\Models\Notice;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $users = User::visibleTo($request->user())->where('status', 'active')->orderBy('name')->get();
        $ids = $users->pluck('id');

        return view('leave.index', [
            'pending' => Leave::with('user')->whereIn('user_id', $ids)->where('status', 'pending')->orderBy('from_date')->get(),
            'history' => Leave::with('user')->whereIn('user_id', $ids)->where('status', '!=', 'pending')->latest()->limit(50)->get(),
            'credits' => CclCredit::with('user')->whereIn('user_id', $ids)->where('status', 'pending')->orderBy('earned_date')->get(),
            'balances' => $users->map(fn ($u) => ['user' => $u,
                'CL' => AttendanceService::leaveBalance($u, 'CL'),
                'SL' => AttendanceService::leaveBalance($u, 'SL'),
                'CCL' => AttendanceService::leaveBalance($u, 'CCL')]),
            'canDecide' => ! $request->user()->isAccountManager(),
        ]);
    }

    public function decide(Request $request, Leave $leave)
    {
        $this->mustSee($request, $leave->user_id);
        $data = $request->validate(['action' => 'required|in:approve,reject', 'remark' => 'required_if:action,reject|nullable|string|max:200'],
            ['remark.required_if' => 'Write a reason for rejecting.']);
        abort_unless($leave->status === 'pending', 404);

        $leave->update([
            'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
            'decided_by' => $request->user()->id, 'decide_remark' => $data['remark'] ?? null,
        ]);
        Notice::send($leave->user_id, 'Leave '.$leave->status,
            $leave->from_date->format('d M').' to '.$leave->to_date->format('d M').($leave->decide_remark ? ' - '.$leave->decide_remark : ''));

        return back()->with('ok', 'Leave '.$leave->status.'.');
    }

    public function decideCcl(Request $request, CclCredit $credit)
    {
        $this->mustSee($request, $credit->user_id);
        $data = $request->validate(['action' => 'required|in:approve,reject']);
        $credit->update(['status' => $data['action'] === 'approve' ? 'approved' : 'rejected']);
        Notice::send($credit->user_id, 'CCL '.$credit->status, 'For working on '.$credit->earned_date->format('d M').'.');

        return back()->with('ok', 'CCL '.$credit->status.'.');
    }

    private function mustSee(Request $request, int $userId): void
    {
        abort_unless(User::visibleTo($request->user())->whereKey($userId)->exists(), 403, 'This employee is not in your team.');
    }
}
