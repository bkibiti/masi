<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentTypeController extends Controller
{
    public function index(): View
    {
        $paymentTypes = PaymentType::orderBy('name')->get();

        return view('masters.payment_types.index', compact('paymentTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        PaymentType::create($data);

        session()->flash('alert-success', 'Payment type added.');

        return back();
    }

    public function update(Request $request, PaymentType $paymentType): RedirectResponse
    {
        $data = $this->validated($request, $paymentType);

        $paymentType->update($data);

        session()->flash('alert-success', 'Payment type updated.');

        return back();
    }

    public function destroy(PaymentType $paymentType): RedirectResponse
    {
        if ($paymentType->is_system) {
            session()->flash('alert-danger', 'This is a system payment type and cannot be deleted. Deactivate it instead.');

            return back();
        }

        if ($paymentType->isInUse()) {
            session()->flash('alert-danger', 'This payment type has been used on a payment and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $paymentType->delete();

        session()->flash('alert-success', 'Payment type deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PaymentType $paymentType = null): array
    {
        $isSystem = $paymentType?->is_system ?? false;

        $rules = [
            'name' => ['required', 'string', 'max:100', Rule::unique('payment_types', 'name')->ignore($paymentType?->id)],
            'description' => 'nullable|string|max:255',
        ];

        // A system type's ledger_role is fixed; only its name/description/active state can change.
        if (! $isSystem) {
            $rules['ledger_role'] = ['required', Rule::in(PaymentType::LEDGER_ROLES)];
        }

        $data = $request->validate($rules);

        $data['is_active'] = $request->boolean('is_active');
        $data['ledger_role'] = $isSystem ? $paymentType->ledger_role : $data['ledger_role'];

        $this->guardSingleActiveRoles($data, $paymentType);

        return $data;
    }

    /**
     * Exactly one active type may hold ledger_role=monthly_fee (the ledger always needs one),
     * and at most one active type may hold ledger_role=penalty.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardSingleActiveRoles(array $data, ?PaymentType $paymentType): void
    {
        if (! $data['is_active']) {
            if ($paymentType && $paymentType->ledger_role === PaymentType::ROLE_MONTHLY_FEE) {
                $anotherIsActive = PaymentType::where('ledger_role', PaymentType::ROLE_MONTHLY_FEE)
                    ->where('is_active', true)
                    ->where('id', '!=', $paymentType->id)
                    ->exists();

                if (! $anotherIsActive) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Exactly one active "Monthly Fee" type is required. Activate another one before deactivating this one.',
                    ]);
                }
            }

            return;
        }

        if (! in_array($data['ledger_role'], [PaymentType::ROLE_MONTHLY_FEE, PaymentType::ROLE_PENALTY], true)) {
            return;
        }

        $conflict = PaymentType::where('ledger_role', $data['ledger_role'])
            ->where('is_active', true)
            ->when($paymentType, fn ($q) => $q->where('id', '!=', $paymentType->id))
            ->first();

        if ($conflict) {
            $roleLabel = $data['ledger_role'] === PaymentType::ROLE_MONTHLY_FEE ? 'Monthly Fee' : 'Penalty';

            throw ValidationException::withMessages([
                'ledger_role' => 'Another active type ("'.$conflict->name.'") already has the '.$roleLabel.' role.',
            ]);
        }
    }
}
