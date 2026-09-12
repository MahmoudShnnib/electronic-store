<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Pos\ReceiptLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use RuntimeException;

/**
 * The mark printed at the head of every receipt.
 *
 * Stored as a converted data URI rather than a file on disk, because the receipt
 * is photographed in the browser before it is printed: an <img> pointing at a
 * URL is one more thing that can fail to load in the half-second before the
 * canvas is taken, and a logo that silently misses the snapshot is a blank space
 * on every slip. Inline bytes cannot lose that race.
 */
final class ReceiptLogoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:settings.manage'];
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096'],
        ]);

        try {
            // Converted once, here, rather than on every receipt: the work is
            // per-pixel and a till should not pay for it on each sale.
            Setting::set('receipt_logo', ReceiptLogo::prepare((string) $request->file('logo')->get()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }

        activity()->event('settings_updated')->withProperties(['keys' => ['receipt_logo']])->log('Receipt logo updated');

        return back()->with('success', __('Receipt logo updated.'));
    }

    public function destroy(): RedirectResponse
    {
        Setting::set('receipt_logo', null);

        activity()->event('settings_updated')->withProperties(['keys' => ['receipt_logo']])->log('Receipt logo removed');

        return back()->with('success', __('Receipt logo removed.'));
    }
}
