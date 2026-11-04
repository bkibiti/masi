<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My Profile" (spec section 3): name is read-only; mobile/email and password are the
 * member's own to change. Always scoped to auth()->user()->member.
 */
class MyProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['member' => $request->user()->member]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $request->user()->member;

        $data = $request->validate([
            'phone' => ['required', 'regex:/^255[0-9]{9}$/', Rule::unique('members', 'phone')->ignore($member->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member->id)],
        ], [
            'phone.regex' => 'Mobile must be 12 digits starting with 255, e.g. 255745777602.',
        ]);

        $member->phone = $data['phone'];
        $member->email = $data['email'] ?? null;
        $member->updated_by = $request->user()->id;
        $member->save();

        $user = $request->user();
        $user->mobile = $member->phone;
        $user->email = $member->email;
        $user->save();

        session()->flash('alert-success', 'Profile updated.');

        return back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($request->new_password);
        $user->must_change_password = false;
        $user->save();

        session()->flash('alert-success', 'Password changed.');

        return back();
    }
}
