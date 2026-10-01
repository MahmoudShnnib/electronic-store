<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Category extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'parent_id', 'name', 'name_ar', 'slug', 'description', 'image_path',
        'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function specificationAttributes(): BelongsToMany
    {
        return $this->belongsToMany(SpecificationAttribute::class, 'category_specification_templates')
            ->withPivot(['is_required', 'position'])
            ->orderByPivot('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * The given categories plus every category below them, at any depth.
     *
     * @param  int|list<int>  $roots
     * @return list<int>
     */
    public static function subtreeIds(int|array $roots, bool $activeOnly = false): array
    {
        $byParent = self::query()
            ->when($activeOnly, fn (Builder $q) => $q->active())
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $queue = (array) $roots;

        // The visited check also stops a parent/child loop from spinning forever.
        while ($queue !== []) {
            $id = (int) array_shift($queue);

            if (in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
            array_push($queue, ...$byParent->get($id, collect())->pluck('id')->all());
        }

        return $ids;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'slug', 'parent_id', 'is_active'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
