<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Roles;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A role's name is immutable once created — only its permission set is editable.
 * The super_admin role is blocked outright in the controller.
 */
final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
