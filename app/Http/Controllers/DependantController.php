<?php

namespace App\Http\Controllers;

use App\Models\Death;
use App\Models\Dependant;
use App\Models\DependantCategory;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DependantController extends Controller
{
    public function store(Request $request, Member $member): RedirectResponse
    {
        $data = $this->validated($request, $member);

        $member->dependants()->create([
            ...$data,
            'status' => 'Alive',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        session()->flash('alert-success', 'Dependant added successfully!');

        return back();
    }

    public function update(Request $request, Dependant $dependant): RedirectResponse
    {
        $data = $this->validated($request, $dependant->member);

        $dependant->update([...$data, 'updated_by' => auth()->id()]);

        session()->flash('alert-success', 'Dependant updated successfully!');

        return back();
    }

    /**
     * A dependant can only be removed if no death was ever recorded for them,
     * so the death history is never lost.
     */
    public function destroy(Dependant $dependant): RedirectResponse
    {
        if ($dependant->isDeceased() || Death::where('dependant_id', $dependant->id)->exists()) {
            session()->flash('alert-danger', 'This dependant has a death record and cannot be removed.');

            return back();
        }

        $dependant->delete();
        session()->flash('alert-success', 'Dependant removed.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Member $member): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'dependant_category_id' => 'required|integer|exists:dependant_categories,id',
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'phone' => ['nullable', 'regex:/^255[0-9]{9}$/'],
            'notes' => 'nullable|string|max:255',
        ], [
            'dependant_category_id.required' => 'Select the category.',
            'phone.regex' => 'Mobile must be 12 digits starting with 255, e.g. 255745777602.',
        ]);

        // Category rules, e.g. Mume: always Male and only for female members.
        $category = DependantCategory::findOrFail($data['dependant_category_id']);

        if (! $category->allowsMember($member)) {
            throw ValidationException::withMessages([
                'dependant_category_id' => $category->name.' can only be added to a '.strtolower($category->member_gender).' member.',
            ]);
        }

        if ($category->gender) {
            $data['gender'] = $category->gender;
        }

        return $data;
    }
}
