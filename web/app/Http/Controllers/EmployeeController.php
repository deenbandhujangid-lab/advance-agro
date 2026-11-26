<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $employees = User::visibleTo($request->user())->with('manager')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%$q%")
                ->orWhere('emp_code', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")))
            ->orderBy('name')->get();

        return view('employees.index', compact('employees', 'q'));
    }

    public function create()
    {
        return view('employees.form', ['employee' => new User(['status' => 'active', 'role' => 'employee',
            'emp_type' => 'operation', 'morning_time' => '09:30', 'evening_time' => '18:30', 'sunday_rule' => 'all',
            'off_sundays' => '2,4', 'ot_allowed' => true, 'joined_on' => today()]), 'managers' => $this->managers()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, new User);
        User::create($data);

        return redirect()->route('employees.index')->with('ok', 'Employee added.');
    }

    public function edit(User $employee)
    {
        abort_if($employee->isSuperAdmin(), 404);

        return view('employees.form', ['employee' => $employee, 'managers' => $this->managers($employee->id)]);
    }

    public function update(Request $request, User $employee)
    {
        abort_if($employee->isSuperAdmin(), 404);
        $employee->update($this->validated($request, $employee));

        return redirect()->route('employees.index')->with('ok', 'Employee saved.');
    }

    public function resetDevice(User $employee)
    {
        $employee->update(['device_id' => null]);
        $employee->tokens()->delete();

        return back()->with('ok', 'Phone reset. '.$employee->name.' can now login from a new phone.');
    }

    public function toggle(User $employee)
    {
        abort_if($employee->isSuperAdmin(), 404);
        $employee->update(['status' => $employee->status === 'active' ? 'inactive' : 'active']);
        if ($employee->status === 'inactive') {
            $employee->tokens()->delete();
        }

        return back()->with('ok', $employee->name.' is now '.$employee->status.'.');
    }

    private function managers(?int $exceptId = null)
    {
        return User::where('role', 'manager')->where('status', 'active')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->orderBy('name')->get();
    }

    private function validated(Request $request, User $employee): array
    {
        $data = $request->validate([
            'emp_code' => ['required', 'string', 'max:30', Rule::unique('users')->ignore($employee->id)],
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('users')->ignore($employee->id)],
            'phone' => ['required', 'digits:10', Rule::unique('users')->ignore($employee->id)],
            'password' => [$employee->exists ? 'nullable' : 'required', 'string', 'min:6'],
            'role' => 'required|in:manager,account_manager,employee',
            'emp_type' => 'required|in:sales,operation',
            'manager_id' => 'nullable|exists:users,id',
            'joined_on' => 'required|date',
            'morning_time' => 'required|date_format:H:i',
            'evening_time' => 'required|date_format:H:i|after:morning_time',
            'sunday_rule' => 'required|in:all,two',
            'off_sundays' => 'nullable|array',
            'off_sundays.*' => 'integer|between:1,5',
            'ot_allowed' => 'nullable|boolean',
            'basic_salary' => 'required|numeric|min:0',
        ], [
            'evening_time.after' => 'Evening time must be after morning time.',
            'phone.digits' => 'Contact number must be 10 digits.',
        ]);

        if ($data['sunday_rule'] === 'two' && count($data['off_sundays'] ?? []) !== 2) {
            abort(back()->withInput()->withErrors(['off_sundays' => 'Choose exactly 2 Sundays.']));
        }
        $data['off_sundays'] = implode(',', $data['off_sundays'] ?? [2, 4]);
        $data['ot_allowed'] = $request->boolean('ot_allowed');
        if ($employee->exists) {
            unset($data['emp_code']);                    // the code never changes after saving
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
