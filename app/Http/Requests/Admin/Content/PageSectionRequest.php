<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Enums\PageSectionType;
use App\Enums\ProductSource;
use App\Http\Controllers\Admin\Content\SectionImageController;
use App\Models\PageSection;
use App\Support\Content\HtmlSanitizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * One page section: its type (on create only — a section never changes type)
 * and the type's `data`, shaped as the table in
 * docs/adr/0001-headless-storefront-split.md describes. Only keys with a rule
 * survive `validated()`, so stray fields never reach the stored JSON.
 *
 * `image_path` values must be images this section already had, or ones the
 * upload endpoint stored under `pages/` — never an arbitrary file on the disk.
 */
final class PageSectionRequest extends FormRequest
{
    public const MAX_SLIDES = 8;

    public const MAX_TILES = 2;

    public const MAX_TRUST_ITEMS = 8;

    public const MAX_CAROUSEL_PRODUCTS = 24;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->sectionType();

        $data = collect($type !== null ? $this->dataRules($type) : [])
            ->mapWithKeys(fn (array $rules, string $field): array => ["data.{$field}" => $rules])
            ->all();

        return [
            ...($this->route('section') === null ? ['type' => ['required', Rule::enum(PageSectionType::class)]] : []),
            'is_active' => ['sometimes', 'boolean'],
            'data' => ['nullable', 'array'],
            ...$data,
        ];
    }

    /** Readable names, so a message says "image" rather than "data.image path". */
    public function attributes(): array
    {
        return [
            'data.slides' => __('slides'),
            'data.slides.*.product_id' => __('product'),
            'data.tiles' => __('tiles'),
            'data.tiles.*.category_id' => __('category'),
            'data.items' => __('items'),
            'data.items.*.icon' => __('icon'),
            'data.items.*.title' => __('title'),
            'data.title' => __('title'),
            'data.icon' => __('icon'),
            'data.source' => __('source'),
            'data.category_id' => __('category'),
            'data.product_ids' => __('products'),
            'data.limit' => __('number of products'),
            'data.image_path' => __('image'),
            'data.link' => __('link'),
        ];
    }

    public function sectionType(): ?PageSectionType
    {
        $section = $this->route('section');

        return $section instanceof PageSection
            ? $section->type
            : PageSectionType::tryFrom((string) $this->input('type'));
    }

    /**
     * The validated data, trimmed and normalised: blank strings become null,
     * ids become integers, fields another carousel source would use are
     * dropped, and rich text is sanitised.
     *
     * @return array<string, mixed>
     */
    public function sectionData(): array
    {
        $data = $this->blankToNull($this->validated('data') ?? []);

        return match ($this->sectionType()) {
            PageSectionType::Hero => ['slides' => array_map(fn (array $slide): array => [
                'product_id' => (int) $slide['product_id'],
                'title' => $slide['title'] ?? null,
                'title_ar' => $slide['title_ar'] ?? null,
                'image_path' => $slide['image_path'] ?? null,
            ], array_values($data['slides'] ?? []))],
            PageSectionType::PromoTiles => ['tiles' => array_map(fn (array $tile): array => [
                'category_id' => (int) $tile['category_id'],
                'title' => $tile['title'] ?? null,
                'title_ar' => $tile['title_ar'] ?? null,
                'image_path' => $tile['image_path'] ?? null,
            ], array_values($data['tiles'] ?? []))],
            PageSectionType::TrustStrip => ['items' => array_map(fn (array $item): array => [
                'icon' => $item['icon'],
                'title' => $item['title'],
                'title_ar' => $item['title_ar'] ?? null,
                'text' => $item['text'] ?? null,
                'text_ar' => $item['text_ar'] ?? null,
            ], array_values($data['items'] ?? []))],
            PageSectionType::ProductCarousel => $this->carouselData($data),
            PageSectionType::CategorySpotlight => array_filter(['count' => isset($data['count']) ? (int) $data['count'] : null]),
            PageSectionType::RichText => [
                'body' => HtmlSanitizer::clean($data['body'] ?? null),
                'body_ar' => HtmlSanitizer::clean($data['body_ar'] ?? null),
            ],
            PageSectionType::CategoryRail,
            PageSectionType::BrandStrip => $this->pick($data, ['title', 'title_ar']),
            PageSectionType::ImageBanner => $this->pick($data, ['image_path', 'link', 'title', 'title_ar']),
            PageSectionType::Newsletter => $this->pick($data, ['title', 'title_ar', 'text', 'text_ar']),
            null => [],
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function dataRules(PageSectionType $type): array
    {
        $text = fn (int $max = 120): array => ['nullable', 'string', "max:{$max}"];
        $titles = ['title' => $text(), 'title_ar' => $text()];
        $icon = ['string', 'max:40', 'alpha_dash:ascii'];

        return match ($type) {
            PageSectionType::Hero => [
                'slides' => ['present', 'array', 'max:'.self::MAX_SLIDES],
                'slides.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
                'slides.*.title' => $text(),
                'slides.*.title_ar' => $text(),
                'slides.*.image_path' => ['nullable', 'string', 'max:255', $this->knownImage()],
            ],
            PageSectionType::PromoTiles => [
                'tiles' => ['present', 'array', 'max:'.self::MAX_TILES],
                'tiles.*.category_id' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')],
                'tiles.*.title' => $text(),
                'tiles.*.title_ar' => $text(),
                'tiles.*.image_path' => ['nullable', 'string', 'max:255', $this->knownImage()],
            ],
            PageSectionType::TrustStrip => [
                'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_TRUST_ITEMS],
                'items.*.icon' => ['required', ...$icon],
                'items.*.title' => ['required', 'string', 'max:120'],
                'items.*.title_ar' => $text(),
                'items.*.text' => $text(255),
                'items.*.text_ar' => $text(255),
            ],
            PageSectionType::ProductCarousel => [
                ...$titles,
                'icon' => ['nullable', ...$icon],
                'source' => ['required', Rule::enum(ProductSource::class)],
                // Fields another source would use are excluded, not validated.
                'category_id' => ['exclude_unless:data.source,'.ProductSource::Category->value, 'required', 'integer', Rule::exists('categories', 'id')],
                'product_ids' => ['exclude_unless:data.source,'.ProductSource::Manual->value, 'required', 'array', 'max:'.self::MAX_CAROUSEL_PRODUCTS],
                'product_ids.*' => ['exclude_unless:data.source,'.ProductSource::Manual->value, 'integer', 'distinct', Rule::exists('products', 'id')],
                'limit' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CAROUSEL_PRODUCTS],
            ],
            PageSectionType::CategorySpotlight => [
                'count' => ['nullable', 'integer', 'min:1', 'max:12'],
            ],
            PageSectionType::CategoryRail,
            PageSectionType::BrandStrip => $titles,
            PageSectionType::ImageBanner => [
                ...$titles,
                'image_path' => ['required', 'string', 'max:255', $this->knownImage()],
                // A storefront path or a full URL; nothing script-shaped.
                'link' => ['nullable', 'string', 'max:255', 'regex:~^(/|https?://)~i'],
            ],
            PageSectionType::RichText => [
                'body' => $text(20000),
                'body_ar' => $text(20000),
            ],
            PageSectionType::Newsletter => [
                ...$titles,
                'text' => $text(255),
                'text_ar' => $text(255),
            ],
        };
    }

    private function knownImage(): Closure
    {
        $section = $this->route('section');
        $known = $section instanceof PageSection ? $section->imagePaths() : [];

        return function (string $attribute, mixed $value, Closure $fail) use ($known): void {
            if (! is_string($value) || $value === '' || in_array($value, $known, true)) {
                return;
            }

            if (! str_starts_with($value, SectionImageController::DIRECTORY.'/')
                || str_contains($value, '..')
                || ! Storage::disk('public')->exists($value)) {
                $fail(__('Upload the image again.'));
            }
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function carouselData(array $data): array
    {
        $source = ProductSource::from($data['source']);

        return [
            'title' => $data['title'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'icon' => $data['icon'] ?? null,
            'source' => $source->value,
            ...($source === ProductSource::Category ? ['category_id' => (int) $data['category_id']] : []),
            ...($source === ProductSource::Manual ? ['product_ids' => array_values(array_map('intval', $data['product_ids']))] : []),
            'limit' => (int) $data['limit'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function pick(array $data, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $data[$key] ?? null])->all();
    }

    private function blankToNull(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->blankToNull(...), $value);
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
