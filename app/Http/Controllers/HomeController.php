<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\GroupStatusService;
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
     * The post-login landing page: the group status dashboard, read-only, for every logged-in
     * user.
     */
    public function index(Request $request, DashboardService $dashboard, GroupStatusService $groupStatus): Renderable
    {
        $year = (int) $request->query('year', now('Africa/Dar_es_Salaam')->year);

        return view('dashboard', [
            'year' => $year,
            'years' => $dashboard->availableYears(),
            'status' => $groupStatus->build($year),
        ]);
    }
}
