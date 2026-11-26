<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CclCredit;
use App\Models\Leave;
use App\Models\Movement;
use App\Models\Notice;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $users = User::visibleTo($viewer)->where('status', 'active')->orderBy('name')->get();
        $ids = $users->pluck('id');

        $todayAtts = Attendance::whereIn('user_id', $ids)->whereDate('date', today())->get()->keyBy('user_id');
        $outsideIds = Movement::whereIn('user_id', $ids)->whereNull('in_at')
            ->whereIn('attendance_id', $todayAtts->whereNull('logout_at')->pluck('id'))->pluck('user_id')->unique();
        $pending = Attendance::with('user')->whereIn('user_id', $ids)->whereNull('logout_at')
            ->whereDate('date', '<', today())->orderBy('date')->get();

        $lists = ['present' => [], 'late' => [], 'absent' => [], 'leave' => [], 'outside' => []];
        foreach ($users as $u) {
            $att = $todayAtts[$u->id] ?? null;
            if ($att) {
                $lists['present'][] = $u->name.' ('.$att->login_at->format('h:i A').')';
                if ($att->is_late) {
                    $lists['late'][] = $u->name.' ('.$att->login_at->format('h:i A').')';
                }
                if ($outsideIds->contains($u->id)) {
                    $lists['outside'][] = $u->name;
                }
            } elseif (AttendanceService::onFullDayLeave($u, today())) {
                $lists['leave'][] = $u->name;
            } elseif (AttendanceService::offDayType($u, today()) === null) {
                $lists['absent'][] = $u->name;      // not logged in yet today
            }
        }
        $lists['pending'] = $pending->map(fn ($a) => $a->user->name.' ('.$a->date->format('d M').')')->all();

        return view('dashboard', [
            'lists' => $lists,
            'pendingLeaves' => Leave::whereIn('user_id', $ids)->where('status', 'pending')->count(),
            'pendingCcl' => CclCredit::whereIn('user_id', $ids)->where('status', 'pending')->count(),
            'notices' => Notice::where('user_id', $viewer->id)->latest()->limit(10)->get(),
        ]);
    }
}
