<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Returns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the states an admin may move a return into by hand. Terminal states that
 * follow from receiving stock or issuing money (item_received, refunded) are
 * set by their own actions.
 */
final class ReviewReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['under_review', 'approved', 'rejected', 'closed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
