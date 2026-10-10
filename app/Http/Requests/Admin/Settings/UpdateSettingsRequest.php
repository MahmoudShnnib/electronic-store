<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Keys are filtered against SettingController::EDITABLE before anything is
 * written, so an unknown key here is ignored rather than rejected.
 */
final class UpdateSettingsRequest extends FormRequest
{
    /** The networks the storefront footer has icons for. */
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'tiktok', 'youtube'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            // The footer links straight to these, so they must be real URLs.
            'settings.social_links' => ['sometimes', 'array:'.implode(',', self::SOCIAL_NETWORKS)],
            'settings.social_links.*' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }
}
