<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * The login form posts a field called "username".
     */
    public function username()
    {
        return 'username';
    }

    /**
     * Accept either an email address or a username in the "username" box.
     */
    protected function credentials(Request $request)
    {
        $login = $request->input('username');

        $field = (filter_var($login, FILTER_VALIDATE_EMAIL) || !Schema::hasColumn('users', 'username'))
            ? 'email'
            : 'username';

        return [
            $field     => $login,
            'password' => $request->input('password'),
        ];
    }
}
