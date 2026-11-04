<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule rounding per loan type (copied onto each loan, like the rate):
 *  - installment_rounding: installment rounded UP to a multiple of this (1 = exact TZS)
 *  - interest_rounding: monthly interest rounded to the NEAREST multiple of this
 * Existing loan types switch to the MASI Excel convention (1,000 / 100), and so do loans not yet
 * disbursed. Disbursed loans keep the schedule they already have (1 / 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_types', function (Blueprint $table) {
            $table->unsignedInteger('installment_rounding')->default(1)->after('penalty_percent');
            $table->unsignedInteger('interest_rounding')->default(1)->after('installment_rounding');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedInteger('installment_rounding')->default(1)->after('penalty_percent');
            $table->unsignedInteger('interest_rounding')->default(1)->after('installment_rounding');
        });

        DB::table('loan_types')->update(['installment_rounding' => 1000, 'interest_rounding' => 100]);

        DB::table('loans')
            ->whereIn('status', ['pending_guarantors', 'pending_approval', 'approved'])
            ->update(['installment_rounding' => 1000, 'interest_rounding' => 100]);
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['installment_rounding', 'interest_rounding']);
        });

        Schema::table('loan_types', function (Blueprint $table) {
            $table->dropColumn(['installment_rounding', 'interest_rounding']);
        });
    }
};
