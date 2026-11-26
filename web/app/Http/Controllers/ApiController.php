<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Leave;
use App\Models\Movement;
use App\Models\Notice;
use App\Models\SalarySheet;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** JSON API for the employee mobile app. Every error is {"message": "plain sentence"}. */
class ApiController extends Controller
{
    private const MAX_ACCURACY = 50;    // metres; weaker GPS readings are not trusted

    public function login(Request $request)
    {
        $data = $request->validate(['emp_code' => 'required|string', 'password' => 'required|string', 'device_id' => 'required|string|max:190']);
        $user = User::where('emp_code', $data['emp_code'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Wrong employee code or password.');
        }
        if ($user->status !== 'active') {
            return $this->fail('Your account is inactive. Contact admin.');
        }
        if ($user->isSuperAdmin()) {
            return $this->fail('Super Admin uses the web application.');
        }
        // One employee = one phone, one phone = one employee.
        if ($user->device_id === null) {
            if (User::where('device_id', $data['device_id'])->where('id', '!=', $user->id)->exists()) {
                return $this->fail('This phone is registered to another employee. Contact admin.');
            }
            $user->update(['device_id' => $data['device_id']]);
        } elseif ($user->device_id !== $data['device_id']) {
            return $this->fail('This phone is not registered for you. Contact admin.');
        }

        $user->tokens()->delete();

        return ['token' => $user->createToken('app')->plainTextToken, 'user' => $this->userData($user)];
    }

    public function signOut(Request $request)
    {
        $att = AttendanceService::today($request->user());
        if ($att && ! $att->logout_at) {
            return $this->fail('Logout for the day first, then sign out of the app.');
        }
        $request->user()->currentAccessToken()->delete();

        return ['message' => 'Signed out.'];
    }

    /** Everything the Home screen needs. */
    public function home(Request $request)
    {
        $user = $request->user();
        $att = AttendanceService::today($user);
        $working = $att && ! $att->logout_at;
        $outside = $working ? Movement::where('attendance_id', $att->id)->whereNull('in_at')->first() : null;
        $perm = AttendanceService::permissionAt($user, now());

        return [
            'server_time' => now()->toIso8601String(),
            'user' => $this->userData($user),
            'state' => $working ? 'logged_in' : 'logged_out',       // which button to show
            'lock_reason' => $working ? null : AttendanceService::lockReason($user),
            'tracking' => $working,                                   // app tracks location only while true
            'login_time' => $att?->login_at->format('h:i A'),
            'logout_time' => $att?->logout_at?->format('h:i A'),
            'worked_minutes' => $att ? $att->workedMinutes() : 0,
            'is_late' => (bool) $att?->is_late,
            'status_line' => ! $working ? null : ($outside ? 'Outside since '.$outside->out_at->format('h:i A') : 'Inside office'),
            'grace_left' => AttendanceService::graceLeft($user),
            'permission' => $perm ? ['from' => Carbon::parse($perm->from_time)->format('h:i A'),
                'to' => Carbon::parse($perm->to_time)->format('h:i A'), 'reason' => $perm->reason] : null,
            'office' => ['lat' => (float) Setting::get('office_lat'), 'lng' => (float) Setting::get('office_lng'),
                'radius' => (float) Setting::get('office_radius'), 'zone_check' => ! $user->isSales()],
            'unread_notices' => Notice::where('user_id', $user->id)->whereNull('read_at')->count(),
            'manager' => $user->manager ? ['name' => $user->manager->name, 'role' => $user->manager->roleLabel(),
                'phone' => $user->manager->phone, 'email' => $user->manager->email] : null,
            'balances' => ['CL' => AttendanceService::leaveBalance($user, 'CL'), 'SL' => AttendanceService::leaveBalance($user, 'SL'),
                'CCL' => AttendanceService::leaveBalance($user, 'CCL')],
        ];
    }

    public function dayLogin(Request $request)
    {
        return $this->punch($request, 'login');
    }

    public function dayLogout(Request $request)
    {
        return $this->punch($request, 'logout');
    }

    private function punch(Request $request, string $action)
    {
        $data = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric', 'mock' => 'nullable|boolean']);
        if ($request->boolean('mock')) {
            return $this->fail('Fake location is on. Turn it off to '.$action.'.');
        }
        if (($data['accuracy'] ?? 0) > self::MAX_ACCURACY) {
            return $this->fail('GPS signal is weak. Move near a window or outside and try again.');
        }
        try {
            $att = $action === 'login'
                ? AttendanceService::login($request->user(), (float) $data['lat'], (float) $data['lng'])
                : AttendanceService::logout($request->user(), (float) $data['lat'], (float) $data['lng']);
        } catch (DomainException $e) {
            return $this->fail($e->getMessage());
        }
        $time = ($action === 'login' ? $att->login_at : $att->logout_at)->format('h:i A');

        return ['message' => ($action === 'login' ? 'Logged in at ' : 'Logged out at ').$time, 'tracking' => $action === 'login'];
    }

    /** One location reading while logged in. "tracking": false tells the app to stop sending. */
    public function track(Request $request)
    {
        $data = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric']);
        if (($data['accuracy'] ?? 0) > self::MAX_ACCURACY) {
            $att = AttendanceService::today($request->user());

            return ['tracking' => (bool) ($att && ! $att->logout_at)];
        }

        return ['tracking' => AttendanceService::track($request->user(), (float) $data['lat'], (float) $data['lng'])];
    }

    /** Month calendar with the details of each day. */
    public function attendance(Request $request)
    {
        $month = $request->validate(['month' => 'nullable|date_format:Y-m'])['month'] ?? today()->format('Y-m');
        $user = $request->user();
        $summary = AttendanceService::month($user, $month);
        $atts = Attendance::with('movements')->where('user_id', $user->id)
            ->where('date', 'like', $month.'%')->get()->keyBy(fn ($a) => $a->date->day);

        $days = [];
        foreach ($summary['days'] as $day => $code) {
            $a = $atts[$day] ?? null;
            $days[] = [
                'date' => sprintf('%s-%02d', $month, $day), 'code' => $code,
                'label' => AttendanceService::CODES[$code] ?? '',
                'login' => $a?->login_at->format('h:i A'), 'logout' => $a?->logout_at?->format('h:i A'),
                'ot_minutes' => $a->ot_minutes ?? 0,
                'movements' => $a ? $a->movements->map(fn ($m) => ['out' => $m->out_at->format('h:i A'),
                    'in' => $m->in_at?->format('h:i A'), 'minutes' => $m->in_at ? $m->minutes() : null,
                    'allowed' => $m->allowed])->all() : [],
            ];
        }

        return ['month' => $month, 'days' => $days, 'totals' => $summary['totals']];
    }

    public function leaves(Request $request)
    {
        $user = $request->user();

        return [
            'balances' => ['CL' => AttendanceService::leaveBalance($user, 'CL'), 'SL' => AttendanceService::leaveBalance($user, 'SL'),
                'CCL' => AttendanceService::leaveBalance($user, 'CCL')],
            'types' => Leave::TYPES,
            'leaves' => Leave::where('user_id', $user->id)->latest()->limit(30)->get()->map(fn ($l) => [
                'id' => $l->id, 'type' => $l->type, 'from' => $l->from_date->toDateString(), 'to' => $l->to_date->toDateString(),
                'half_day' => $l->half_day, 'days' => $l->days, 'reason' => $l->reason, 'status' => $l->status,
                'remark' => $l->decide_remark,
                'can_cancel' => $l->status === 'pending' || ($l->status === 'approved' && $l->from_date->gt(today())),
            ]),
        ];
    }

    public function applyLeave(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => 'required|in:CL,SL,CCL,LWP',
            'from' => 'required|date|after_or_equal:today',
            'to' => 'required|date|after_or_equal:from',
            'half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:200',
        ]);
        $from = Carbon::parse($data['from']);
        $half = $request->boolean('half_day');
        $to = $half ? $from->copy() : Carbon::parse($data['to']);

        $days = AttendanceService::leaveDays($user, $from, $to, $half);
        if ($days <= 0 || ($half && AttendanceService::offDayType($user, $from))) {
            return $this->fail('These dates are already off days. No leave is needed.');
        }
        $overlap = Leave::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->exists();
        if ($overlap) {
            return $this->fail('You already have a leave request for these dates.');
        }
        $balance = AttendanceService::leaveBalance($user, $data['type']);
        if ($balance !== null && $days > $balance) {
            return $this->fail('Not enough '.$data['type'].' balance. You have '.$balance.' day(s) left.');
        }

        Leave::create(['user_id' => $user->id, 'type' => $data['type'], 'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(), 'half_day' => $half, 'days' => $days, 'reason' => $data['reason']]);
        $approver = $user->manager_id ?: User::where('role', 'super_admin')->value('id');
        Notice::send($approver, 'Leave request', $user->name.': '.$from->format('d M').' to '.$to->format('d M').' ('.$data['type'].').');

        return ['message' => 'Leave request sent to your manager.'];
    }

    public function cancelLeave(Request $request, Leave $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 404);
        $canCancel = $leave->status === 'pending' || ($leave->status === 'approved' && $leave->from_date->gt(today()));
        if (! $canCancel) {
            return $this->fail('This leave can no longer be cancelled.');
        }
        $leave->update(['status' => 'cancelled']);

        return ['message' => 'Leave cancelled.'];
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return [
            'user' => $this->userData($user) + [
                'email' => $user->email, 'phone' => $user->phone, 'manager' => $user->manager?->name,
                'morning_time' => Carbon::parse($user->morning_time)->format('h:i A'),
                'evening_time' => Carbon::parse($user->evening_time)->format('h:i A'),
            ],
            'salary' => ['basic' => $user->basic_salary, 'due_amount' => $user->dueAmount(), 'deduction_per_month' => $user->deduction_per_month],
            'payslips' => SalarySheet::where('user_id', $user->id)->whereNotNull('finalized_at')->orderByDesc('month')->limit(12)->get()
                ->map(fn ($s) => ['month' => Carbon::parse($s->month.'-01')->format('M Y'), 'basic' => $s->basic,
                    'unpaid_days' => $s->unpaid_days, 'unpaid_amount' => $s->unpaid_amount, 'ot_amount' => $s->ot_amount,
                    'advance_deduction' => $s->advance_deduction, 'net' => $s->net]),
        ];
    }

    public function notices(Request $request)
    {
        $notices = Notice::where('user_id', $request->user()->id)->latest()->limit(30)->get()
            ->map(fn ($n) => ['title' => $n->title, 'body' => $n->body, 'time' => $n->created_at->format('d M, h:i A'), 'unread' => $n->read_at === null]);
        Notice::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return ['notices' => $notices];
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'new_password' => 'required|string|min:6']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return $this->fail('Current password is wrong.');
        }
        $request->user()->update(['password' => $data['new_password']]);

        return ['message' => 'Password changed.'];
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'emp_code' => $user->emp_code, 'name' => $user->name,
            'role' => $user->roleLabel(), 'type' => ucfirst((string) $user->emp_type)];
    }

    private function fail(string $message, int $status = 422)
    {
        return response()->json(['message' => $message], $status);
    }
}
