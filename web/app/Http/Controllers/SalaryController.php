<?php

namespace App\Http\Controllers;

use App\Models\AdvanceEntry;
use App\Models\Notice;
use App\Models\SalarySheet;
use App\Models\User;
use App\Services\SalaryService;
use DomainException;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    /** Advance given, recovered and due amount per employee. */
    public function advances()
    {
        $rows = User::where('role', '!=', 'super_admin')->with('advanceEntries')->orderBy('name')->get()->map(function ($u) {
            $given = $u->advanceEntries->where('type', 'advance')->sum('amount');
            $back = $u->advanceEntries->whereIn('type', ['deduction', 'repayment'])->sum('amount');

            return ['user' => $u, 'given' => $given, 'back' => $back, 'due' => max(0, $given - $back)];
        });

        return view('salary.advances', compact('rows'));
    }

    public function statement(User $employee)
    {
        return view('salary.statement', [
            'employee' => $employee,
            'entries' => $employee->advanceEntries()->orderBy('date')->orderBy('id')->get(),
        ]);
    }

    /** Give an advance or record a cash repayment. */
    public function storeEntry(Request $request, User $employee)
    {
        $data = $request->validate([
            'type' => 'required|in:advance,repayment',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1',
            'remark' => 'nullable|string|max:200',
        ]);
        if ($data['type'] === 'repayment' && $data['amount'] > $employee->dueAmount()) {
            return back()->withInput()->withErrors(['amount' => 'Repayment cannot be more than the due amount (Rs '.number_format($employee->dueAmount(), 2).').']);
        }
        AdvanceEntry::create($data + ['user_id' => $employee->id, 'created_by' => $request->user()->id]);
        if ($data['type'] === 'advance') {
            Notice::send($employee->id, 'Advance given', 'Rs '.number_format($data['amount'], 2).' added to your due amount.');
        }

        return back()->with('ok', ucfirst($data['type']).' saved.');
    }

    public function setDeduction(Request $request, User $employee)
    {
        $data = $request->validate(['deduction_per_month' => 'required|numeric|min:0']);
        $employee->update($data);

        return back()->with('ok', 'Deduction per month saved.');
    }

    public function sheet(Request $request)
    {
        $month = $request->query('month', today()->subMonthNoOverflow()->format('Y-m'));

        return view('salary.sheet', [
            'month' => $month,
            'sheets' => SalarySheet::with('user')->where('month', $month)->get()->sortBy('user.name'),
            'finalized' => SalaryService::isFinalized($month),
        ]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        try {
            SalaryService::generate($data['month']);
        } catch (DomainException $e) {
            return redirect()->route('salary.sheet', ['month' => $data['month']])->withErrors(['month' => $e->getMessage()]);
        }

        return redirect()->route('salary.sheet', ['month' => $data['month']])->with('ok', 'Salary sheet generated. Check it, then press Finalize.');
    }

    public function finalize(Request $request)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        try {
            SalaryService::finalize($data['month'], $request->user());
        } catch (DomainException $e) {
            return redirect()->route('salary.sheet', ['month' => $data['month']])->withErrors(['month' => $e->getMessage()]);
        }

        return redirect()->route('salary.sheet', ['month' => $data['month']])->with('ok', 'Salary finalized. Advance deductions are recovered.');
    }
}
