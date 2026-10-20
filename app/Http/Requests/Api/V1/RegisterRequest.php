<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * device_name labels the Sanctum personal access token so a customer can revoke
 * a single device without signing out everywhere.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Stored lowercase, and unique ignoring case: Postgres compares emails
     * case-sensitively, so `Ali@…` and `ali@…` were becoming two accounts.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && User::withTrashed()->whereRaw('LOWER(email) = ?', [$value])->exists()) {
                        $fail(__('validation.unique'));
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', Password::defaults()],
            'device_name' => ['required', 'string', 'max:64'],
        ];
    }
}
