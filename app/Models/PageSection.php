<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageSectionType;
use App\Support\Content\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** One block of a page; `data` holds the type-specific content. */
final class PageSection extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['page_id', 'type', 'sort_order', 'is_active', 'data'];

    protected function casts(): array
    {
        return [
            'type' => PageSectionType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::saved(ContentCache::bump(...));
        self::deleted(ContentCache::bump(...));
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * The public-disk images this section's data points at (hero slide
     * artwork, promo tile artwork, a banner), so replacing or deleting the
     * section can remove the files it no longer uses.
     *
     * @param  array<string, mixed>|null  $data  defaults to the stored data
     * @return list<string>
     */
    public function imagePaths(?array $data = null): array
    {
        $data ??= $this->data ?? [];

        $paths = match ($this->type) {
            PageSectionType::Hero => array_column($data['slides'] ?? [], 'image_path'),
            PageSectionType::PromoTiles => array_column($data['tiles'] ?? [], 'image_path'),
            PageSectionType::ImageBanner => [$data['image_path'] ?? null],
            default => [],
        };

        return array_values(array_filter($paths, fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['page_id', 'type', 'sort_order', 'is_active', 'data'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
