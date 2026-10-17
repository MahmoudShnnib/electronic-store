<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The page's sections in their new order. Every section of the page must be
 * listed exactly once, so a stale tab cannot silently drop or duplicate one.
 */
final class ReorderSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Page $page */
                $page = $this->route('page');
                $current = $page->sections()->pluck('id')->sort()->values()->all();
                $given = collect($this->input('ids'))->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

                if ($current !== $given) {
                    $validator->errors()->add('ids', __('The sections changed in another tab. Reload and try again.'));
                }
            },
        ];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_map('intval', $this->validated('ids')));
    }
}
