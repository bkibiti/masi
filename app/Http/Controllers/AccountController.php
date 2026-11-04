<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function forcePasswordForm(): View
    {
        return view('account.force_password');
    }

    public function forcePasswordUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        session()->flash('alert-success', 'Password changed. You can now continue.');

        return redirect()->route('home');
    }
}
