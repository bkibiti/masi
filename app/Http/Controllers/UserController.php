<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class UserController extends Controller
{
    use HasRoles;

    public function index()
    {
        $users = User::where('status', 1)->orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'position' => 'nullable|string|max:50',
            'role' => 'required',
            'email' => 'string|email|max:255',
            'username' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $roles = $this->selectedRoles($request->role);
        if ($roles->isEmpty()) {
            return back()->withErrors(['role' => 'Select a valid role.'])->withInput();
        }

        // Save the user and the role together: no user without a role
        DB::transaction(function () use ($request, $roles) {
            $user = new User;
            $user->name = $request->name;
            $user->username = $request->username;
            $user->position = $request->position;
            $user->email = $request->email;
            $user->mobile = $request->mobile;
            $user->status = '1';
            $user->password = Hash::make($request->password);
            $user->save();

            $user->syncRoles($roles);
        });

        session()->flash('alert-success', 'User created successfully!');

        return back();
    }

    public function update(Request $request)
    {
        $request->validate([
            'name1' => 'required|string|max:100',
            'role1' => 'required',
            'position1' => 'nullable|string|max:50',
        ]);

        $roles = $this->selectedRoles($request->role1);
        if ($roles->isEmpty()) {
            return back()->withErrors(['role1' => 'Select a valid role.'])->withInput();
        }

        $user = User::findOrFail($request->UserID);
        $user->name = $request->name1;
        $user->position = $request->position1;
        $user->mobile = $request->mobile1;
        $user->save();

        $user->syncRoles($roles);

        session()->flash('alert-success', 'User updated successfully!');

        return back();
    }

    public function deActivate(Request $request)
    {

        if ($request->status == 1) {
            $user = new User;
            $user = User::findOrFail($request->userid);
            $user->status = 0;
            $user->save();

            session()->flash('alert-success', 'User de-activated successfully!');

            // return redirect()->back();
            return redirect()->route('users.index');
        }
        if ($request->status == 0) {
            $user = new User;
            $user = User::findOrFail($request->userid);
            $user->status = 1;
            $user->save();

            session()->flash('alert-success', 'User activated successfully!');

            // return redirect()->back();
            return redirect()->route('users.index');

        }

    }

    public function changePasswordForm()
    {
        return view('users.change_password');
    }

    public function changePassword(Request $request)
    {
        if (! (Hash::check($request->current_password, Auth::user()->password))) {
            // The passwords matches
            session()->flash('alert-danger', 'Your current password does not matches with the password you provided. Please try again.');

            return redirect()->back();
        }

        if (strcmp($request->current_password, $request->new_password) == 0) {
            // Current password and new password are same
            session()->flash('alert-danger', 'New Password cannot be same as your current password. Please choose a different password.');

            return redirect()->back();
        }

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Change Password
        $user = Auth::user();
        $user->password = Hash::make($request->new_password);
        $user->save();

        session()->flash('alert-success', 'Password changed successfully !');

        return redirect()->back();

    }

    /**
     * The form sends role IDs (role[] / role1[]). laravel-permission v6 treats plain
     * values as role NAMES, so turn the IDs into Role models.
     */
    private function selectedRoles($input)
    {
        $ids = array_map('intval', (array) $input);

        return Role::whereIn('id', $ids)->get();
    }

    public function search(Request $request)
    {
        if ($request->status == '0') {
            $users = User::all();
        }
        if ($request->status == '1') {
            $users = User::where('status', 1)->get();
        }
        if ($request->status == '2') {
            $users = User::where('status', 0)->get();
        }

        $request->flash();

        return view('users.index', compact('users'));

    }
}
