<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissionNames = [
            'View Fine Types',
            'Manage Fine Types',
            'View Fines',
            'Manage Fines',
        ];

        foreach ($permissionNames as $name) {
            DB::table('permissions')->insert([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id', 'name');
        $superuserId = DB::table('roles')->where('name', 'Superuser')->value('id');

        if ($superuserId) {
            $rolePermissions = [];
            foreach ($permissionNames as $name) {
                $rolePermissions[] = ['role_id' => $superuserId, 'permission_id' => $permissionIds[$name]];
            }
            DB::table('role_has_permissions')->insert($rolePermissions);
        }
    }

    public function down(): void
    {
        $permissionNames = [
            'View Fine Types',
            'Manage Fine Types',
            'View Fines',
            'Manage Fines',
        ];
        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
