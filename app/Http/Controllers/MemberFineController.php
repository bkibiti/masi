<?php

namespace App\Http\Controllers;

use App\Models\FineType;
use App\Models\Member;
use App\Models\MemberFine;
use App\Services\ContributionLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberFineController extends Controller
{
    public function store(Request $request, ContributionLedger $ledger): RedirectResponse
    {
        $data = $this->validated($request);
        $member = Member::findOrFail($data['member_id']);

        // The amount always comes from the fine type, never from the submitted form field.
        $fineType = FineType::findOrFail($data['fine_type_id']);
        if ((float) $fineType->default_amount <= 0) {
            throw ValidationException::withMessages([
                'fine_type_id' => 'This fine type has no default amount set. Set one in Masters > Fine Types before using it.',
            ]);
        }
        $data['amount'] = $fineType->default_amount;

        DB::transaction(function () use ($data, $member, $ledger) {
            MemberFine::create([
                ...$data,
                'created_by' => auth()->id(),
            ]);

            $ledger->rebuildForMember($member);
        });

        session()->flash('alert-success', 'Fine added.');

        return back();
    }

    public function update(Request $request, MemberFine $memberFine, ContributionLedger $ledger): RedirectResponse
    {
        $data = $this->validated($request, $memberFine);

        DB::transaction(function () use ($data, $memberFine, $ledger) {
            $memberFine->update($data);
            $ledger->rebuildForMember($memberFine->member);
        });

        session()->flash('alert-success', 'Fine updated.');

        return back();
    }

    public function destroy(MemberFine $memberFine, ContributionLedger $ledger): RedirectResponse
    {
        DB::transaction(function () use ($memberFine, $ledger) {
            $member = $memberFine->member;
            $memberFine->delete();
            $ledger->rebuildForMember($member);
        });

        session()->flash('alert-success', 'Fine deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?MemberFine $memberFine = null): array
    {
        $rules = [
            'fine_type_id' => 'required|exists:fine_types,id',
            'fined_on' => 'required|date|before_or_equal:today',
            'reason' => 'nullable|string|max:500',
        ];

        if ($memberFine) {
            // Editing lets the amount be corrected directly; adding a fine always takes the fine
            // type's current default (see store()), so the submitted amount is never validated here.
            $rules['amount'] = 'required|numeric|min:0.01';
        } else {
            $rules['member_id'] = 'required|exists:members,id';
        }

        return $request->validate($rules);
    }
}
