<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * This app already has a staff-facing "Members" role (plural) predating this feature.
 * Renamed to "Member Login" to keep the self-service role this task adds unmistakably
 * separate from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'Member')->update(['name' => 'Member Login']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Member Login')->update(['name' => 'Member']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
