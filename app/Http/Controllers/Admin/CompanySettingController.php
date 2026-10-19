<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyOperationalTime;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        $company = CompanySetting::current();

        return view('admin.company.edit', compact('company'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'brand_color' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'operational_times' => ['required', 'array', 'min:1'],
            'operational_times.*.id' => ['nullable', 'integer'],
            'operational_times.*.label' => ['nullable', 'string', 'max:100'],
            'operational_times.*.start_time' => ['required', 'date_format:H:i'],
            'operational_times.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        unset($validated['logo'], $validated['remove_logo']);

        $times = collect($request->input('operational_times', []))
            ->reject(function ($item) {
                return ($item['start_time'] ?? '') === ''
                    && ($item['end_time'] ?? '') === ''
                    && trim((string) ($item['label'] ?? '')) === '';
            })
            ->values();

        $company = CompanySetting::current();
        $company->update($validated);

        if ($request->hasFile('logo')) {
            if (filled($company->logo_path)) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $company->update([
                'logo_path' => $request->file('logo')->store('logos', 'public'),
            ]);
        } elseif ($request->boolean('remove_logo') && filled($company->logo_path)) {
            Storage::disk('public')->delete($company->logo_path);
            $company->update(['logo_path' => null]);
        }

        $submittedIds = [];

        foreach ($times as $index => $item) {
            $start = $item['start_time'];
            $end = $item['end_time'];

            if ($start === $end) {
                throw ValidationException::withMessages([
                    "operational_times.$index.end_time" => 'The end time must differ from the start time.',
                ]);
            }

            $data = [
                'label' => ($item['label'] ?? '') !== '' ? $item['label'] : null,
                'start_time' => $start,
                'end_time' => $end,
            ];

            if (! empty($item['id'])) {
                $existing = CompanyOperationalTime::find($item['id']);

                if ($existing === null) {
                    continue;
                }

                $existing->update($data);
                $submittedIds[] = $existing->id;
            } else {
                $created = CompanyOperationalTime::create($data + [
                    'company_settings_id' => $company->id,
                ]);
                $submittedIds[] = $created->id;
            }
        }

        CompanyOperationalTime::query()
            ->where('company_settings_id', $company->id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        return redirect()
            ->route('admin.company.edit')
            ->with('status', 'company-updated');
    }
}
