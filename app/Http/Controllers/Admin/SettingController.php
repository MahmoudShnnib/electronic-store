<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

final class SettingController extends Controller implements HasMiddleware
{
    /** Whitelist of editable settings; anything else is rejected. */
    private const EDITABLE = [
        'store_name', 'contact_info', 'social_links', 'bank_transfer_details', 'invoice_details',
        'page.about', 'page.contact', 'page.privacy-policy', 'page.terms', 'page.return-policy',
    ];

    public static function middleware(): array
    {
        return ['can:settings.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('admin/settings/Index', [
            'settings' => collect(self::EDITABLE)
                ->mapWithKeys(fn (string $key) => [$key => Setting::get($key)]),
            // Not in EDITABLE: it is written by its own upload endpoint, which
            // converts the image before storing it.
            'receiptLogo' => Setting::get('receipt_logo'),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated['settings'] as $key => $value) {
            if (! in_array($key, self::EDITABLE, true)) {
                continue;
            }

            Setting::set($key, $value);
        }

        activity()->event('settings_updated')->withProperties(['keys' => array_keys($validated['settings'])])->log('Settings updated');

        return back()->with('success', __('Settings saved.'));
    }
}
