<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Orders;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape only — whether the requested transition is legal from the order's
 * current state is decided by the state map in OrderStatus.
 */
final class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Refunded is reached only through the return/problem form, which moves
            // the money and the stock; a bare status flip would do neither.
            'status' => ['required', Rule::enum(OrderStatus::class)->except([OrderStatus::Refunded])],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
