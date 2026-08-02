<?php

namespace App\Http\Controllers;

use App\Models\SystemSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class SystemSettingsController extends Controller
{
    /**
     * Show the form for editing system settings.
     */
    public function edit(): View
    {
        $settings = app(SystemSettings::class);

        return view('system-settings.edit', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update the system settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $settings = app(SystemSettings::class);

        $rules = [
            'mail_host' => 'required|string',
            'mail_port' => 'required|integer',
            'mail' => 'required|string',
            'mail_pass' => $settings?->mail_pass ? 'nullable|string' : 'required|string',
            'dav_url' => 'nullable|string',
            'dav_user' => 'nullable|string',
            'dav_pass' => 'nullable|string',
            'webdav_user' => 'nullable|string',
            'webdav_pass' => 'nullable|string',
            'webdav_endpoint' => 'nullable|string',
            'webdav_save_path' => 'nullable|string',
            'timezone' => 'required|string',
            'currency' => 'required|string',
            'locale' => 'required|string',
            // Plages horaires globales (La Pépite)
            'hourly_max_hours' => 'required|integer|min:1|max:24',
            'half_day_morning_start' => 'required|date_format:H:i',
            'half_day_morning_end' => 'required|date_format:H:i|after:half_day_morning_start',
            'half_day_afternoon_start' => 'required|date_format:H:i',
            'half_day_afternoon_end' => 'required|date_format:H:i|after:half_day_afternoon_start',
            'half_day_evening_start' => 'required|date_format:H:i',
            'half_day_evening_end' => 'required|date_format:H:i|after:half_day_evening_start',
            'full_day_start' => 'required|date_format:H:i',
            'full_day_end' => 'required|date_format:H:i|after:full_day_start',
            'member_discount_percent' => 'required|integer|min:0|max:100',
        ];

        $validated = $request->validate($rules);

        // Remove empty password fields to keep existing values
        $passwordFields = ['mail_pass', 'dav_pass', 'webdav_pass'];
        foreach ($passwordFields as $field) {
            if (empty($validated[$field])) {
                unset($validated[$field]);
            }
        }

        if (!$settings) {
            $settings = SystemSettings::create($validated);
        } else {
            $settings->update($validated);
        }

        return redirect()->route('system-settings.edit')->with('success', __('System settings updated successfully.'));
    }
}
