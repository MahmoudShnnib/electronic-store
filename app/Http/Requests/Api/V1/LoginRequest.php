<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately no password complexity rules here — this is a credential check,
 * not a password change, and applying Password::defaults() would leak which
 * accounts predate the current policy.
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Emails are matched case-insensitively: `Ali@Example.com` is `ali@example.com`. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:64'],
        ];
    }
}
