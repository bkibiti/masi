<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $paymentMethods = PaymentMethod::orderBy('name')->get();

        return view('masters.payment_methods.index', compact('paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        PaymentMethod::create($data);

        session()->flash('alert-success', 'Payment method added.');

        return back();
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $this->validated($request, $paymentMethod);

        $paymentMethod->update($data);

        session()->flash('alert-success', 'Payment method updated.');

        return back();
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->isInUse()) {
            session()->flash('alert-danger', 'This payment method has been used on a payment and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $paymentMethod->delete();

        session()->flash('alert-success', 'Payment method deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PaymentMethod $paymentMethod = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('payment_methods', 'name')->ignore($paymentMethod?->id)],
            'description' => 'nullable|string|max:255',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
