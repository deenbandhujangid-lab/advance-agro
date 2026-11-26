<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Monthly summary per employee; ?download=1 gives an Excel-readable CSV file. */
    public function index(Request $request)
    {
        $month = $request->query('month', today()->format('Y-m'));
        $rows = User::visibleTo($request->user())->where('status', 'active')->orderBy('name')->get()
            ->map(fn ($u) => ['user' => $u, 't' => AttendanceService::month($u, $month)['totals']]);

        if ($request->boolean('download')) {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Code', 'Name', 'Type', 'Present', 'Late', 'Grace used', 'Half day', 'Absent', 'Leave',
                    'Off days', 'Logout pending', 'Unpaid days', 'Overtime hours', 'Outside hours']);
                foreach ($rows as $r) {
                    $t = $r['t'];
                    fputcsv($out, [$r['user']->emp_code, $r['user']->name, ucfirst((string) $r['user']->emp_type),
                        $t['present'], $t['late'], $t['grace'], $t['half'], $t['absent'], $t['leave'], $t['off'],
                        $t['pending'], $t['unpaid_days'], round($t['ot_minutes'] / 60, 2), round($t['outside_minutes'] / 60, 2)]);
                }
                fclose($out);
            }, "attendance-report-$month.csv", ['Content-Type' => 'text/csv']);
        }

        return view('reports.index', compact('rows', 'month'));
    }
}
