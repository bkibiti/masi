<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissionNames = [
            'View Loan Types',
            'Manage Loan Types',
            'View Loans',
            'Manage Loans',
            'Approve Loans',
            'Disburse Loans',
            'Record Loan Repayments',
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

        DB::table('roles')->insert([
            ['name' => 'Loan Officer', 'description' => 'Approves or rejects loan applications', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Treasurer', 'description' => 'Releases loan funds and records repayments', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $loanOfficerId = DB::table('roles')->where('name', 'Loan Officer')->value('id');
        $treasurerId = DB::table('roles')->where('name', 'Treasurer')->value('id');
        $superuserId = DB::table('roles')->where('name', 'Superuser')->value('id');

        $rolePermissions = [];

        foreach (['View Loans', 'Approve Loans'] as $name) {
            $rolePermissions[] = ['role_id' => $loanOfficerId, 'permission_id' => $permissionIds[$name]];
        }

        foreach (['View Loans', 'Disburse Loans', 'Record Loan Repayments'] as $name) {
            $rolePermissions[] = ['role_id' => $treasurerId, 'permission_id' => $permissionIds[$name]];
        }

        if ($superuserId) {
            foreach ($permissionNames as $name) {
                $rolePermissions[] = ['role_id' => $superuserId, 'permission_id' => $permissionIds[$name]];
            }
        }

        DB::table('role_has_permissions')->insert($rolePermissions);

        // settings.id has no AUTO_INCREMENT (legacy table); pick an id clear of the existing
        // 100-105 block used by ConfigurationsController::KEYS.
        DB::table('settings')->insert([
            'id' => 110,
            'name' => 'loan_guarantor_threshold_percent',
            'display_name' => 'Loan Guarantor Threshold %',
            'value' => '80',
            'updated_by' => null,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('name', 'loan_guarantor_threshold_percent')->delete();

        $roleIds = DB::table('roles')->whereIn('name', ['Loan Officer', 'Treasurer'])->pluck('id');
        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();

        $permissionNames = [
            'View Loan Types',
            'Manage Loan Types',
            'View Loans',
            'Manage Loans',
            'Approve Loans',
            'Disburse Loans',
            'Record Loan Repayments',
        ];
        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
