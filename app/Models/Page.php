<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Content\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A storefront page assembled from ordered sections. System pages are
 * addressed by `key`; custom pages by `slug`.
 */
final class Page extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Keys DefaultPages installs; a custom page may not take one as its slug.
     *
     * @var list<string>
     */
    public const SYSTEM_KEYS = ['home', 'about', 'contact', 'privacy-policy', 'terms', 'return-policy'];

    protected $fillable = ['key', 'slug', 'title', 'title_ar', 'seo', 'is_published', 'is_system'];

    protected function casts(): array
    {
        return [
            'seo' => 'array',
            'is_published' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::saved(ContentCache::bump(...));
        self::deleted(ContentCache::bump(...));
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** By system key, or by slug for custom pages. */
    public function scopeAddressedBy(Builder $query, string $keyOrSlug): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('key', $keyOrSlug)->orWhere('slug', $keyOrSlug));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'slug', 'title', 'title_ar', 'is_published'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
