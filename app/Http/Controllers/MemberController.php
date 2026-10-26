<?php

namespace App\Http\Controllers;

use App\Models\DependantCategory;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), Member::STATUSES, true) ? $request->query('status') : null;

        $members = Member::query()
            ->with('phones')
            ->withCount(['dependants', 'dependants as living_dependants_count' => fn ($q) => $q->where('status', 'Alive')])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->get();

        return view('members.index', compact('members', 'status'));
    }

    public function show(Member $member): View
    {
        $member->load([
            'phones',
            'dependants.death',
            'dependants.category',
            'ownDeath',
            'deaths.recorder',
            'deaths.reverser',
        ]);

        $categories = DependantCategory::ordered()->get();

        return view('members.show', compact('member', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $member = DB::transaction(function () use ($data) {
            $member = Member::create([
                ...collect($data)->except('phones')->all(),
                'status' => 'Active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncPhones($member, $data['phones'] ?? []);

            return $member;
        });

        session()->flash('alert-success', 'Member added successfully!');

        return redirect()->route('members.show', $member);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $data = $this->validated($request, $member);

        DB::transaction(function () use ($data, $member) {
            $attributes = collect($data)->except('phones')->all();

            // A deceased member keeps that status; it only changes by reversing the death record.
            if ($member->isDeceased()) {
                unset($attributes['status']);
            }

            $member->update([...$attributes, 'updated_by' => auth()->id()]);
            $this->syncPhones($member, $data['phones'] ?? []);
        });

        session()->flash('alert-success', 'Member updated successfully!');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Member $member = null): array
    {
        // Drop empty phone boxes before validating, so "up to 3" means up to 3 filled in.
        $request->merge([
            'phones' => array_values(array_filter(array_map('trim', (array) $request->input('phones', [])))),
        ]);

        return $request->validate([
            'member_no' => ['nullable', 'string', 'max:20', Rule::unique('members', 'member_no')->ignore($member?->id)],
            'name' => 'required|string|max:150',
            'gender' => ['required', Rule::in(Member::GENDERS)],
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'location' => 'nullable|string|max:100',
            'joined_on' => 'nullable|date|before_or_equal:today',
            'status' => [$member && ! $member->isDeceased() ? 'required' : 'nullable', Rule::in(['Active', 'Inactive'])],
            'notes' => 'nullable|string|max:1000',
            'phones' => 'nullable|array|max:'.Member::MAX_PHONES,
            'phones.*' => ['distinct', 'regex:/^255[0-9]{9}$/'],
        ], [
            'phones.max' => 'A member can have at most '.Member::MAX_PHONES.' mobile numbers.',
            'phones.*.regex' => 'Mobile must be 12 digits starting with 255, e.g. 255745777602.',
            'phones.*.distinct' => 'The same mobile number is entered twice.',
        ]);
    }

    /**
     * Replace the member's numbers with the submitted ones; the first is the primary number.
     *
     * @param  array<int, string>  $phones
     */
    private function syncPhones(Member $member, array $phones): void
    {
        $member->phones()->delete();

        foreach (array_values($phones) as $index => $phone) {
            $member->phones()->create([
                'phone' => $phone,
                'is_primary' => $index === 0,
            ]);
        }
    }
}
