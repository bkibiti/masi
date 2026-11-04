<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-only login management for members (create, link, reset, disable). Every action here
 * requires "Manage Members", the same permission that already gates editing a member.
 */
class MemberLoginController extends Controller
{
    public function create(Member $member): RedirectResponse
    {
        abort_if($member->user_id !== null, 400, 'This member already has a login.');

        $password = Str::password(12);

        $user = new User;
        $user->name = $member->name;
        $user->username = $this->generateUsername($member->name);
        $user->email = $member->email;
        $user->mobile = $member->phone;
        $user->status = 1;
        $user->must_change_password = true;
        $user->password = Hash::make($password);
        $user->save();

        $user->assignRole('Member Login');

        $member->user_id = $user->id;
        $member->save();

        session()->flash('alert-success', 'Login created for '.$member->name.'.');
        session()->flash('temp_password', $password);
        session()->flash('temp_password_username', $user->username);

        return back();
    }

    public function link(Request $request, Member $member): RedirectResponse
    {
        abort_if($member->user_id !== null, 400, 'This member already has a login.');

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', Rule::unique('members', 'user_id')],
        ]);

        $user = User::findOrFail($data['user_id']);
        $user->assignRole('Member Login');

        $member->user_id = $user->id;
        $member->save();

        session()->flash('alert-success', 'Login linked to '.$member->name.'.');

        return back();
    }

    public function resetPassword(Member $member): RedirectResponse
    {
        abort_if($member->user_id === null, 400, 'This member has no login yet.');

        $password = Str::password(12);

        $user = $member->user;
        $user->password = Hash::make($password);
        $user->must_change_password = true;
        $user->save();

        session()->flash('alert-success', 'Password reset for '.$member->name.'.');
        session()->flash('temp_password', $password);
        session()->flash('temp_password_username', $user->username);

        return back();
    }

    public function disable(Member $member): RedirectResponse
    {
        abort_if($member->user_id === null, 400, 'This member has no login yet.');

        $user = $member->user;
        $user->status = 0;
        $user->save();

        session()->flash('alert-success', 'Login disabled for '.$member->name.'.');

        return back();
    }

    public function enable(Member $member): RedirectResponse
    {
        abort_if($member->user_id === null, 400, 'This member has no login yet.');

        $user = $member->user;
        $user->status = 1;
        $user->save();

        session()->flash('alert-success', 'Login enabled for '.$member->name.'.');

        return back();
    }

    public function bulkCreateForm(): View
    {
        $members = Member::whereNull('user_id')->orderBy('name')->get();

        return view('members.bulk_logins_confirm', compact('members'));
    }

    /**
     * Creates a login for every member without one and shows every temporary password once,
     * on a printable page. Nothing here is re-fetchable afterwards.
     *
     * @return View
     */
    public function bulkCreateStore()
    {
        $members = Member::whereNull('user_id')->orderBy('name')->get();

        $created = $members->map(function (Member $member) {
            $password = Str::password(12);

            $user = new User;
            $user->name = $member->name;
            $user->username = $this->generateUsername($member->name);
            $user->email = $member->email;
            $user->mobile = $member->phone;
            $user->status = 1;
            $user->must_change_password = true;
            $user->password = Hash::make($password);
            $user->save();

            $user->assignRole('Member Login');

            $member->user_id = $user->id;
            $member->save();

            return [
                'member' => $member,
                'username' => $user->username,
                'password' => $password,
            ];
        });

        return view('members.bulk_logins_result', ['created' => $created]);
    }

    /**
     * A unique, <=20-char username derived from the member's name, with a numeric suffix
     * if needed (matches the `username` column's size and uniqueness constraint).
     */
    private function generateUsername(string $name): string
    {
        $base = Str::of($name)->slug('.')->limit(16, '')->toString();
        $base = $base !== '' ? $base : 'member';

        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $suffix++;
            $username = Str::limit($base, 20 - strlen((string) $suffix), '').$suffix;
        }

        return $username;
    }
}
