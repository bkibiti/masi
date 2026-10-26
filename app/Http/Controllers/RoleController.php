<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::get();
        $permissions = Permission::get();

        return view('roles.index', compact('roles', 'permissions'));
    }

    public function create()
    {
        $permissions = Permission::get();

        return view('roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'unique:roles|required|string|min:2|max:50',
            'description' => 'required|string|min:5|max:50',
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // The form sends permission IDs; laravel-permission v6 treats plain strings
        // as permission NAMES, so pass the Permission models instead.
        $role->syncPermissions($this->selectedPermissions($request));

        session()->flash('alert-success', 'Role created successfully!');

        return redirect()->route('roles.index');
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);

        // Cast to int: pluck('id') can come back as strings depending on the DB driver's
        // PDO attributes, which broke the view's in_array() ticking check on some servers.
        $rolePermissions = $role->permissions()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $permissionsAll = Permission::get();

        return view('roles.edit', compact('role', 'permissionsAll', 'rolePermissions'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:roles,id',
            'name' => ['required', 'string', 'min:2', 'max:50', Rule::unique('roles', 'name')->ignore($request->id)],
            'description' => 'required|string|min:5|max:50',
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::findOrFail($request->id);
        $role->name = $request->name;
        $role->description = $request->description;
        $role->save();

        $role->syncPermissions($this->selectedPermissions($request));

        session()->flash('alert-success', 'Role updated successfully!');

        return redirect()->route('roles.index');
    }

    public function destroy(Request $request)
    {
        $role = Role::find($request->role_id);

        if ($role) {
            $role->syncPermissions([]);
            $role->delete();
        }

        session()->flash('alert-success', 'Role deleted successfully!');

        return back();
    }

    /** The ticked permission IDs from the form, as Permission models. */
    private function selectedPermissions(Request $request)
    {
        $ids = array_map('intval', (array) $request->input('permissions', []));

        return Permission::whereIn('id', $ids)->get();
    }
}
