<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CclCredit;
use App\Models\Leave;
use App\Models\Movement;
use App\Models\Notice;
use App\Models\SalarySheet;
use App\Models\Setting;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Walks the whole SRS in one story: web setup, mobile API, tracking, missed logout, leave, CCL, salary. */
class FullFlowTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE = ['lat' => 28.6139, 'lng' => 77.2090];
    private const NEAR = ['lat' => 28.61392, 'lng' => 77.20902];      // a few metres away
    private const OUT = ['lat' => 28.6148, 'lng' => 77.2090];         // about 100 m away
    private const FAR = ['lat' => 28.70, 'lng' => 77.30];

    private function api(string $token, string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, '/api'.$uri, $data);
    }

    private function web(User $user)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function employeeForm(array $over): array
    {
        return $over + ['password' => 'secret123', 'role' => 'employee', 'emp_type' => 'operation', 'joined_on' => '2026-11-01',
            'morning_time' => '09:30', 'evening_time' => '18:30', 'sunday_rule' => 'all', 'ot_allowed' => 1, 'basic_salary' => 26000];
    }

    public function test_full_story(): void
    {
        $this->seed();
        $admin = User::where('role', 'super_admin')->first();

        // ----- Super Admin sets the office and adds people on the web -----
        $this->travelTo('2026-11-02 08:00:00');                       // Monday
        $this->web($admin)->post('/settings', ['office_name' => 'HO', 'office_lat' => self::OFFICE['lat'], 'office_lng' => self::OFFICE['lng'],
            'office_radius' => 35, 'grace_minutes' => 15, 'grace_days' => 2, 'ot_min_minutes' => 30, 'ot_days' => 26,
            'cl_quota' => 12, 'sl_quota' => 6])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(self::OFFICE['lat'], Setting::get('office_lat'));
        $this->assertEquals(35, Setting::get('office_radius'));

        $this->web($admin)->post('/employees', $this->employeeForm(['emp_code' => 'M1', 'name' => 'Manager One',
            'email' => 'm1@x.in', 'phone' => '9000000001', 'role' => 'manager']))->assertSessionHasNoErrors();
        $manager = User::where('emp_code', 'M1')->first();
        $this->web($admin)->post('/employees', $this->employeeForm(['emp_code' => 'A1', 'name' => 'Accounts One',
            'email' => 'a1@x.in', 'phone' => '9000000002', 'role' => 'account_manager']))->assertSessionHasNoErrors();
        $accounts = User::where('emp_code', 'A1')->first();
        $this->web($admin)->post('/employees', $this->employeeForm(['emp_code' => 'E1', 'name' => 'Op Emp',
            'email' => 'e1@x.in', 'phone' => '9000000003', 'manager_id' => $manager->id]))->assertSessionHasNoErrors();
        $this->web($admin)->post('/employees', $this->employeeForm(['emp_code' => 'S1', 'name' => 'Sales Emp',
            'email' => 's1@x.in', 'phone' => '9000000004', 'emp_type' => 'sales', 'manager_id' => $manager->id]))->assertSessionHasNoErrors();
        $this->web($admin)->post('/employees', $this->employeeForm(['emp_code' => 'E1', 'name' => 'Dup', 'email' => 'd@x.in', 'phone' => '9000000009']))
            ->assertSessionHasErrors('emp_code');
        $emp = User::where('emp_code', 'E1')->first();
        $sales = User::where('emp_code', 'S1')->first();

        // Roles: account manager cannot add employees, manager cannot open salary, employee cannot use the web.
        $this->web($accounts)->get('/employees/new')->assertForbidden();
        $this->web($manager)->get('/salary/sheet')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['login' => 'E1', 'password' => 'secret123'])->assertSessionHasErrors('login');

        // ----- Phone binding -----
        $this->api('', 'post', '/login', ['emp_code' => 'E1', 'password' => 'bad', 'device_id' => 'phone-1'])->assertStatus(422);
        $empToken = $this->api('', 'post', '/login', ['emp_code' => 'E1', 'password' => 'secret123', 'device_id' => 'phone-1'])->assertOk()->json('token');
        $this->api('', 'post', '/login', ['emp_code' => 'E1', 'password' => 'secret123', 'device_id' => 'phone-2'])
            ->assertStatus(422)->assertJsonPath('message', 'This phone is not registered for you. Contact admin.');
        $this->api('', 'post', '/login', ['emp_code' => 'S1', 'password' => 'secret123', 'device_id' => 'phone-1'])
            ->assertStatus(422)->assertJsonPath('message', 'This phone is registered to another employee. Contact admin.');
        $salesToken = $this->api('', 'post', '/login', ['emp_code' => 'S1', 'password' => 'secret123', 'device_id' => 'phone-9'])->assertOk()->json('token');

        // ----- Monday: login rules, 35 m zone, grace -----
        $this->travelTo('2026-11-02 09:40:00');
        $this->api($empToken, 'post', '/attendance/login', self::FAR)->assertStatus(422);
        $this->api($empToken, 'post', '/attendance/login', self::NEAR + ['mock' => true])->assertStatus(422);
        $this->api($empToken, 'post', '/attendance/login', self::NEAR)->assertOk()->assertJsonPath('tracking', true);
        $this->api($empToken, 'post', '/attendance/login', self::NEAR)->assertStatus(422);
        $att = Attendance::where('user_id', $emp->id)->first();
        $this->assertTrue($att->grace_used);
        $this->assertFalse($att->is_late);
        $this->api($empToken, 'get', '/home')->assertOk()->assertJsonPath('state', 'logged_in')->assertJsonPath('grace_left', 1);

        // Sales logs in from anywhere, on time.
        $this->travelTo('2026-11-02 09:20:00');
        $this->api($salesToken, 'post', '/attendance/login', self::FAR)->assertOk();
        $this->api($salesToken, 'post', '/track', self::FAR)->assertJsonPath('tracking', true);
        $this->assertTrue(Movement::where('user_id', $sales->id)->first()->allowed);

        // Operation employee walks out without permission, comes back.
        $this->travelTo('2026-11-02 11:00:00');
        $this->api($empToken, 'post', '/track', self::OUT)->assertJsonPath('tracking', true);
        $this->api($empToken, 'post', '/track', self::OUT);
        $this->assertSame(1, Movement::where('user_id', $emp->id)->count());
        $this->assertFalse(Movement::where('user_id', $emp->id)->first()->allowed);
        $this->assertTrue(Notice::where('user_id', $manager->id)->where('title', 'Left office without permission')->exists());
        $this->api($empToken, 'get', '/home')->assertJsonPath('status_line', 'Outside since 11:00 AM');
        $this->api($empToken, 'post', '/attendance/logout', self::OUT)->assertStatus(422);       // cannot logout from outside
        $this->travelTo('2026-11-02 11:20:00');
        $this->api($empToken, 'post', '/track', self::NEAR);
        $this->assertSame(20, Movement::where('user_id', $emp->id)->first()->minutes());
        $this->assertSame(0, $att->points()->count());                 // no permission: only Out/In times, no route
        $this->assertSame(1, Attendance::where('user_id', $sales->id)->first()->points()->count());   // sales route is saved

        // Manager allows 2-4 PM; the second trip is "Allowed".
        $this->web($manager)->post('/attendance/outside', ['user_id' => $emp->id, 'date' => '2026-11-02', 'from_time' => '14:00',
            'to_time' => '16:00', 'reason' => 'Bank work'])->assertSessionHasNoErrors();
        $this->travelTo('2026-11-02 14:10:00');
        $this->api($empToken, 'post', '/track', self::OUT);
        $this->travelTo('2026-11-02 15:00:00');
        $this->api($empToken, 'post', '/track', self::NEAR);
        $second = Movement::where('user_id', $emp->id)->orderByDesc('id')->first();
        $this->assertTrue($second->allowed);
        $this->assertSame(50, $second->minutes());
        $this->assertSame(2, $att->points()->count());                 // allowed trip: route is saved

        // Logout at 8 PM: 90 minutes overtime. After logout tracking is refused.
        $this->travelTo('2026-11-02 20:00:00');
        $this->api($empToken, 'post', '/attendance/logout', self::NEAR)->assertOk();
        $this->assertSame(90, $att->fresh()->ot_minutes);
        $this->api($empToken, 'post', '/track', self::OUT)->assertJsonPath('tracking', false);
        $this->assertSame(2, Movement::where('user_id', $emp->id)->count());

        // ----- Tuesday: sales forgot to logout -> locked until the manager enters the time -----
        $this->travelTo('2026-11-03 09:25:00');
        $this->api($salesToken, 'get', '/home')->assertJsonPath('state', 'logged_out')
            ->assertJsonPath('lock_reason', 'Logout of 02 Nov is pending. Please contact your manager.');
        $this->api($salesToken, 'post', '/attendance/login', self::FAR)->assertStatus(422);
        $this->api($salesToken, 'post', '/track', self::FAR)->assertJsonPath('tracking', false);   // no tracking overnight
        $pending = Attendance::where('user_id', $sales->id)->first();
        $this->web($manager)->get('/attendance/pending')->assertOk()->assertSee('Sales Emp');
        $this->web($manager)->post("/attendance/pending/{$pending->id}", ['logout_time' => '08:00', 'remark' => 'x'])
            ->assertSessionHasErrors("logout_time_{$pending->id}");                                // before login time
        $this->web($manager)->post("/attendance/pending/{$pending->id}", ['logout_time' => '18:30', 'remark' => 'Forgot'])
            ->assertSessionHasNoErrors();
        $this->assertSame($manager->id, $pending->fresh()->logout_by);
        $this->api($salesToken, 'post', '/attendance/login', self::FAR)->assertOk();
        $this->travelTo('2026-11-03 09:40:00');
        $this->api($salesToken, 'post', '/attendance/logout', self::FAR)->assertOk();              // sales logs out anywhere

        // Grace: 2nd late-within-15 uses grace, 3rd is Late, 20 minutes is always Late.
        $this->travelTo('2026-11-03 09:45:00');
        $this->api($empToken, 'post', '/attendance/login', self::NEAR)->assertOk();
        $this->travelTo('2026-11-03 18:30:00');
        $this->api($empToken, 'post', '/attendance/logout', self::NEAR)->assertOk();
        $this->travelTo('2026-11-04 09:40:00');
        $this->api($empToken, 'post', '/attendance/login', self::NEAR)->assertOk();
        $this->travelTo('2026-11-04 18:50:00');
        $this->api($empToken, 'post', '/attendance/logout', self::NEAR)->assertOk();
        $days = Attendance::where('user_id', $emp->id)->orderBy('date')->get();
        $this->assertTrue($days[1]->grace_used);
        $this->assertTrue($days[2]->is_late);
        $this->assertSame(0, $days[2]->ot_minutes);                                                 // 20 min is under the 30 min minimum

        // ----- Leave: apply, balance, overlap, approve -----
        $this->api($empToken, 'post', '/leaves', ['type' => 'CCL', 'from' => '2026-11-06', 'to' => '2026-11-06', 'reason' => 'x'])->assertStatus(422);
        $this->api($empToken, 'post', '/leaves', ['type' => 'CL', 'from' => '2026-11-06', 'to' => '2026-11-06', 'reason' => 'Family'])->assertOk();
        $this->api($empToken, 'post', '/leaves', ['type' => 'SL', 'from' => '2026-11-06', 'to' => '2026-11-07', 'reason' => 'x'])->assertStatus(422);
        $this->api($empToken, 'get', '/leaves')->assertJsonPath('balances.CL', 11);
        $leave = Leave::first();
        $this->web($manager)->post("/leave/{$leave->id}/decide", ['action' => 'reject'])->assertSessionHasErrors('remark');
        $this->web($manager)->post("/leave/{$leave->id}/decide", ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->travelTo('2026-11-06 09:30:00');
        $this->api($empToken, 'post', '/attendance/login', self::NEAR)->assertStatus(422)->assertJsonPath('message', 'You are on approved leave today.');

        // ----- Sunday work earns CCL -----
        $this->travelTo('2026-11-08 09:30:00');
        $this->assertSame('weekly_off', AttendanceService::offDayType($emp, today()));
        $this->api($empToken, 'post', '/attendance/login', self::NEAR)->assertOk();
        $this->travelTo('2026-11-08 18:30:00');
        $this->api($empToken, 'post', '/attendance/logout', self::NEAR)->assertOk();
        $credit = CclCredit::first();
        $this->assertSame('pending', $credit->status);
        $this->web($manager)->post("/leave/ccl/{$credit->id}/decide", ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->api($empToken, 'get', '/leaves')->assertJsonPath('balances.CCL', 1);

        // "2 Sundays off": 2nd and 4th are off, the 3rd Sunday is a working day.
        $sales->update(['sunday_rule' => 'two', 'off_sundays' => '2,4']);
        $this->assertSame('weekly_off', AttendanceService::offDayType($sales, \Carbon\Carbon::parse('2026-11-08')));
        $this->assertNull(AttendanceService::offDayType($sales, \Carbon\Carbon::parse('2026-11-15')));

        // ----- Advance and salary -----
        $this->web($accounts)->post("/salary/advances/{$emp->id}", ['type' => 'advance', 'date' => '2026-11-08', 'amount' => 10000])->assertSessionHasNoErrors();
        $this->web($accounts)->post("/salary/deduction/{$emp->id}", ['deduction_per_month' => 2000])->assertSessionHasNoErrors();
        $this->assertEquals(10000, $emp->fresh()->dueAmount());

        $this->travelTo('2026-12-01 10:00:00');
        $month = AttendanceService::month($emp->fresh(), '2026-11');
        $this->assertSame('P', $month['days'][2]);
        $this->assertSame('L', $month['days'][4]);
        $this->assertSame('A', $month['days'][5]);
        $this->assertSame('LV', $month['days'][6]);
        $this->assertSame('P', $month['days'][8]);          // worked on Sunday
        $this->assertSame('WO', $month['days'][15]);
        // Working days in Nov 2026 = 25 (30 days - 5 Sundays). Present 3 + leave 1 => 21 absent.
        $this->assertEquals(21, $month['totals']['unpaid_days']);

        $this->web($accounts)->post('/salary/generate', ['month' => '2026-11'])->assertSessionHasNoErrors();
        $sheet = SalarySheet::where('user_id', $emp->id)->first();
        $this->assertEquals(round(26000 / 30 * 21, 2), $sheet->unpaid_amount);
        $this->assertEquals(round(90 / 60 * 26000 / (26 * 9), 2), $sheet->ot_amount);              // 166.67
        $this->assertEquals(2000, $sheet->advance_deduction);
        $this->assertEquals(round(26000 - $sheet->unpaid_amount + $sheet->ot_amount - 2000, 2), $sheet->net);

        $this->web($accounts)->post('/salary/finalize', ['month' => '2026-11'])->assertSessionHasNoErrors();
        $this->assertEquals(8000, $emp->fresh()->dueAmount());
        $this->web($accounts)->post('/salary/generate', ['month' => '2026-11'])->assertSessionHasErrors('month');
        $this->web($manager)->post("/attendance/day/{$att->id}/correct", ['login_time' => '09:30', 'remark' => 'x'])->assertSessionHasErrors('remark');
        $this->api($empToken, 'get', '/profile')->assertOk()->assertJsonPath('salary.due_amount', 8000)->assertJsonCount(1, 'payslips');
        $this->api($empToken, 'get', '/attendance?month=2026-11')->assertOk()->assertJsonPath('days.1.code', 'P')->assertJsonCount(2, 'days.1.movements');
        $this->api($empToken, 'get', '/notices')->assertOk();

        // Manager sees only own team; reset phone lets a new phone register.
        $other = User::create(['emp_code' => 'E9', 'name' => 'Other Team', 'email' => 'e9@x.in', 'password' => 'secret123',
            'role' => 'employee', 'emp_type' => 'operation']);
        $this->web($manager)->get('/employees')->assertOk()->assertSee('Op Emp')->assertDontSee('Other Team');
        $this->web($admin)->post("/employees/{$emp->id}/reset-phone")->assertSessionHasNoErrors();
        $this->api('', 'post', '/login', ['emp_code' => 'E1', 'password' => 'secret123', 'device_id' => 'phone-2'])->assertOk();

        // ----- Every web page opens -----
        foreach (['/', '/employees', '/employees/new', "/employees/{$emp->id}/edit", '/attendance?date=2026-11-02', '/attendance/monthly?month=2026-11',
                     "/attendance/day/{$att->id}", '/attendance/pending', '/attendance/outside', '/leave', '/salary/advances',
                     "/salary/advances/{$emp->id}", '/salary/sheet?month=2026-11', '/reports?month=2026-11', '/settings', '/password'] as $page) {
            $this->web($admin)->get($page)->assertOk();
        }
        $this->web($admin)->get('/reports?month=2026-11&download=1')->assertOk()->assertDownload('attendance-report-2026-11.csv');
    }
}
