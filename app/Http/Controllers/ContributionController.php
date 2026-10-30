<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\ContributionLedger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContributionController extends Controller
{
    public function register(Request $request, ContributionLedger $ledger): View
    {
        $year = (int) $request->query('year', now()->year);
        $search = trim((string) $request->query('search', ''));

        $members = Member::query()
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->get();

        $rows = $members->map(function (Member $member) use ($ledger, $year) {
            $statement = $ledger->statementForMember($member, $year);

            return [
                'member' => $member,
                'months' => $statement['months'],
                'paid_this_year' => $statement['year_totals']['paid'],
                'penalties_this_year' => $statement['year_totals']['penalties'],
                'arrears' => $statement['arrears'],
                'credit' => $statement['credit'],
            ];
        });

        $summary = [
            'collected' => $rows->sum('paid_this_year'),
            'penalties' => $rows->sum('penalties_this_year'),
            'arrears' => $rows->sum('arrears'),
            'in_arrears' => $rows->filter(fn ($r) => $r['arrears'] > 0)->count(),
        ];

        $years = $this->availableYears();

        return view('contributions.register', compact('rows', 'year', 'years', 'search', 'summary'));
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $thisYear = (int) now()->year;

        return range($thisYear - 2, $thisYear + 1);
    }
}
