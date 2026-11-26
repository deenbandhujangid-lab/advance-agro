<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CclCredit;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Movement;
use App\Models\Notice;
use App\Models\OutsidePermission;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use DomainException;

/**
 * All attendance rules live here so the web and the mobile API behave the same.
 * A DomainException message is a plain sentence that can be shown to the user as is.
 */
class AttendanceService
{
    // ---------- location ----------

    /** Distance between two points in metres. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    public static function officeIsSet(): bool
    {
        return (float) Setting::get('office_lat') != 0.0 || (float) Setting::get('office_lng') != 0.0;
    }

    public static function distanceFromOffice(float $lat, float $lng): float
    {
        return self::distance($lat, $lng, (float) Setting::get('office_lat'), (float) Setting::get('office_lng'));
    }

    public static function radius(): float
    {
        return (float) Setting::get('office_radius');
    }

    // ---------- calendar ----------

    /** Holiday dates, loaded once per request. */
    private static ?array $holidays = null;

    /** 'holiday', 'weekly_off' or null for a normal working day. */
    public static function offDayType(User $user, Carbon $date): ?string
    {
        if (self::$holidays === null || app()->runningUnitTests()) {
            self::$holidays = Holiday::pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip()->all();
        }

        if (isset(self::$holidays[$date->toDateString()])) {
            return 'holiday';
        }
        if ($date->isSunday()) {
            if ($user->sunday_rule !== 'two') {
                return 'weekly_off';
            }
            $nth = (int) ceil($date->day / 7);          // 1st, 2nd ... Sunday of the month
            $off = array_map('intval', explode(',', (string) $user->off_sundays));

            return in_array($nth, $off, true) ? 'weekly_off' : null;
        }

        return null;
    }

    // ---------- state ----------

    public static function today(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)->whereDate('date', today())->first();
    }

    /** An earlier day that was never logged out. */
    public static function pending(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)->whereNull('logout_at')
            ->whereDate('date', '<', today())->orderBy('date')->first();
    }

    public static function permissionAt(User $user, Carbon $at): ?OutsidePermission
    {
        $time = $at->format('H:i:s');

        return OutsidePermission::where('user_id', $user->id)->whereDate('date', $at->toDateString())
            ->where('from_time', '<=', $time)->where('to_time', '>=', $time)->first();
    }

    public static function graceLeft(User $user): int
    {
        $used = Attendance::where('user_id', $user->id)->where('grace_used', true)
            ->whereBetween('date', [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()])
            ->count();

        return max(0, (int) Setting::get('grace_days') - $used);
    }

    /** Why the Login button is locked, or null when login is possible (location not checked here). */
    public static function lockReason(User $user): ?string
    {
        if ($user->status !== 'active') {
            return 'Your account is inactive. Contact admin.';
        }
        if ($p = self::pending($user)) {
            return 'Logout of '.$p->date->format('d M').' is pending. Please contact your manager.';
        }
        $today = self::today($user);
        if ($today && $today->logout_at) {
            return 'You have already logged out today.';
        }
        if (! $today && self::onFullDayLeave($user, today())) {
            return 'You are on approved leave today.';
        }

        return null;
    }

    public static function onFullDayLeave(User $user, Carbon $date): bool
    {
        return Leave::where('user_id', $user->id)->where('status', 'approved')->where('half_day', false)
            ->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->exists();
    }

    // ---------- login / logout ----------

    public static function login(User $user, float $lat, float $lng): Attendance
    {
        if ($reason = self::lockReason($user)) {
            throw new DomainException($reason);
        }
        if (self::today($user)) {
            throw new DomainException('You are already logged in.');
        }
        if (! $user->isSales()) {
            self::mustBeInsideOffice($lat, $lng, 'login');
        }

        $now = now();
        $offDay = self::offDayType($user, today()) !== null;
        $isLate = false;
        $graceUsed = false;

        if (! $offDay) {
            $start = Carbon::parse(today()->toDateString().' '.$user->morning_time);
            $lateBy = (int) floor(($now->timestamp - $start->timestamp) / 60);
            if ($lateBy >= 1) {
                if ($lateBy <= (int) Setting::get('grace_minutes') && self::graceLeft($user) > 0) {
                    $graceUsed = true;
                } else {
                    $isLate = true;
                }
            }
        }

        return Attendance::create([
            'user_id' => $user->id, 'date' => today()->toDateString(), 'login_at' => $now,
            'login_lat' => $lat, 'login_lng' => $lng,
            'is_late' => $isLate, 'grace_used' => $graceUsed, 'on_off_day' => $offDay,
        ]);
    }

    public static function logout(User $user, float $lat, float $lng): Attendance
    {
        $att = self::today($user);
        if (! $att) {
            throw new DomainException('You are not logged in today.');
        }
        if ($att->logout_at) {
            throw new DomainException('You have already logged out today.');
        }
        if (! $user->isSales()) {
            $perm = self::permissionAt($user, now());
            if (! ($perm && $perm->allow_outside_logout)) {
                self::mustBeInsideOffice($lat, $lng, 'logout');
            }
        }
        $att->logout_lat = $lat;
        $att->logout_lng = $lng;

        return self::close($att, now());
    }

    /** Close a day with a logout time: by the employee, or by a manager for a missed logout. */
    public static function close(Attendance $att, Carbon $logoutAt, ?User $by = null, ?string $remark = null): Attendance
    {
        $user = $att->user;
        if ($logoutAt->lte($att->login_at)) {
            throw new DomainException('Logout time must be after the login time ('.$att->login_at->format('h:i A').').');
        }
        if ($logoutAt->toDateString() !== $att->date->toDateString()) {
            throw new DomainException('Logout time must be on the same day.');
        }

        Movement::where('attendance_id', $att->id)->whereNull('in_at')->update(['in_at' => $logoutAt]);

        $att->logout_at = $logoutAt;
        $att->ot_minutes = 0;
        if (! $att->on_off_day && $user->ot_allowed) {
            $end = Carbon::parse($att->date->toDateString().' '.$user->evening_time);
            $extra = (int) floor(($logoutAt->timestamp - $end->timestamp) / 60);
            if ($extra >= (int) Setting::get('ot_min_minutes')) {
                $att->ot_minutes = $extra;
            }
        }
        if ($by) {
            $att->logout_by = $by->id;
            $att->remark = $remark;
        }
        $att->save();

        // Work on an off day earns one CCL, waiting for the manager's approval.
        if ($att->on_off_day && $att->workedMinutes() >= $user->shiftHours() * 30) {
            $credit = CclCredit::firstOrCreate(['user_id' => $user->id, 'earned_date' => $att->date->toDateString()]);
            if ($credit->wasRecentlyCreated) {
                Notice::send($user->manager_id, 'CCL to approve', $user->name.' worked on '.$att->date->format('d M').' (off day).');
            }
        }
        if ($by) {
            Notice::send($user->id, 'Logout time entered', 'Your manager closed '.$att->date->format('d M').' at '.$logoutAt->format('h:i A').'. You can login now.');
        }

        return $att;
    }

    private static function mustBeInsideOffice(float $lat, float $lng, string $action): void
    {
        if (! self::officeIsSet()) {
            throw new DomainException('Office location is not set yet. Contact admin.');
        }
        $distance = self::distanceFromOffice($lat, $lng);
        if ($distance > self::radius()) {
            throw new DomainException('You are '.round($distance).' m from office. Come inside the office to '.$action.'.');
        }
    }

    // ---------- tracking ----------

    /** Save one location reading. Returns false when the employee is not logged in (app must stop tracking). */
    public static function track(User $user, float $lat, float $lng, ?Carbon $at = null): bool
    {
        $att = self::today($user);
        if (! $att || $att->logout_at) {
            return false;
        }
        $at ??= now();
        $inside = self::officeIsSet() && self::distanceFromOffice($lat, $lng) <= self::radius();
        $open = Movement::where('attendance_id', $att->id)->whereNull('in_at')->first();

        // Out and In times are saved for everyone.
        $saveRoute = false;
        if (! $inside && ! $open) {
            $allowed = $user->isSales() || self::permissionAt($user, $at) !== null;
            Movement::create(['attendance_id' => $att->id, 'user_id' => $user->id, 'out_at' => $at, 'allowed' => $allowed]);
            if (! $allowed) {
                Notice::send($user->manager_id, 'Left office without permission', $user->name.' went outside at '.$at->format('h:i A').'.');
            }
            $saveRoute = $allowed;
        } elseif ($open) {
            if ($inside) {
                $open->update(['in_at' => $at]);
            }
            $saveRoute = $open->allowed;
        }

        // The route (where the employee went) is saved only for Sales and for employees
        // the manager allowed to go outside. For others only the Out and In times are kept.
        if ($saveRoute) {
            $att->points()->create(['lat' => $lat, 'lng' => $lng, 'inside' => $inside, 'recorded_at' => $at]);
        }

        return true;
    }

    // ---------- month view ----------

    public const CODES = [
        'P' => 'Present', 'L' => 'Late', 'H' => 'Half day', 'A' => 'Absent', 'LV' => 'Leave',
        'LW' => 'Leave without pay', 'WO' => 'Weekly off', 'HD' => 'Holiday', 'PN' => 'Logout pending',
    ];

    /** Bootstrap colour for each code: the same colours on every screen. */
    public const COLORS = [
        'P' => 'success', 'L' => 'warning', 'H' => 'info', 'A' => 'danger', 'LV' => 'primary',
        'LW' => 'primary', 'WO' => 'secondary', 'HD' => 'secondary', 'PN' => 'dark', '-' => 'light',
    ];

    /**
     * One month of one employee: a status code per day and the totals used by reports and salary.
     *
     * @return array{days: array<int,string>, totals: array<string,float|int>}
     */
    public static function month(User $user, string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $atts = Attendance::with('movements')->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->keyBy(fn ($a) => $a->date->toDateString());
        $leaves = Leave::where('user_id', $user->id)->where('status', 'approved')
            ->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get();

        $days = [];
        $t = ['present' => 0, 'late' => 0, 'half' => 0, 'absent' => 0, 'leave' => 0, 'off' => 0, 'pending' => 0,
              'grace' => 0, 'unpaid_days' => 0.0, 'ot_minutes' => 0, 'outside_minutes' => 0];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $att = $atts[$key] ?? null;
            $leave = $leaves->first(fn ($l) => $l->from_date->lte($d) && $l->to_date->gte($d));
            $code = self::dayCode($user, $d, $att, $leave);
            $days[$d->day] = $code;

            if ($att) {
                $t['ot_minutes'] += $att->ot_minutes;
                $t['outside_minutes'] += $att->logout_at ? $att->outsideMinutes() : 0;
                $t['grace'] += $att->grace_used ? 1 : 0;
            }
            match ($code) {
                'P' => $t['present']++,
                'L' => [$t['present']++, $t['late']++],
                'H' => $t['half']++,
                'A' => $t['absent']++,
                'LV', 'LW' => $t['leave']++,
                'WO', 'HD' => $t['off']++,
                'PN' => $t['pending']++,
                default => null,
            };
            if ($code === 'A' || $code === 'LW') {
                $t['unpaid_days'] += 1;
            } elseif ($code === 'H') {
                $paidHalf = $leave && $leave->half_day && $leave->type !== 'LWP' && $att;
                $t['unpaid_days'] += $paidHalf ? 0 : 0.5;
            }
        }

        return ['days' => $days, 'totals' => $t];
    }

    public static function dayCode(User $user, Carbon $date, ?Attendance $att, ?Leave $leave): string
    {
        if ($date->gt(today()) || ($user->joined_on && $date->lt($user->joined_on))) {
            return '-';
        }
        if ($att) {
            if ($att->isPending()) {
                return 'PN';
            }
            if (! $att->logout_at || $att->on_off_day) {
                return 'P';
            }
            if ($att->workedMinutes() < $user->shiftHours() * 30) {
                return 'H';
            }

            return $att->is_late ? 'L' : 'P';
        }
        if ($type = self::offDayType($user, $date)) {
            return $type === 'holiday' ? 'HD' : 'WO';
        }
        if ($leave) {
            if ($leave->half_day) {
                return 'H';
            }

            return $leave->type === 'LWP' ? 'LW' : 'LV';
        }

        return $date->isToday() ? '-' : 'A';
    }

    // ---------- leave ----------

    /** Working days in a date range for this employee (weekly offs and holidays are not counted). */
    public static function leaveDays(User $user, Carbon $from, Carbon $to, bool $halfDay): float
    {
        if ($halfDay) {
            return 0.5;
        }
        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days += self::offDayType($user, $d) === null ? 1 : 0;
        }

        return $days;
    }

    /** Balance left for a leave type; null means no limit (LWP). */
    public static function leaveBalance(User $user, string $type): ?float
    {
        if ($type === 'LWP') {
            return null;
        }
        $taken = (float) Leave::where('user_id', $user->id)->where('type', $type)
            ->whereIn('status', ['pending', 'approved'])
            ->when($type !== 'CCL', fn ($q) => $q->whereYear('from_date', today()->year))
            ->sum('days');
        $total = $type === 'CCL'
            ? CclCredit::where('user_id', $user->id)->where('status', 'approved')->count()
            : (float) Setting::get($type === 'CL' ? 'cl_quota' : 'sl_quota');

        return max(0, $total - $taken);
    }
}
