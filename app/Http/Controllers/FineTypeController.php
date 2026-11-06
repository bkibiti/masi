<?php

namespace App\Http\Controllers;

use App\Models\FineType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FineTypeController extends Controller
{
    public function index(): View
    {
        $fineTypes = FineType::orderBy('name')->get();

        return view('masters.fine_types.index', compact('fineTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        FineType::create($data);

        session()->flash('alert-success', 'Fine type added.');

        return back();
    }

    public function update(Request $request, FineType $fineType): RedirectResponse
    {
        $data = $this->validated($request, $fineType);

        $fineType->update($data);

        session()->flash('alert-success', 'Fine type updated.');

        return back();
    }

    public function destroy(FineType $fineType): RedirectResponse
    {
        if ($fineType->isInUse()) {
            session()->flash('alert-danger', 'This fine type has been used on a fine and cannot be deleted. Deactivate it instead.');

            return back();
        }

        $fineType->delete();

        session()->flash('alert-success', 'Fine type deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?FineType $fineType = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('fine_types', 'name')->ignore($fineType?->id)],
            'default_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
