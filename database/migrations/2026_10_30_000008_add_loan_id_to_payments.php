<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('payment_type_id')->constrained('loans')->restrictOnDelete();
        });

        // The "Loan Repayment" type existed with ledger_role=none (recorded only, ignored by
        // ContributionLedger). It has never been used on a payment, so flipping its role is a
        // safe, no-backfill change. Loan repayments now get their own ledger_role so they can be
        // routed to LoanLedger instead, and PaymentType::isLedgerRelevant() is a whitelist of
        // monthly_fee/penalty, so this new role still never reaches ContributionLedger.
        DB::table('payment_types')->where('name', 'Loan Repayment')->update(['ledger_role' => 'loan']);
    }

    public function down(): void
    {
        DB::table('payment_types')->where('name', 'Loan Repayment')->update(['ledger_role' => 'none']);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->dropColumn('loan_id');
        });
    }
};
