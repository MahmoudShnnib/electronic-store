<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inventory\AdjustStock;
use App\Enums\MovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SpecificationAttribute;
use App\Models\SpecificationGroup;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic demo data for local development: an electronics category tree,
 * brands, spec templates, products with variants, and opening stock recorded
 * through the movement ledger (never silently).
 */
final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::default();

        // Categories: english name => [arabic name, [child english => child arabic]]
        $tree = [
            'Smartphones' => ['الهواتف الذكية', ['Android Phones' => 'هواتف أندرويد', 'iPhones' => 'آيفون']],
            'Laptops' => ['الحواسيب المحمولة', ['Gaming Laptops' => 'حواسيب الألعاب', 'Ultrabooks' => 'ألترابوك']],
            'Computers' => ['أجهزة الكمبيوتر', ['Desktops' => 'حواسيب مكتبية', 'All-in-One' => 'الكل في واحد']],
            'Monitors' => ['الشاشات', []],
            'Gaming' => ['الألعاب', ['Consoles' => 'أجهزة الألعاب', 'Controllers' => 'أذرع التحكم']],
            'Networking' => ['الشبكات', ['Routers' => 'أجهزة التوجيه', 'Switches' => 'المحولات']],
            'Components' => ['المكونات', [
                'Graphics Cards' => 'كروت الشاشة',
                'Processors' => 'المعالجات',
                'Memory' => 'الذاكرة',
                'Storage' => 'التخزين',
            ]],
            'Printers' => ['الطابعات', []],
            'Accessories' => ['الإكسسوارات', [
                'Keyboards' => 'لوحات المفاتيح',
                'Mice' => 'الفأرات',
                'Headsets' => 'سماعات الرأس',
                'Cables' => 'الكابلات',
            ]],
        ];

        $categories = [];
        $position = 0;

        // Arabic names are backfilled only when empty, so an admin's own
        // translation is never overwritten by a re-seed.
        $translate = function (Category $category, string $arabic): Category {
            if ($category->name_ar === null || $category->name_ar === '') {
                $category->update(['name_ar' => $arabic]);
            }

            return $category;
        };

        foreach ($tree as $parentName => [$parentArabic, $children]) {
            $parent = Category::query()->firstOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'position' => $position++, 'is_active' => true],
            );
            $categories[$parentName] = $translate($parent, $parentArabic);

            $i = 0;

            foreach ($children as $childName => $childArabic) {
                $child = Category::query()->firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'position' => $i++, 'is_active' => true],
                );
                $categories[$childName] = $translate($child, $childArabic);
            }
        }

        // Brands
        $brands = [];
        foreach (['Samsung', 'Apple', 'Lenovo', 'ASUS', 'Dell', 'HP', 'Logitech', 'TP-Link', 'Sony', 'MSI'] as $i => $name) {
            $brands[$name] = Brand::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'position' => $i, 'is_active' => true],
            );
        }

        $supplier = Supplier::query()->firstOrCreate(
            ['company_name' => 'Global Tech Distribution'],
            ['contact_person' => 'Sales Desk', 'email' => 'sales@gtd.example', 'status' => 'active'],
        );

        // Specification templates
        $groups = [
            'General' => [
                ['name' => 'Model', 'type' => 'text'],
                ['name' => 'Operating system', 'type' => 'text'],
                ['name' => 'Warranty', 'type' => 'text'],
            ],
            'Performance' => [
                ['name' => 'Processor', 'type' => 'text', 'is_filterable' => true],
                ['name' => 'RAM', 'type' => 'text', 'unit' => 'GB', 'is_filterable' => true],
                ['name' => 'Storage', 'type' => 'text', 'unit' => 'GB', 'is_filterable' => true],
                ['name' => 'Graphics card', 'type' => 'text'],
            ],
            'Display' => [
                ['name' => 'Screen size', 'type' => 'number', 'unit' => 'inch', 'is_filterable' => true],
                ['name' => 'Resolution', 'type' => 'text'],
                ['name' => 'Refresh rate', 'type' => 'number', 'unit' => 'Hz'],
            ],
            'Battery & Power' => [
                ['name' => 'Battery capacity', 'type' => 'number', 'unit' => 'mAh'],
            ],
            'Connectivity' => [
                ['name' => 'Network support', 'type' => 'text'],
                ['name' => 'Ports', 'type' => 'text'],
            ],
        ];

        $attributes = [];
        $gi = 0;
        foreach ($groups as $groupName => $attrs) {
            $group = SpecificationGroup::query()->firstOrCreate(
                ['name' => $groupName],
                ['position' => $gi++],
            );

            foreach ($attrs as $ai => $attr) {
                $attributes[$attr['name']] = SpecificationAttribute::query()->firstOrCreate(
                    ['specification_group_id' => $group->id, 'name' => $attr['name']],
                    [
                        'unit' => $attr['unit'] ?? null,
                        'type' => $attr['type'],
                        'is_filterable' => $attr['is_filterable'] ?? false,
                        'position' => $ai,
                    ],
                );
            }
        }

        $adjust = app(AdjustStock::class);
        $adminId = User::query()->where('email', 'admin@store.test')->value('id');

        $demoProducts = [
            [
                'name' => 'Galaxy S25 Ultra', 'category' => 'Android Phones', 'brand' => 'Samsung',
                'price' => 1199_00, 'cost' => 950_00, 'featured' => true,
                'specs' => ['Processor' => 'Snapdragon 8 Gen 4', 'RAM' => '12', 'Screen size' => '6.8', 'Battery capacity' => '5000', 'Operating system' => 'Android 15'],
                'variants' => [
                    ['name' => '256GB / Titanium Black', 'attrs' => ['storage' => '256GB', 'color' => 'Titanium Black'], 'price' => 1199_00, 'stock' => 25],
                    ['name' => '512GB / Titanium Gray', 'attrs' => ['storage' => '512GB', 'color' => 'Titanium Gray'], 'price' => 1329_00, 'stock' => 14],
                ],
            ],
            [
                'name' => 'iPhone 17 Pro', 'category' => 'iPhones', 'brand' => 'Apple',
                'price' => 1299_00, 'cost' => 1050_00, 'featured' => true,
                'specs' => ['Processor' => 'A19 Pro', 'RAM' => '8', 'Screen size' => '6.3', 'Operating system' => 'iOS 19'],
                'variants' => [
                    ['name' => '256GB / Natural', 'attrs' => ['storage' => '256GB', 'color' => 'Natural'], 'price' => 1299_00, 'stock' => 30],
                    ['name' => '1TB / Blue', 'attrs' => ['storage' => '1TB', 'color' => 'Blue'], 'price' => 1699_00, 'stock' => 8],
                ],
            ],
            [
                'name' => 'Legion 7 Pro Gaming Laptop', 'category' => 'Gaming Laptops', 'brand' => 'Lenovo',
                'price' => 2399_00, 'cost' => 1900_00, 'featured' => true, 'sale' => 2199_00,
                'specs' => ['Processor' => 'Intel Core i9-14900HX', 'RAM' => '32', 'Storage' => '1000', 'Graphics card' => 'RTX 4080', 'Screen size' => '16', 'Refresh rate' => '240'],
                'stock' => 12,
            ],
            [
                'name' => 'ZenBook 14 OLED', 'category' => 'Ultrabooks', 'brand' => 'ASUS',
                'price' => 1099_00, 'cost' => 880_00,
                'specs' => ['Processor' => 'Intel Core Ultra 7', 'RAM' => '16', 'Storage' => '512', 'Screen size' => '14'],
                'stock' => 18,
            ],
            [
                'name' => 'UltraSharp 27 4K Monitor', 'category' => 'Monitors', 'brand' => 'Dell',
                'price' => 549_00, 'cost' => 420_00,
                'specs' => ['Screen size' => '27', 'Resolution' => '3840x2160', 'Refresh rate' => '60', 'Ports' => 'HDMI, DP, USB-C'],
                'stock' => 22,
            ],
            [
                'name' => 'PlayStation 5 Pro', 'category' => 'Consoles', 'brand' => 'Sony',
                'price' => 699_00, 'cost' => 580_00, 'featured' => true,
                'specs' => ['Storage' => '2000'],
                'stock' => 9,
            ],
            [
                'name' => 'GeForce RTX 5080 16GB', 'category' => 'Graphics Cards', 'brand' => 'MSI',
                'price' => 1199_00, 'cost' => 999_00,
                'specs' => ['Graphics card' => 'RTX 5080'],
                'stock' => 6,
            ],
            [
                'name' => 'Archer AX90 WiFi 6 Router', 'category' => 'Routers', 'brand' => 'TP-Link',
                'price' => 249_00, 'cost' => 180_00, 'sale' => 219_00,
                'specs' => ['Network support' => 'WiFi 6, Gigabit'],
                'stock' => 35,
            ],
            [
                'name' => 'MX Master 4 Wireless Mouse', 'category' => 'Mice', 'brand' => 'Logitech',
                'price' => 119_00, 'cost' => 80_00,
                'specs' => [],
                'stock' => 60,
            ],
            [
                'name' => 'LaserJet Pro M404 Printer', 'category' => 'Printers', 'brand' => 'HP',
                'price' => 329_00, 'cost' => 250_00,
                'specs' => ['Ports' => 'USB, Ethernet'],
                'stock' => 11,
            ],
        ];

        foreach ($demoProducts as $data) {
            $product = Product::query()->firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'category_id' => $categories[$data['category']]->id,
                    'brand_id' => $brands[$data['brand']]->id,
                    'supplier_id' => $supplier->id,
                    'name' => $data['name'],
                    'sku' => strtoupper(Str::slug($data['name'], '-')).'-'.rand(100, 999),
                    'short_description' => 'High-quality '.strtolower($data['name']).' with full local warranty.',
                    'description' => 'The '.$data['name'].' delivers excellent performance and reliability for work and play.',
                    'cost_price_cents' => $data['cost'],
                    'price_cents' => $data['price'],
                    'sale_price_cents' => $data['sale'] ?? null,
                    'status' => 'active',
                    'is_featured' => $data['featured'] ?? false,
                    'has_variants' => isset($data['variants']),
                    'published_at' => now(),
                ],
            );

            foreach ($data['specs'] as $attrName => $value) {
                if (! isset($attributes[$attrName])) {
                    continue;
                }

                $product->specificationValues()->firstOrCreate(
                    ['specification_attribute_id' => $attributes[$attrName]->id],
                    ['value' => $value],
                );
            }

            if (isset($data['variants'])) {
                foreach ($data['variants'] as $vi => $variantData) {
                    $variant = $product->variants()->firstOrCreate(
                        ['sku' => strtoupper(Str::slug($data['name'], '-')).'-V'.($vi + 1)],
                        [
                            'name' => $variantData['name'],
                            'attributes' => $variantData['attrs'],
                            'cost_price_cents' => (int) round($variantData['price'] * 0.8),
                            'price_cents' => $variantData['price'],
                            'position' => $vi,
                            'is_active' => true,
                        ],
                    );

                    if ($variant->stockLevels()->doesntExist()) {
                        $adjust->handle($warehouse, $product, $variant, $variantData['stock'], MovementType::Initial, 'Opening stock', $adminId);
                    }
                }
            } elseif (($data['stock'] ?? 0) > 0 && $product->stockLevels()->doesntExist()) {
                $adjust->handle($warehouse, $product, null, $data['stock'], MovementType::Initial, 'Opening stock', $adminId);
            }
        }
    }
}
