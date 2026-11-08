<?php

namespace App\Http\Controllers;

use App\Models\LoanType;
use App\Services\LoanCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanTypeController extends Controller
{
    public function index(): View
    {
        $loanTypes = LoanType::orderBy('name')->get();

        return view('masters.loan_types.index', compact('loanTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        LoanType::create($data);

        session()->flash('alert-success', 'Loan type added.');

        return back();
    }

    public function update(Request $request, LoanType $loanType): RedirectResponse
    {
        $data = $this->validated($request, $loanType);

        $loanType->update($data);

        session()->flash('alert-success', 'Loan type updated.');

        return back();
    }

    public function destroy(LoanType $loanType): RedirectResponse
    {
        if ($loanType->isInUse()) {
            session()->flash('alert-danger', 'This loan type has been used on a loan and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $loanType->delete();

        session()->flash('alert-success', 'Loan type deleted.');

        return back();
    }

    /**
     * Example calculation preview for the create/edit form (e.g. 1,000,000 over the max duration).
     */
    public function preview(Request $request, LoanCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'interest_method' => ['required', Rule::in(LoanType::INTEREST_METHODS)],
            'installment_rounding' => ['nullable', Rule::in(array_keys(LoanType::INSTALLMENT_ROUNDING_OPTIONS))],
            'interest_rounding' => ['nullable', Rule::in(array_keys(LoanType::INTEREST_ROUNDING_OPTIONS))],
        ]);

        $rows = $calculator->installments(
            (float) $data['amount'],
            (float) $data['interest_rate'],
            (int) $data['duration_months'],
            $data['interest_method'],
            CarbonImmutable::now(),
            (int) ($data['installment_rounding'] ?? 1),
            (int) ($data['interest_rounding'] ?? 1),
        );

        return response()->json([
            'installments' => array_map(fn ($row) => [
                'number' => $row['number'],
                'due_date' => $row['due_date']->toDateString(),
                'principal' => (float) $row['principal'],
                'interest' => (float) $row['interest'],
                'amount' => (float) $row['amount'],
            ], $rows),
            'total_interest' => array_sum(array_column($rows, 'interest')),
            'total_payable' => array_sum(array_column($rows, 'amount')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LoanType $loanType = null): array
    {
        $locked = $loanType?->isInUse() ?? false;

        // Changing a loan type never changes existing loans, so its terms are locked once it
        // has been used (the form disables and never submits them); only name/description/
        // active state can still change.
        $termRules = $locked ? ['sometimes'] : ['required'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('loan_types', 'name')->ignore($loanType?->id)],
            'description' => 'nullable|string|max:255',
            'interest_method' => [...$termRules, Rule::in(LoanType::INTEREST_METHODS)],
            'interest_rate' => [...$termRules, 'numeric', 'min:0', 'max:100'],
            // Amount and duration limits are only checked when a loan is applied for or approved,
            // so they stay editable even when the type is in use.
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'min_duration_months' => ['required', 'integer', 'min:1'],
            'max_duration_months' => ['required', 'integer', 'gte:min_duration_months'],
            'penalty_percent' => [...$termRules, 'numeric', 'min:0', 'max:100'],
            // Rounding stays editable even when the type is in use: it is copied onto each loan
            // at application, so changing it only affects loans applied for afterwards.
            'installment_rounding' => ['required', Rule::in(array_keys(LoanType::INSTALLMENT_ROUNDING_OPTIONS))],
            'interest_rounding' => ['required', Rule::in(array_keys(LoanType::INTEREST_ROUNDING_OPTIONS))],
        ]);

        $data['installment_rounding'] = (int) $data['installment_rounding'];
        $data['interest_rounding'] = (int) $data['interest_rounding'];

        $data['is_active'] = $request->boolean('is_active');

        if ($locked) {
            $data['interest_method'] = $loanType->interest_method;
            $data['interest_rate'] = $loanType->interest_rate;
            $data['penalty_percent'] = $loanType->penalty_percent;
        }

        return $data;
    }
}
