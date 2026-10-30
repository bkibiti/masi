<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only equivalent of the real (hand-managed) `settings` table, read by the
 * groupName()/groupLogoUrl() helpers that every page layout calls. See the note in
 * 2025_01_01_000001_create_users_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 45);
            $table->string('display_name', 45)->nullable();
            $table->string('value')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
