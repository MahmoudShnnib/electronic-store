<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SpecificationAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'specification_group_id', 'name', 'name_ar', 'unit', 'type',
        'is_filterable', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SpecificationGroup::class, 'specification_group_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductSpecificationValue::class);
    }

    /**
     * The categories this attribute applies to. An attribute with no
     * categories is universal — it shows on every product form.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_specification_attribute');
    }
}
