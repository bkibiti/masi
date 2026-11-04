<?php

namespace App\Services;

use App\Models\ContributionSetting;
use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberPenalty;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only aggregates for the admin dashboard. Every method returns a plain array (or, for
 * recent payments, a Collection) built from SUM/GROUP BY queries - nothing here loads every
 * row into PHP, and nothing here writes to the ledger.
 */
class DashboardService
{
    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /**
     * Years that have at least one due or payment, plus the current year.
     *
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        // No raw YEAR()/strftime() here: it needs to run the same on MySQL (production) and
        // SQLite (tests), so the year is pulled out in PHP from a deduped list of dates.
        $dueYears = MemberDue::query()->select('period')->distinct()->pluck('period')
            ->map(fn ($d) => CarbonImmutable::parse($d)->year);
        $paymentYears = Payment::query()->select('paid_on')->distinct()->pluck('paid_on')
            ->map(fn ($d) => CarbonImmutable::parse($d)->year);

        return $dueYears->merge($paymentYears)
            ->push($this->today()->year)
            ->map(fn ($y) => (int) $y)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Card (a): total received this year, across all payment types.
     *
     * @return array<string, mixed>
     */
    public function totalsCard(int $year): array
    {
        return [
            'total' => (float) Payment::query()->whereYear('paid_on', $year)->sum('amount'),
        ];
    }

    /**
     * Card (b): monthly fees collected this year, and the closed-month collection rate.
     * "Closed" months are those whose due_date has already passed.
     *
     * @return array<string, mixed>
     */
    public function monthlyFeesCard(int $year): array
    {
        $collected = (float) MemberDue::query()->whereYear('period', $year)->sum('amount_paid');

        $closed = MemberDue::query()
            ->whereYear('period', $year)
            ->where('due_date', '<', $this->today()->toDateString())
            ->selectRaw('SUM(amount) as expected, SUM(amount_paid) as paid, MIN(period) as first_period, MAX(period) as last_period')
            ->first();

        $expected = (float) ($closed->expected ?? 0);
        $paid = (float) ($closed->paid ?? 0);
        $rate = $expected > 0 ? (int) round($paid / $expected * 100) : null;

        $rangeLabel = null;
        if ($closed->first_period && $closed->last_period) {
            $first = CarbonImmutable::parse($closed->first_period);
            $last = CarbonImmutable::parse($closed->last_period);
            $rangeLabel = $first->format('M').'–'.$last->format('M');
        }

        return [
            'collected' => $collected,
            'rate' => $rate,
            'range_label' => $rangeLabel,
        ];
    }

    /**
     * Card (c): outstanding arrears as of today, across all years - not filtered by the
     * year selector.
     *
     * @return array<string, mixed>
     */
    public function arrearsCard(): array
    {
        $today = $this->today()->toDateString();

        $fees = (float) MemberDue::query()
            ->where('due_date', '<', $today)
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
            ->value('total');

        $penalties = (float) MemberPenalty::query()
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
            ->value('total');

        return [
            'fees' => $fees,
            'penalties' => $penalties,
            'total' => $fees + $penalties,
        ];
    }

    /**
     * Panel (3a): monthly fees collected vs expected, one entry per month of the given year.
     * "Collected" is money allocated to that month's due (amount_paid), not money received
     * in that calendar month.
     *
     * @return array<string, mixed>
     */
    public function monthlyChart(int $year): array
    {
        // Grouping by the period column itself (not a raw MONTH() extraction) keeps this
        // portable between MySQL (production) and SQLite (tests): every due's period is
        // already the 1st of its month, so one row per period is one row per month.
        $rows = MemberDue::query()
            ->whereYear('period', $year)
            ->selectRaw('period, SUM(amount) as expected, SUM(amount_paid) as collected')
            ->groupBy('period')
            ->get()
            ->keyBy(fn ($row) => CarbonImmutable::parse($row->period)->month);

        $today = $this->today();
        $currentMonth = $today->year === $year ? $today->month : null;

        $months = [];
        $expectedValues = [];

        for ($m = 1; $m <= 12; $m++) {
            $row = $rows->get($m);
            $hasDues = $row !== null;
            $expected = $hasDues ? (float) $row->expected : 0.0;
            $collected = $hasDues ? (float) $row->collected : 0.0;

            if ($hasDues) {
                $expectedValues[] = $expected;
            }

            $months[] = [
                'month' => $m,
                'label' => CarbonImmutable::create($year, $m, 1)->format('M'),
                'has_dues' => $hasDues,
                'expected' => $expected,
                'collected' => $collected,
                'percent' => $expected > 0 ? (int) min(100, round($collected / $expected * 100)) : 0,
                'is_current' => $m === $currentMonth,
            ];
        }

        $uniqueExpected = array_unique($expectedValues);
        $sameExpectedEveryMonth = count($uniqueExpected) === 1 ? (float) array_values($uniqueExpected)[0] : null;

        $footnote = null;
        if ($currentMonth) {
            $dueDate = $today->startOfMonth()->endOfMonth();
            $footnote = $today->format('F').' is due on '.$dueDate->format('j F Y').'.';
        }

        return [
            'months' => $months,
            'same_expected_every_month' => $sameExpectedEveryMonth,
            'footnote' => $footnote,
        ];
    }

    /**
     * Panel (3b): the current calendar month's collection status - always "now", regardless
     * of the Year selector elsewhere on the page.
     *
     * @return array<string, mixed>
     */
    public function currentMonthPanel(): array
    {
        $today = $this->today();
        $monthStart = $today->startOfMonth();
        $dueDate = $monthStart->endOfMonth();

        $setting = ContributionSetting::query()
            ->where('start_date', '<=', $monthStart->toDateString())
            ->where('end_date', '>=', $dueDate->toDateString())
            ->first();

        $stats = MemberDue::query()
            ->whereDate('period', $monthStart->toDateString())
            ->selectRaw('COUNT(*) as total_members, SUM(CASE WHEN amount_paid >= amount THEN 1 ELSE 0 END) as paid_members, SUM(amount) as expected, SUM(amount_paid) as collected')
            ->first();

        $totalMembers = (int) ($stats->total_members ?? 0);
        $paidMembers = (int) ($stats->paid_members ?? 0);
        $unpaidMembers = max(0, $totalMembers - $paidMembers);
        $daysUntilDue = (int) $today->diffInDays($dueDate);

        return [
            'month_label' => $monthStart->format('F Y'),
            'due_badge' => match (true) {
                $daysUntilDue === 0 => 'Due today',
                $daysUntilDue === 1 => 'Due tomorrow',
                default => 'Due in '.$daysUntilDue.' days',
            },
            'due_date' => $dueDate,
            'total_members' => $totalMembers,
            'paid_members' => $paidMembers,
            'unpaid_members' => $unpaidMembers,
            'percent' => $totalMembers > 0 ? (int) round($paidMembers / $totalMembers * 100) : 0,
            'expected' => (float) ($stats->expected ?? 0),
            'collected' => (float) ($stats->collected ?? 0),
            'setting' => $setting,
            'penalty_amount' => $setting?->penaltyForFullMonth(),
            'next_month_label' => $monthStart->addMonth()->format('F'),
        ];
    }

    /**
     * Section 4: the most recent payments across all members, newest first.
     *
     * @return Collection<int, Payment>
     */
    public function recentPayments(int $limit = 10): Collection
    {
        return Payment::query()
            ->with(['member', 'paymentType', 'paymentMethod'])
            ->latest('paid_on')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The "My summary" row shown above the group dashboard to a user linked to a member
     * (spec section 4): their own arrears, current-month status, active loan balance and
     * next installment, and pending guarantee requests.
     *
     * @return array<string, mixed>
     */
    public function mySummary(Member $member): array
    {
        $today = $this->today();
        $currentPeriod = $today->startOfMonth()->toDateString();

        $dueIds = $member->dues()->pluck('id');

        $feeArrears = (float) MemberDue::whereIn('id', $dueIds)
            ->where('due_date', '<', $today->toDateString())
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
            ->value('total');

        $penaltyArrears = (float) MemberPenalty::whereIn('member_due_id', $dueIds)
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
            ->value('total');

        $currentDue = $member->dues()->whereDate('period', $currentPeriod)->first();
        $currentMonthStatus = match (true) {
            $currentDue === null => 'No due this month',
            $currentDue->balance() <= 0 => 'Paid',
            default => 'Unpaid',
        };

        $activeLoan = $member->activeLoan();
        $nextInstallment = $activeLoan?->installments->first(fn ($i) => ! $i->isFullyPaid());

        return [
            'arrears' => $feeArrears + $penaltyArrears,
            'current_month_status' => $currentMonthStatus,
            'active_loan' => $activeLoan,
            'active_loan_balance' => $activeLoan?->outstandingBalance(),
            'next_installment' => $nextInstallment,
            'pending_guarantees' => $member->guarantees()->where('status', LoanGuarantor::STATUS_PENDING)->count(),
        ];
    }
}
