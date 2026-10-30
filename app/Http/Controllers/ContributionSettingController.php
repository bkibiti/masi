<?php

namespace App\Http\Controllers;

use App\Models\ContributionSetting;
use App\Services\ContributionLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContributionSettingController extends Controller
{
    public function index(): View
    {
        $settings = ContributionSetting::orderByDesc('start_date')->get();

        $active = $settings->first(fn (ContributionSetting $s) => $s->status() === 'Active');

        return view('masters.contribution_settings.index', compact('settings', 'active'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ContributionSetting::create($data);

        app(ContributionLedger::class)->rebuildAll();

        session()->flash('alert-success', 'Contribution setting added.');

        return back();
    }

    public function update(Request $request, ContributionSetting $contributionSetting): RedirectResponse
    {
        $data = $this->validated($request, $contributionSetting);

        $contributionSetting->update($data);

        app(ContributionLedger::class)->rebuildAll();

        session()->flash('alert-success', 'Contribution setting updated.');

        return back();
    }

    public function destroy(ContributionSetting $contributionSetting): RedirectResponse
    {
        if ($contributionSetting->hasDues()) {
            session()->flash('alert-danger', 'This setting already has dues and cannot be deleted.');

            return back();
        }

        $contributionSetting->delete();

        session()->flash('alert-success', 'Contribution setting deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ContributionSetting $setting = null): array
    {
        $locked = $setting?->isLocked() ?? false;

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'monthly_amount' => 'required|numeric|min:0.01',
            'penalty_percent' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        if (! $start->isSameDay($start->startOfMonth())) {
            throw ValidationException::withMessages(['start_date' => 'The start date must be the 1st of a month.']);
        }

        if (! $end->isSameDay($end->endOfMonth())) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be the last day of a month.']);
        }

        if ($locked) {
            // Dates, amount and penalty % are locked once any payment has been allocated under this setting.
            $data['start_date'] = $setting->start_date->toDateString();
            $data['end_date'] = $setting->end_date->toDateString();
            $data['monthly_amount'] = $setting->monthly_amount;
            $data['penalty_percent'] = $setting->penalty_percent;
        } else {
            $overlap = ContributionSetting::query()
                ->when($setting, fn ($q) => $q->where('id', '!=', $setting->id))
                ->where('start_date', '<=', $data['end_date'])
                ->where('end_date', '>=', $data['start_date'])
                ->first();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_date' => 'This period overlaps "'.$overlap->name.'" ('.$overlap->start_date->format('j M Y').' - '.$overlap->end_date->format('j M Y').').',
                ]);
            }
        }

        return $data;
    }
}
