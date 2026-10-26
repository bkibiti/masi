<?php

namespace App\Http\Controllers;

use App\Models\Member;
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
     * Members dashboard.
     */
    public function index(): Renderable
    {
        $stats = [
            'members' => Member::query()->count(),
        ];

        return view('home', compact('stats'));
    }
}
