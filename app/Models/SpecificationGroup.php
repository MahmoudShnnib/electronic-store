<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SpecificationGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_ar', 'position'];

    public function attributes(): HasMany
    {
        return $this->hasMany(SpecificationAttribute::class)->orderBy('position');
    }
}
