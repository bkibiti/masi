<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Due dates now run monthly from the release (disbursement) date instead of month-ends.
 * Moves the due dates of loans already released onto the new rule. Amounts are untouched.
 * Run `php artisan loans:refresh` afterwards so penalties and statuses follow the new dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        $loans = DB::table('loans')
            ->whereNotNull('disbursed_on')
            ->whereIn('status', ['disbursed', 'completed'])
            ->get(['id', 'disbursed_on']);

        foreach ($loans as $loan) {
            $released = CarbonImmutable::parse($loan->disbursed_on)->startOfDay();

            foreach (DB::table('loan_installments')->where('loan_id', $loan->id)->get(['id', 'number']) as $installment) {
                DB::table('loan_installments')->where('id', $installment->id)->update([
                    'due_date' => $released->addMonthsNoOverflow((int) $installment->number)->toDateString(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $loans = DB::table('loans')
            ->whereNotNull('disbursed_on')
            ->whereIn('status', ['disbursed', 'completed'])
            ->get(['id', 'disbursed_on']);

        foreach ($loans as $loan) {
            $released = CarbonImmutable::parse($loan->disbursed_on);

            foreach (DB::table('loan_installments')->where('loan_id', $loan->id)->get(['id', 'number']) as $installment) {
                DB::table('loan_installments')->where('id', $installment->id)->update([
                    'due_date' => $released->addMonthsNoOverflow((int) $installment->number)->endOfMonth()->toDateString(),
                ]);
            }
        }
    }
};
