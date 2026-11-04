<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // login form
    public function login()
    {
        return view('auth.login');
    }

    /**
     * The post-login landing page: the group dashboard, read-only, for every logged-in user
     * (every role now holds "View Dashboard"). A user linked to a member also gets a "My
     * summary" row above it.
     */
    public function index(Request $request, DashboardService $dashboard): Renderable
    {
        $year = (int) $request->query('year', now()->year);
        $member = $request->user()->member;

        return view('dashboard', [
            'year' => $year,
            'years' => $dashboard->availableYears(),
            'totals' => $dashboard->totalsCard($year),
            'monthlyFees' => $dashboard->monthlyFeesCard($year),
            'arrears' => $dashboard->arrearsCard(),
            'chart' => $dashboard->monthlyChart($year),
            'currentMonth' => $dashboard->currentMonthPanel(),
            'recentPayments' => $dashboard->recentPayments(),
            'mySummary' => $member ? $dashboard->mySummary($member) : null,
        ]);
    }
}
