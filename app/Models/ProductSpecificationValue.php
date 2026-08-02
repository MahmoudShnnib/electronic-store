<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductSpecificationValue extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'specification_attribute_id', 'value', 'value_ar'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(SpecificationAttribute::class, 'specification_attribute_id');
    }
}
