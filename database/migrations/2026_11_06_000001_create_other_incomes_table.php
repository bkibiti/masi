<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Group income that does not belong to any member: an opening balance that cannot be
 * attributed to a member, bank interest, donations, etc. Counted in cash available and
 * the group fund, never in any member's contributions.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['View Other Income', 'Manage Other Income'];

    public function up(): void
    {
        // A failed earlier run may have left a half-created table behind.
        Schema::dropIfExists('other_incomes');

        Schema::create('other_incomes', function (Blueprint $table) {
            $table->id();
            $table->date('received_on');
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('received_on');
        });

        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id', 'name');

        // Superuser gets both. Roles that can record expenses can manage other income;
        // roles that can see expenses can see other income.
        $grant = function ($roleIds, array $names) use ($ids) {
            foreach ($roleIds as $roleId) {
                foreach ($names as $name) {
                    DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $ids[$name]]);
                }
            }
        };

        $grant(DB::table('roles')->where('name', 'Superuser')->pluck('id'), self::PERMISSIONS);

        $expensePermission = fn (array $names) => DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('permissions.name', $names)
            ->distinct()->pluck('role_has_permissions.role_id');

        $grant($expensePermission(['Add Expenses', 'Edit Expenses']), self::PERMISSIONS);
        $grant($expensePermission(['View Expense Management', 'View Expenses']), ['View Other Income']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('other_incomes');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
