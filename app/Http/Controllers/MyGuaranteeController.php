<?php

namespace App\Http\Controllers;

use App\Models\LoanGuarantor;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Guarantee Requests" (spec section 3): loans the logged-in member has been asked to
 * guarantee, plus guarantees they've already given. Always scoped to their own member_id.
 */
class MyGuaranteeController extends Controller
{
    public function index(Request $request): View
    {
        $member = $request->user()->member;

        $guarantees = $member->guarantees()->with(['loan.member', 'loan.loanType'])->get();

        return view('account.guarantees.index', [
            'pending' => $guarantees->where('status', LoanGuarantor::STATUS_PENDING)->values(),
            'responded' => $guarantees->where('status', '!=', LoanGuarantor::STATUS_PENDING)->values(),
        ]);
    }

    public function accept(Request $request, LoanGuarantor $guarantor, LoanService $service): RedirectResponse
    {
        $this->authorizeOwn($request, $guarantor);

        $service->recordGuarantorResponse($guarantor, LoanGuarantor::STATUS_ACCEPTED, $request->user());

        session()->flash('alert-success', 'You have accepted this guarantee request.');

        return back();
    }

    public function decline(Request $request, LoanGuarantor $guarantor, LoanService $service): RedirectResponse
    {
        $this->authorizeOwn($request, $guarantor);

        $service->recordGuarantorResponse($guarantor, LoanGuarantor::STATUS_DECLINED, $request->user());

        session()->flash('alert-success', 'You have declined this guarantee request.');

        return back();
    }

    private function authorizeOwn(Request $request, LoanGuarantor $guarantor): void
    {
        abort_unless((int) $guarantor->guarantor_member_id === (int) $request->user()->member->id, 403);
    }
}
