<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Brand
 */
final class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'logo' => $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
