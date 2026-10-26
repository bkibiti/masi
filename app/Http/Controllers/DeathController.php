<?php

namespace App\Http\Controllers;

use App\Models\Death;
use App\Models\Dependant;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeathController extends Controller
{
    /**
     * Death history. Default range: 1 January this year to today.
     */
    public function index(Request $request): View
    {
        $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'type' => 'nullable|in:all,member,dependant',
        ]);

        $today = CarbonImmutable::now('Africa/Dar_es_Salaam');
        $from = $request->input('from_date') ?: $today->startOfYear()->toDateString();
        $to = $request->input('to_date') ?: $today->toDateString();
        $type = $request->input('type', 'all');
        $showReversed = $request->boolean('show_reversed');
        $showUndated = $request->boolean('show_undated');

        $deaths = Death::query()
            ->with(['member', 'recorder', 'reverser'])
            ->where(function ($q) use ($from, $to, $showUndated) {
                $q->whereBetween('date_of_death', [$from, $to]);
                if ($showUndated) {
                    $q->orWhereNull('date_of_death');
                }
            })
            ->when($type === 'member', fn ($q) => $q->whereNull('dependant_id'))
            ->when($type === 'dependant', fn ($q) => $q->whereNotNull('dependant_id'))
            ->when(! $showReversed, fn ($q) => $q->whereNull('reversed_at'))
            ->latest('date_of_death')
            ->latest('id')
            ->get();

        $undatedCount = Death::active()->whereNull('date_of_death')->count();

        return view('deaths.index', compact('deaths', 'from', 'to', 'type', 'showReversed', 'showUndated', 'undatedCount'));
    }

    public function storeForMember(Request $request, Member $member): RedirectResponse
    {
        if ($member->isDeceased()) {
            session()->flash('alert-danger', 'A death is already recorded for this member.');

            return back();
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $member) {
            Death::create([
                ...$data,
                'member_id' => $member->id,
                'dependant_id' => null,
                'deceased_name' => $member->name,
                'relationship' => 'Member',
                'recorded_by' => auth()->id(),
            ]);

            $member->update(['status' => 'Deceased', 'updated_by' => auth()->id()]);
        });

        session()->flash('alert-success', 'Death of '.$member->name.' recorded.');

        return back();
    }

    public function storeForDependant(Request $request, Dependant $dependant): RedirectResponse
    {
        if ($dependant->isDeceased()) {
            session()->flash('alert-danger', 'A death is already recorded for this dependant.');

            return back();
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $dependant) {
            Death::create([
                ...$data,
                'member_id' => $dependant->member_id,
                'dependant_id' => $dependant->id,
                'deceased_name' => $dependant->name,
                'relationship' => $dependant->relationshipName(),
                'recorded_by' => auth()->id(),
            ]);

            $dependant->update(['status' => 'Deceased', 'updated_by' => auth()->id()]);
        });

        session()->flash('alert-success', 'Death of '.$dependant->name.' recorded.');

        return back();
    }

    /**
     * Undo a death recorded by mistake. The record is kept (marked reversed) for history,
     * and the person is set back to Active / Alive.
     */
    public function reverse(Request $request, Death $death): RedirectResponse
    {
        $request->validate(['reversal_reason' => 'required|string|max:255']);

        if ($death->isReversed()) {
            session()->flash('alert-danger', 'This death record is already reversed.');

            return back();
        }

        DB::transaction(function () use ($request, $death) {
            $death->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $request->input('reversal_reason'),
            ]);

            if ($death->isMemberDeath()) {
                $death->member->update(['status' => 'Active', 'updated_by' => auth()->id()]);
            } else {
                $death->dependant->update(['status' => 'Alive', 'updated_by' => auth()->id()]);
            }
        });

        session()->flash('alert-success', 'Death record reversed. It stays in the history as reversed.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'date_of_death' => 'required|date|before_or_equal:today',
            'place_of_death' => 'nullable|string|max:150',
            'burial_date' => 'nullable|date|after_or_equal:date_of_death',
            'burial_place' => 'nullable|string|max:150',
            'reported_by' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ], [
            'burial_date.after_or_equal' => 'The burial date cannot be before the date of death.',
        ]);
    }
}
