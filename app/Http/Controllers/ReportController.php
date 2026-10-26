<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function getReport(Request $request)
    {
        $request->validate(['report' => 'required|in:3']);
        $request->flash();

        switch ($request->report) {
            case 3:
                $data = $this->totalMonthlyExpenses();

                if ($request->action == 'print') {
                    return PDF::loadView('reports.total_monthly_expenses_pdf', compact('data'))->stream();
                }

                return view('reports.total_monthly_expenses', compact('data'));
        }

        return back();
    }

    private function totalMonthlyExpenses()
    {
        return DB::table('expenses')
            ->select(DB::raw("DATE_FORMAT(created_at, '%b %Y') month,sum(amount) amount"))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y%m')"), DB::raw("DATE_FORMAT(created_at, '%b %Y')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y%m')"), 'desc')
            ->limit(12)
            ->get();
    }
}
