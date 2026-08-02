<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Attaches the bundled catalogue photography to the demo products.
 *
 * The JPEGs themselves live in storage/app/public/products and are committed with
 * the repo, so this seeder needs no network — it only writes the rows that point
 * at them. Photos are stock imagery of the right *category* (a phone for a phone,
 * an HP printer for the LaserJet); they are not renders of the specific SKUs.
 *
 * Idempotent: re-running replaces a product's image rows rather than duplicating.
 */
final class ProductImageSeeder extends Seeder
{
    /**
     * product slug => list of [file, alt] with the first entry as primary.
     *
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private const IMAGES = [
        'galaxy-s25-ultra' => [
            ['galaxy-s25-ultra-1.jpg', 'Galaxy S25 Ultra front and back'],
            ['galaxy-s25-ultra-2.jpg', 'Galaxy S25 Ultra in bronze'],
        ],
        'iphone-17-pro' => [
            ['iphone-17-pro-1.jpg', 'iPhone 17 Pro camera array'],
            ['iphone-17-pro-2.jpg', 'iPhone 17 Pro in white'],
        ],
        'legion-7-pro-gaming-laptop' => [
            ['legion-7-pro-1.jpg', 'Legion 7 Pro with backlit keyboard'],
            ['legion-7-pro-2.jpg', 'Legion 7 Pro gaming setup'],
        ],
        'zenbook-14-oled' => [
            ['zenbook-14-oled-1.jpg', 'Zenbook 14 OLED open on a desk'],
            ['zenbook-14-oled-2.jpg', 'Zenbook 14 OLED side profile'],
        ],
        'ultrasharp-27-4k-monitor' => [
            ['ultrasharp-27-1.jpg', 'UltraSharp 27 4K monitor on a desk'],
            ['ultrasharp-27-2.jpg', 'UltraSharp 27 4K monitor front view'],
        ],
        'playstation-5-pro' => [
            ['playstation-5-pro-1.jpg', 'PlayStation 5 Pro console with controller'],
        ],
        'geforce-rtx-5080-16gb' => [
            ['rtx-5080-1.jpg', 'GeForce RTX 5080 installed in a lit case'],
        ],
        'archer-ax90-wifi-6-router' => [
            ['archer-ax90-1.jpg', 'Archer AX90 Wi-Fi 6 router'],
            ['archer-ax90-2.jpg', 'Network cabling'],
        ],
        'mx-master-4-wireless-mouse' => [
            ['mx-master-4-1.jpg', 'MX Master 4 wireless mouse'],
            ['mx-master-4-2.jpg', 'Desk setup with keyboard'],
        ],
        'laserjet-pro-m404-printer' => [
            ['laserjet-m404-1.jpg', 'LaserJet Pro M404 printer'],
            ['laserjet-m404-2.jpg', 'Office printer in use'],
        ],
    ];

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (self::IMAGES as $slug => $images) {
            $product = Product::query()->where('slug', $slug)->first();

            if ($product === null) {
                $this->command?->warn("Skipping {$slug}: product not found.");

                continue;
            }

            $rows = [];

            foreach (array_values($images) as $position => [$file, $alt]) {
                if (! $disk->exists("products/{$file}")) {
                    $this->command?->warn("Skipping {$slug}: products/{$file} is missing from the public disk.");

                    continue;
                }

                $rows[] = [
                    'path' => "products/{$file}",
                    'alt' => $alt,
                    'position' => $position,
                    'is_primary' => $position === 0,
                ];
            }

            if ($rows === []) {
                continue;
            }

            // Never touch a product someone has already given artwork to in the
            // dashboard — this seeder only fills in empty demo products.
            if ($product->images()->exists()) {
                continue;
            }

            foreach ($rows as $row) {
                ProductImage::create([...$row, 'product_id' => $product->id]);
            }
        }
    }
}
