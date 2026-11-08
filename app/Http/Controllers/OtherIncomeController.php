<?php

namespace App\Http\Controllers;

use App\Models\OtherIncome;
use App\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtherIncomeController extends Controller
{
    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    public function index(Request $request): View
    {
        $year = $request->query('year', 'all');

        $incomes = OtherIncome::query()
            ->with(['paymentMethod', 'recorder'])
            ->when($year !== 'all', fn ($q) => $q->whereYear('received_on', (int) $year))
            ->orderByDesc('received_on')->orderByDesc('id')
            ->get();

        $years = OtherIncome::query()->selectRaw('DISTINCT YEAR(received_on) as y')->orderByDesc('y')->pluck('y');
        $methods = PaymentMethod::query()->orderBy('name')->get();

        return view('other_income.index', [
            'incomes' => $incomes,
            'total' => (float) $incomes->sum('amount'),
            'year' => $year,
            'years' => $years,
            'methods' => $methods,
            'today' => CarbonImmutable::now(self::TIMEZONE)->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['recorded_by'] = $request->user()->id;

        OtherIncome::create($data);

        session()->flash('alert-success', 'Other income recorded.');

        return redirect()->route('other-income.index');
    }

    public function update(Request $request, OtherIncome $otherIncome): RedirectResponse
    {
        $otherIncome->update($this->validated($request));

        session()->flash('alert-success', 'Other income updated.');

        return redirect()->route('other-income.index');
    }

    public function destroy(OtherIncome $otherIncome): RedirectResponse
    {
        $otherIncome->delete();

        session()->flash('alert-success', 'Other income deleted.');

        return redirect()->route('other-income.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();

        return $request->validate([
            'received_on' => 'required|date_format:Y-m-d|before_or_equal:'.$today,
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'reference' => 'nullable|string|max:100',
        ], [
            'received_on.before_or_equal' => 'The date cannot be in the future.',
        ]);
    }
}
