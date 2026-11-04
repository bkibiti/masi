<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes the sales/POS/inventory/purchase permissions left over from the ABG codebase.
 * Nothing in MASI uses them. Detaches them from every role and user, then deletes them.
 */
return new class extends Migration
{
    private const NAMES = [
        'POS Users',
        'Edit Sales',
        'View Sales Reports',
        'View Inventory Reports',
        'View Purchase Reports',
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::NAMES)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::NAMES as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
