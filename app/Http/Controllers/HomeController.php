<?php

namespace App\Http\Controllers;

use App\Models\Member;
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
     * The post-login landing page. Users with "View Dashboard" see the admin dashboard;
     * everyone else keeps the plain landing page, so this stays the universal redirect
     * target for every role without 403-ing anyone who hasn't been granted it yet.
     */
    public function index(Request $request, DashboardService $dashboard): Renderable
    {
        if (! auth()->user()->can('View Dashboard')) {
            $stats = [
                'members' => Member::query()->count(),
            ];

            return view('home', compact('stats'));
        }

        $year = (int) $request->query('year', now()->year);

        return view('dashboard', [
            'year' => $year,
            'years' => $dashboard->availableYears(),
            'totals' => $dashboard->totalsCard($year),
            'monthlyFees' => $dashboard->monthlyFeesCard($year),
            'arrears' => $dashboard->arrearsCard(),
            'chart' => $dashboard->monthlyChart($year),
            'currentMonth' => $dashboard->currentMonthPanel(),
            'recentPayments' => $dashboard->recentPayments(),
        ]);
    }
}
