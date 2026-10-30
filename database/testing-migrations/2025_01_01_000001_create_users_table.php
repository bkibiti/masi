<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only: the real app's `users` table is hand-managed (see .ai/rules/database.md) and has
 * no production migration. This gives the testing sqlite database just enough of that shape to
 * exercise auth and `recorded_by` in tests, without ever running against the real database
 * (see AppServiceProvider::boot(), which only loads this path when running tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('username', 20)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('email')->unique();
            $table->string('mobile', 45)->nullable();
            $table->string('password');
            $table->tinyInteger('status')->default(1);
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
