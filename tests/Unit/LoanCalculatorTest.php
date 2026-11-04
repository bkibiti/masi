<?php

namespace Tests\Unit;

use App\Models\LoanType;
use App\Services\LoanCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LoanCalculatorTest extends TestCase
{
    /**
     * Scenario (a): 1,000,000 at 2%/month over 6 months, monthly reducing balance.
     */
    public function test_a_reducing_balance_schedule_amortises_correctly(): void
    {
        $calculator = new LoanCalculator;

        $rows = $calculator->installments(1_000_000, 2.0, 6, LoanType::METHOD_MONTHLY_REDUCING, CarbonImmutable::parse('2026-01-15'));

        $this->assertCount(6, $rows);

        $expected = [
            ['interest' => 20000.0, 'principal' => 158526.0, 'amount' => 178526.0],
            ['interest' => 16829.0, 'principal' => 161697.0, 'amount' => 178526.0],
            ['interest' => 13596.0, 'principal' => 164930.0, 'amount' => 178526.0],
            ['interest' => 10297.0, 'principal' => 168229.0, 'amount' => 178526.0],
            ['interest' => 6932.0, 'principal' => 171594.0, 'amount' => 178526.0],
            ['interest' => 3500.0, 'principal' => 175024.0, 'amount' => 178524.0], // rounding on the last installment
        ];

        foreach ($expected as $i => $row) {
            $this->assertSame($row['interest'], $rows[$i]['interest'], "installment {$rows[$i]['number']} interest");
            $this->assertSame($row['principal'], $rows[$i]['principal'], "installment {$rows[$i]['number']} principal");
            $this->assertSame($row['amount'], $rows[$i]['amount'], "installment {$rows[$i]['number']} amount");
        }

        // Interest falls every month.
        for ($i = 1; $i < count($rows); $i++) {
            $this->assertLessThan($rows[$i - 1]['interest'], $rows[$i]['interest']);
        }

        $this->assertSame(1_000_000.0, array_sum(array_column($rows, 'principal')));

        // Due dates: monthly from the release date (released 15 Jan -> 15 Feb ... 15 Jul).
        $this->assertSame('2026-02-15', $rows[0]['due_date']->toDateString());
        $this->assertSame('2026-07-15', $rows[5]['due_date']->toDateString());
    }

    public function test_due_dates_released_on_the_31st_clamp_to_short_months_without_drifting(): void
    {
        $rows = (new LoanCalculator)->installments(300000, 0.0, 3, LoanType::METHOD_FIXED, CarbonImmutable::parse('2026-01-31'));

        $this->assertSame('2026-02-28', $rows[0]['due_date']->toDateString());
        $this->assertSame('2026-03-31', $rows[1]['due_date']->toDateString());
        $this->assertSame('2026-04-30', $rows[2]['due_date']->toDateString());
    }

    /**
     * MASI Excel convention: installment rounded up to the next 1,000, interest to the nearest 100,
     * last installment = whatever is left. 3,500,000 at 3%/month over 4 months.
     */
    public function test_excel_rounding_matches_the_masi_sheet_and_never_overcharges(): void
    {
        $calculator = new LoanCalculator;

        $rows = $calculator->installments(3_500_000, 3.0, 4, LoanType::METHOD_MONTHLY_REDUCING, CarbonImmutable::parse('2026-11-15'), 1000, 100);

        $expected = [
            ['interest' => 105000.0, 'principal' => 837000.0, 'amount' => 942000.0],
            ['interest' => 79900.0, 'principal' => 862100.0, 'amount' => 942000.0],
            ['interest' => 54000.0, 'principal' => 888000.0, 'amount' => 942000.0],
            ['interest' => 27400.0, 'principal' => 912900.0, 'amount' => 940300.0], // Excel shows 942,000 here and overcharges 1,700
        ];

        foreach ($expected as $i => $row) {
            $this->assertSame($row['interest'], $rows[$i]['interest'], "installment {$rows[$i]['number']} interest");
            $this->assertSame($row['principal'], $rows[$i]['principal'], "installment {$rows[$i]['number']} principal");
            $this->assertSame($row['amount'], $rows[$i]['amount'], "installment {$rows[$i]['number']} amount");
        }

        $this->assertSame(3_500_000.0, array_sum(array_column($rows, 'principal')));
        $this->assertSame(266_300.0, array_sum(array_column($rows, 'interest')));
    }

    /**
     * Scenario (b): 1,000,000 at 10% over 5 months, fixed (once, on principal).
     */
    public function test_b_fixed_interest_schedule_splits_evenly(): void
    {
        $calculator = new LoanCalculator;

        $rows = $calculator->installments(1_000_000, 10.0, 5, LoanType::METHOD_FIXED, CarbonImmutable::parse('2026-01-15'));

        $this->assertCount(5, $rows);

        foreach ($rows as $row) {
            $this->assertSame(220000.0, $row['amount']);
        }

        $this->assertSame(100_000.0, array_sum(array_column($rows, 'interest')));
        $this->assertSame(1_000_000.0, array_sum(array_column($rows, 'principal')));
        $this->assertSame(100_000.0, $calculator->totalInterest(1_000_000, 10.0, 5, LoanType::METHOD_FIXED));
    }
}
