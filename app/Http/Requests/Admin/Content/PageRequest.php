<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A storefront page's own fields. System pages (home) keep their key, have no
 * slug and stay published — the SPA addresses them directly and would 404
 * otherwise. A custom page's slug must not shadow a system key, since
 * `/pages/{key-or-slug}` resolves either.
 */
final class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $page = $this->route('page');
        $isSystem = $page instanceof Page && $page->is_system;

        return [
            'title' => ['required', 'string', 'max:120'],
            'title_ar' => ['nullable', 'string', 'max:120'],
            'slug' => $isSystem
                ? ['prohibited']
                : [
                    'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::notIn(Page::SYSTEM_KEYS),
                    Rule::unique('pages', 'slug')->ignore($page),
                    Rule::unique('pages', 'key'),
                ],
            'is_published' => ['boolean'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:120'],
            'seo.title_ar' => ['nullable', 'string', 'max:120'],
            'seo.description' => ['nullable', 'string', 'max:300'],
            'seo.description_ar' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, mixed> */
    public function pageData(): array
    {
        $page = $this->route('page');
        $isSystem = $page instanceof Page && $page->is_system;

        $seo = array_filter(
            array_map(fn (?string $value): ?string => filled($value) ? trim($value) : null, $this->validated('seo') ?? []),
            fn (?string $value): bool => $value !== null,
        );

        return [
            'title' => trim($this->validated('title')),
            'title_ar' => filled($this->validated('title_ar')) ? trim($this->validated('title_ar')) : null,
            ...($isSystem ? [] : ['slug' => $this->validated('slug')]),
            'is_published' => $isSystem || $this->boolean('is_published', true),
            'seo' => $seo === [] ? null : $seo,
        ];
    }
}
