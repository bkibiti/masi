<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ConfigurationsController extends Controller
{
    /**
     * The only settings Wadau uses.
     */
    public const KEYS = ['group_name', 'registration_number', 'slogan', 'logo'];

    public function index(): View
    {
        $settings = Setting::whereIn('name', self::KEYS)->pluck('value', 'name');

        return view('configurations.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_name' => 'required|string|max:150',
            'registration_number' => 'nullable|string|max:100',
            'slogan' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);

        foreach (['group_name', 'registration_number', 'slogan'] as $key) {
            $this->save($key, $data[$key] ?? null);
        }

        $currentLogo = Setting::where('name', 'logo')->value('value');

        if ($request->hasFile('logo')) {
            $this->deleteLogo($currentLogo);
            $file = $request->file('logo');
            $name = 'logo-'.time().'.'.$file->extension();
            $file->move(public_path('logo'), $name);
            $this->save('logo', $name);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($currentLogo);
            $this->save('logo', null);
        }

        session()->flash('alert-success', 'Settings saved successfully!');

        return back();
    }

    private function save(string $key, ?string $value): void
    {
        Setting::where('name', $key)->update([
            'value' => $value,
            'updated_by' => auth()->id(),
        ]);
    }

    private function deleteLogo(?string $file): void
    {
        if ($file) {
            File::delete(public_path('logo/'.basename($file)));
        }
    }
}
