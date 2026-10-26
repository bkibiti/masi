<?php

namespace App\Http\Controllers;

use App\Models\Death;
use App\Models\Dependant;
use App\Models\DependantCategory;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;

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
     * Members dashboard: membership numbers, dependants and this year's deaths.
     */
    public function index(): Renderable
    {
        $yearStart = CarbonImmutable::now('Africa/Dar_es_Salaam')->startOfYear()->toDateString();

        $membersByStatus = Member::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $livingByGender = Member::alive()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $dependantsByRelationship = Dependant::query()
            ->where('status', 'Alive')
            ->selectRaw('dependant_category_id, count(*) as total')
            ->groupBy('dependant_category_id')
            ->pluck('total', 'dependant_category_id');

        $deathsThisYear = Death::active()->where('date_of_death', '>=', $yearStart);

        $stats = [
            'active' => (int) ($membersByStatus['Active'] ?? 0),
            'inactive' => (int) ($membersByStatus['Inactive'] ?? 0),
            'deceased' => (int) ($membersByStatus['Deceased'] ?? 0),
            'male' => (int) ($livingByGender['Male'] ?? 0),
            'female' => (int) ($livingByGender['Female'] ?? 0),
            'dependants' => (int) $dependantsByRelationship->sum(),
            'member_deaths' => (clone $deathsThisYear)->whereNull('dependant_id')->count(),
            'dependant_deaths' => (clone $deathsThisYear)->whereNotNull('dependant_id')->count(),
        ];

        $recentDeaths = Death::active()
            ->whereNotNull('date_of_death')
            ->with('member')
            ->latest('date_of_death')
            ->latest('id')
            ->limit(8)
            ->get();

        $categories = DependantCategory::ordered()->get();

        return view('home', compact('stats', 'dependantsByRelationship', 'recentDeaths', 'categories'));
    }
}
