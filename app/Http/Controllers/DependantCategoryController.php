<?php

namespace App\Http\Controllers;

use App\Models\DependantCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DependantCategoryController extends Controller
{
    public function index(): View
    {
        $categories = DependantCategory::ordered()
            ->withCount([
                'dependants',
                'dependants as living_count' => fn ($q) => $q->where('status', 'Alive'),
            ])
            ->get();

        return view('masters.dependant_categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        DependantCategory::create($this->validated($request));
        session()->flash('alert-success', 'Category added.');

        return back();
    }

    /**
     * Rename / change the rules. Existing dependants follow the category automatically.
     */
    public function update(Request $request, DependantCategory $dependantCategory): RedirectResponse
    {
        $dependantCategory->update($this->validated($request, $dependantCategory));
        session()->flash('alert-success', 'Category updated.');

        return back();
    }

    public function destroy(DependantCategory $dependantCategory): RedirectResponse
    {
        if ($dependantCategory->dependants()->exists()) {
            session()->flash('alert-danger', 'This category has dependants and cannot be deleted. Rename it instead.');

            return back();
        }

        $dependantCategory->delete();
        session()->flash('alert-success', 'Category deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?DependantCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('dependant_categories', 'name')->ignore($category?->id)],
            'group_name' => 'nullable|string|max:50',
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'member_gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'sort_order' => 'nullable|integer|min:0|max:999',
        ], [
            'name.unique' => 'A category with this name already exists.',
        ]);

        $data['group_name'] = $data['group_name'] ?: $data['name'];
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
