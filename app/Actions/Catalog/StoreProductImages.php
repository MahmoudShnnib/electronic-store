<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores uploaded product images on the public disk with hashed names.
 * MIME/type/size validation happens in the Form Request; this action only
 * ever sees vetted files.
 */
final class StoreProductImages
{
    /**
     * @param  list<UploadedFile>  $files
     * @return list<ProductImage>
     */
    public function handle(Product $product, array $files, bool $firstIsPrimary = false): array
    {
        $startPosition = (int) ($product->images()->max('position') ?? -1) + 1;
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        $stored = [];

        foreach ($files as $index => $file) {
            $path = $file->store("products/{$product->id}", 'public');

            $stored[] = $product->images()->create([
                'path' => $path,
                'alt' => $product->name,
                'position' => $startPosition + $index,
                'is_primary' => ! $hasPrimary && ($firstIsPrimary || $index === 0) && $startPosition === 0,
            ]);
        }

        return $stored;
    }

    public function delete(ProductImage $image): void
    {
        Storage::disk('public')->delete($image->path);

        $wasPrimary = $image->is_primary;
        $product = $image->product;

        $image->delete();

        if ($wasPrimary) {
            $product->images()->orderBy('position')->first()?->update(['is_primary' => true]);
        }
    }
}
