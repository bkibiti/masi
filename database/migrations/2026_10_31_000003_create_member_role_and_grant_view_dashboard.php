<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('roles')->where('name', 'Member')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Member',
                'description' => 'Every member\'s own login: group dashboard, My Account, loan applications and guarantee responses',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // "View Dashboard" already exists in the real (hand-seeded) permissions table, but the
        // test-only schema (see AppServiceProvider) starts empty, so create it if missing.
        if (! DB::table('permissions')->where('name', 'View Dashboard')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'View Dashboard',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $viewDashboardId = DB::table('permissions')->where('name', 'View Dashboard')->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Superuser', 'Manager', 'Partners', 'Loan Officer', 'Treasurer', 'Member'])
            ->pluck('id', 'name');

        $alreadyGranted = DB::table('role_has_permissions')
            ->where('permission_id', $viewDashboardId)
            ->whereIn('role_id', $roleIds)
            ->pluck('role_id')
            ->all();

        $toGrant = [];
        foreach ($roleIds as $roleId) {
            if (! in_array($roleId, $alreadyGranted, true)) {
                $toGrant[] = ['role_id' => $roleId, 'permission_id' => $viewDashboardId];
            }
        }

        if (! empty($toGrant)) {
            DB::table('role_has_permissions')->insert($toGrant);
        }
    }

    public function down(): void
    {
        $viewDashboardId = DB::table('permissions')->where('name', 'View Dashboard')->value('id');
        $memberRoleId = DB::table('roles')->where('name', 'Member')->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Manager', 'Partners', 'Loan Officer', 'Treasurer', 'Member'])
            ->pluck('id');

        DB::table('role_has_permissions')
            ->where('permission_id', $viewDashboardId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        if ($memberRoleId) {
            DB::table('model_has_roles')->where('role_id', $memberRoleId)->delete();
            DB::table('roles')->where('id', $memberRoleId)->delete();
        }
    }
};
