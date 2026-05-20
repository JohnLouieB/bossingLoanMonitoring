<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SystemSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_server_amount' => ['nullable', 'numeric', 'min:0'],
            'yearly_domain_amount' => ['nullable', 'numeric', 'min:0'],
            'export_csv_email' => ['nullable', 'email', 'max:255'],
        ]);

        SystemSetting::current()->update($validated);

        return Redirect::route('profile.edit')->with('status', 'system-settings-saved');
    }
}
