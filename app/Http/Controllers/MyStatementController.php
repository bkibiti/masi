<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberDue;
use App\Models\MemberPenalty;
use App\Services\ContributionLedger;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * "My Statement" (spec section 3): the logged-in member's own contribution statement.
 * Always scoped to auth()->user()->member, never a route parameter.
 */
class MyStatementController extends Controller
{
    public function index(Request $request, ContributionLedger $ledger): View
    {
        $member = $request->user()->member;
        $year = (int) $request->query('year', now()->year);

        return view('account.statement', [
            ...$this->data($member, $year, $ledger),
            'years' => range(now()->year - 2, now()->year + 1),
        ]);
    }

    public function pdf(Request $request, ContributionLedger $ledger): Response
    {
        $member = $request->user()->member;
        $year = (int) $request->query('year', now()->year);

        return PDF::loadView('account.statement_pdf', $this->data($member, $year, $ledger))
            ->download("statement-{$member->id}-{$year}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Member $member, int $year, ContributionLedger $ledger): array
    {
        $statement = $ledger->statementForMember($member, $year);

        $penaltyTotals = MemberPenalty::whereIn('member_due_id', $member->dues()->pluck('id'))
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as paid, COALESCE(SUM(amount - amount_paid), 0) as owed')
            ->first();

        $nextDue = MemberDue::where('member_id', $member->id)
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date')
            ->first();

        $payments = $member->payments()->with(['paymentType', 'paymentMethod'])->latest('paid_on')->latest('id')->get();

        return [
            'member' => $member,
            'year' => $year,
            'statement' => $statement,
            'penalties_paid' => (float) $penaltyTotals->paid,
            'penalties_owed' => (float) $penaltyTotals->owed,
            'next_due' => $nextDue,
            'payments' => $payments,
            'feeChargeRecords' => $ledger->feeCharges($member),
        ];
    }
}
