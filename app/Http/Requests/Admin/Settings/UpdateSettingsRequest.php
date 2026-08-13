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
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.return_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
