<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Notice;
use App\Models\OutsidePermission;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\SalaryService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /** One row per employee for one date. */
    public function daily(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        $users = User::visibleTo($request->user())->where('status', 'active')->orderBy('name')->get();
        $atts = Attendance::with('movements')->whereIn('user_id', $users->pluck('id'))
            ->whereDate('date', $date)->get()->keyBy('user_id');
        $leaves = Leave::whereIn('user_id', $users->pluck('id'))->where('status', 'approved')
            ->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->get()->keyBy('user_id');

        $rows = $users->map(fn ($u) => [
            'user' => $u,
            'att' => $atts[$u->id] ?? null,
            'code' => AttendanceService::dayCode($u, $date, $atts[$u->id] ?? null, $leaves[$u->id] ?? null),
        ]);

        return view('attendance.daily', compact('rows', 'date'));
    }

    /** One day of one employee: times, in/out list and the route on a map. */
    public function show(Request $request, Attendance $attendance)
    {
        $this->mustSee($request, $attendance->user_id);
        $attendance->load('user', 'movements', 'points', 'closedBy');

        return view('attendance.show', [
            'att' => $attendance,
            'office' => ['lat' => (float) Setting::get('office_lat'), 'lng' => (float) Setting::get('office_lng'),
                         'radius' => (float) Setting::get('office_radius')],
            'canEdit' => ! $request->user()->isAccountManager(),
        ]);
    }

    /** Correct the login or logout time of a day. */
    public function correct(Request $request, Attendance $attendance)
    {
        $this->mustSee($request, $attendance->user_id);
        $data = $request->validate([
            'login_time' => 'required|date_format:H:i',
            'logout_time' => 'nullable|date_format:H:i',
            'remark' => 'required|string|max:200',
        ]);
        if (SalaryService::isFinalized($attendance->date->format('Y-m'))) {
            return back()->withErrors(['remark' => 'Salary for this month is finalized. Attendance cannot be changed.']);
        }

        $day = $attendance->date->toDateString();
        $user = $attendance->user;
        $attendance->login_at = Carbon::parse("$day {$data['login_time']}");
        if (! $attendance->on_off_day) {
            $start = Carbon::parse("$day {$user->morning_time}");
            $lateBy = (int) floor(($attendance->login_at->timestamp - $start->timestamp) / 60);
            if ($lateBy < 1) {
                $attendance->is_late = false;
                $attendance->grace_used = false;
            } elseif ($lateBy > (int) Setting::get('grace_minutes')) {
                $attendance->is_late = true;
                $attendance->grace_used = false;
            }
        }
        $attendance->remark = $data['remark'];
        $attendance->save();

        try {
            if (! empty($data['logout_time'])) {
                AttendanceService::close($attendance, Carbon::parse("$day {$data['logout_time']}"), $request->user(), $data['remark']);
            }
        } catch (DomainException $e) {
            return back()->withErrors(['logout_time' => $e->getMessage()]);
        }

        return back()->with('ok', 'Attendance corrected.');
    }

    /** Employee x date grid for a month. */
    public function monthly(Request $request)
    {
        $month = $request->query('month', today()->format('Y-m'));
        $users = User::visibleTo($request->user())->where('status', 'active')->orderBy('name')->get();
        $rows = $users->map(fn ($u) => ['user' => $u] + AttendanceService::month($u, $month));

        return view('attendance.monthly', [
            'rows' => $rows, 'month' => $month,
            'daysInMonth' => Carbon::parse($month.'-01')->daysInMonth,
        ]);
    }

    /** Employees who forgot to logout. */
    public function pending(Request $request)
    {
        $ids = User::visibleTo($request->user())->pluck('id');
        $pending = Attendance::with('user')->whereIn('user_id', $ids)->whereNull('logout_at')
            ->whereDate('date', '<', today())->orderBy('date')->get();

        return view('attendance.pending', compact('pending'));
    }

    public function closePending(Request $request, Attendance $attendance)
    {
        $this->mustSee($request, $attendance->user_id);
        $data = $request->validate(['logout_time' => 'required|date_format:H:i', 'remark' => 'required|string|max:200']);
        abort_unless($attendance->isPending(), 404);

        try {
            AttendanceService::close($attendance,
                Carbon::parse($attendance->date->toDateString().' '.$data['logout_time']), $request->user(), $data['remark']);
        } catch (DomainException $e) {
            return back()->withErrors(['logout_time_'.$attendance->id => $e->getMessage()]);
        }

        return back()->with('ok', 'Logout time saved. '.$attendance->user->name.' can login now.');
    }

    /** Manager allows an employee to go outside the office. */
    public function outside(Request $request)
    {
        $users = User::visibleTo($request->user())->where('status', 'active')->orderBy('name')->get();
        $permissions = OutsidePermission::with('user', 'creator')->whereIn('user_id', $users->pluck('id'))
            ->whereDate('date', '>=', today()->subDays(7))->orderByDesc('date')->orderBy('from_time')->get();

        return view('attendance.outside', compact('users', 'permissions'));
    }

    public function storeOutside(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date|after_or_equal:today',
            'from_time' => 'required|date_format:H:i',
            'to_time' => 'required|date_format:H:i|after:from_time',
            'reason' => 'required|string|max:200',
        ], ['to_time.after' => 'To time must be after From time.']);
        $this->mustSee($request, (int) $data['user_id']);

        $data['allow_outside_logout'] = $request->boolean('allow_outside_logout');
        $data['created_by'] = $request->user()->id;
        OutsidePermission::create($data);
        Notice::send((int) $data['user_id'], 'You may go outside',
            Carbon::parse($data['date'])->format('d M').', '.Carbon::parse($data['from_time'])->format('h:i A')
            .' to '.Carbon::parse($data['to_time'])->format('h:i A').'.');

        return back()->with('ok', 'Permission saved.');
    }

    public function deleteOutside(Request $request, OutsidePermission $permission)
    {
        $this->mustSee($request, $permission->user_id);
        $permission->delete();

        return back()->with('ok', 'Permission removed.');
    }

    private function mustSee(Request $request, int $userId): void
    {
        abort_unless(User::visibleTo($request->user())->whereKey($userId)->exists(), 403, 'This employee is not in your team.');
    }
}
