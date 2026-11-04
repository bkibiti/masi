<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MASI started fining unpaid contributions on 1 March 2026: on that date every month still
 * unpaid (January, February and anything older) got its first penalty, and month-ends before it
 * carried no fine. Settings get a "penalties start on" date; existing ones that begin before
 * 1 March 2026 are set to it. Run `php artisan contributions:refresh` afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_settings', function (Blueprint $table) {
            $table->date('penalties_start_on')->nullable()->after('penalty_percent');
        });

        DB::table('contribution_settings')
            ->where('start_date', '<', '2026-03-01')
            ->update(['penalties_start_on' => '2026-03-01']);
    }

    public function down(): void
    {
        Schema::table('contribution_settings', function (Blueprint $table) {
            $table->dropColumn('penalties_start_on');
        });
    }
};
