<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inventory\AdjustStock;
use App\Enums\MovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * GameSir controller line, sourced from the supplier's Arabic product sheet
 * (Gamesir.pdf). Photos and the brand logo are committed with the repo under
 * storage/app/public/{products,brands}, so no network is needed.
 *
 * Idempotent: products are matched by slug, and existing images are kept so
 * dashboard uploads are never clobbered.
 */
final class GameSirSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::default();
        $adjust = app(AdjustStock::class);
        $adminId = User::query()->where('email', 'admin@store.test')->value('id');

        $supplier = Supplier::query()->firstOrCreate(
            ['company_name' => 'Global Tech Distribution'],
            ['contact_person' => 'Sales Desk', 'email' => 'sales@gtd.example', 'status' => 'active'],
        );

        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'gamesir'],
            ['name' => 'GameSir', 'is_active' => true],
        );

        if ($brand->logo_path === null) {
            $brand->update(['logo_path' => 'brands/gamesir.png']);
        }

        $category = Category::query()->where('slug', 'controllers')->first()
            ?? Category::query()->firstOrCreate(
                ['slug' => 'controllers'],
                ['name' => 'Controllers', 'name_ar' => 'أذرع التحكم', 'is_active' => true],
            );

        $products = [
            [
                'name' => 'GameSir Super Nova',
                'name_ar' => 'يد تحكم GameSir Super Nova اللاسلكية الاحترافية',
                'price' => 199_00, 'cost' => 140_00,
                'stock' => 25,
                'featured' => true,
                'short' => 'Professional wireless controller with Hall Effect sticks and an RGB charging dock.',
                'short_ar' => 'يد تحكم لاسلكية احترافية بعصي Hall Effect وقاعدة شحن بإضاءة RGB.',
                'description' => "Level up with the GameSir Super Nova, built for professional performance and high precision across all game genres. Hall Effect drift-resistant sticks ensure accurate response and a longer lifespan, fast-response buttons and a comfortable grip keep long sessions easy on the hands.\n\nConnect over USB-C, Bluetooth, or 2.4GHz for wide device compatibility, and keep it charged and ready on the elegant RGB charging dock.\n\n• Hall Effect drift-resistant sticks\n• Bluetooth, 2.4GHz and USB-C connectivity\n• RGB charging dock\n• Comfortable anti-slip grip\n• Long-lasting battery\n• Works with PC, Nintendo Switch, Android and iOS",
                'description_ar' => "ارتقِ بتجربة اللعب مع GameSir Super Nova، المصممة لتوفير أداء احترافي ودقة عالية في جميع أنواع الألعاب. تأتي بعصي Hall Effect المقاومة للانجراف لضمان استجابة دقيقة وعمر افتراضي أطول، مع أزرار سريعة الاستجابة وتصميم مريح يمنحك راحة أثناء جلسات اللعب الطويلة.\n\nتدعم اليد الاتصال عبر USB-C وBluetooth و2.4GHz مما يجعلها متوافقة مع مجموعة واسعة من الأجهزة، كما تأتي مع قاعدة شحن أنيقة بإضاءة RGB للحفاظ على شحنها وجاهزيتها في جميع الأوقات.\n\nالمميزات:\n• عصي Hall Effect مقاومة للانجراف.\n• اتصال لاسلكي Bluetooth و2.4GHz بالإضافة إلى USB-C.\n• قاعدة شحن بإضاءة RGB.\n• تصميم مريح مع قبضة مانعة للانزلاق.\n• بطارية تدوم لساعات طويلة.\n• متوافقة مع PC وNintendo Switch وAndroid وiOS.",
                'images' => [['gamesir-super-nova.jpg', 'GameSir Super Nova wireless controller on its RGB charging dock']],
            ],
            [
                'name' => 'GameSir Nova Lite',
                'name_ar' => 'يد تحكم GameSir Nova Lite',
                'price' => 119_00, 'cost' => 80_00,
                'stock' => 40,
                'short' => 'Reliable lightweight controller with Hall Effect sticks and triple connectivity.',
                'short_ar' => 'يد تحكم خفيفة وموثوقة بعصي Hall Effect وثلاث طرق اتصال.',
                'description' => "Enjoy smooth gaming with the GameSir Nova Lite, designed for dependable performance and a comfortable fit for every play style. Hall Effect drift-resistant sticks deliver high precision and a longer lifespan, alongside fast-response buttons for excellent control in every game.\n\nSupports Bluetooth, 2.4GHz and USB-C so you can play across all your devices.\n\n• Hall Effect drift-resistant sticks\n• Bluetooth, 2.4GHz and USB-C connectivity\n• Comfortable lightweight design for long sessions\n• Precise, fast-response buttons\n• Long-life rechargeable battery\n• Works with PC, Nintendo Switch, Android and iOS",
                'description_ar' => "استمتع بتجربة لعب سلسة مع GameSir Nova Lite، المصممة لتقديم أداء موثوق وتصميم مريح يناسب مختلف أساليب اللعب. تتميز بعصي Hall Effect المقاومة للانجراف، مما يوفر دقة عالية وعمرًا افتراضيًا أطول، إلى جانب أزرار سريعة الاستجابة تمنحك تحكمًا ممتازًا في جميع الألعاب.\n\nتدعم اليد عدة طرق اتصال USB-C وBluetooth و2.4GHz لتمنحك مرونة في الاستخدام على مختلف الأجهزة، بما في ذلك.\n\nالمميزات:\n• عصي Hall Effect مقاومة للانجراف.\n• اتصال عبر Bluetooth و2.4GHz وUSB-C.\n• تصميم مريح وخفيف للاستخدام الطويل.\n• أزرار دقيقة وسريعة الاستجابة.\n• بطارية قابلة لإعادة الشحن بعمر تشغيل طويل.\n• متوافقة مع PC، Nintendo Switch، Android، وiOS.",
                'images' => [['gamesir-nova-lite.jpg', 'GameSir Nova Lite controller']],
            ],
            [
                'name' => 'GameSir Nova Lite Signature Edition',
                'name_ar' => 'يد تحكم GameSir Nova Lite إصدار التوقيع الذهبي',
                'price' => 149_00, 'cost' => 100_00,
                'stock' => 15,
                'short' => 'Limited gold edition with a signature finish and Hall Effect sticks.',
                'short_ar' => 'إصدار ذهبي خاص بتوقيع مميز وعصي Hall Effect.',
                'description' => "The GameSir Nova Lite Signature Edition combines professional performance with a luxurious gold finish and an elegant signature that gives it a unique look, while keeping every feature players need for smooth, precise gaming.\n\nEquipped with Hall Effect drift-resistant sticks for higher precision and a longer lifespan, plus a comfortable design and fast-response buttons for complete control in every game.\n\n• Special edition with a luxury gold design\n• Hall Effect drift-resistant sticks\n• Bluetooth, 2.4GHz and USB-C connectivity\n• Comfortable for long sessions\n• Rechargeable battery with excellent runtime\n• Works with PC, Nintendo Switch, Android and iOS",
                'description_ar' => "اجمع بين الأداء الاحترافي والتصميم الفاخر مع GameSir Nova Lite Signature Edition. تأتي هذه النسخة بتشطيب ذهبي أنيق مع توقيع مميز يمنحها مظهرًا فريدًا، مع الحفاظ على جميع المزايا التي يحتاجها اللاعبون للحصول على تجربة لعب سلسة ودقيقة.\n\nمزودة بعصي Hall Effect المقاومة للانجراف لضمان دقة أعلى وعمر استخدام أطول، بالإضافة إلى تصميم مريح وأزرار سريعة الاستجابة تمنحك تحكمًا كاملاً في مختلف الألعاب.\n\nالمميزات:\n• إصدار خاص بتصميم ذهبي فاخر.\n• عصي Hall Effect مقاومة للانجراف.\n• اتصال عبر Bluetooth و2.4GHz وUSB-C.\n• تصميم مريح للاستخدام لساعات طويلة.\n• بطارية قابلة لإعادة الشحن بعمر تشغيل ممتاز.\n• متوافقة مع PC، Nintendo Switch، Android، وiOS.",
                'images' => [['gamesir-nova-lite-signature.jpg', 'GameSir Nova Lite Signature Edition in gold']],
            ],
            [
                'name' => 'GameSir T3 Lite',
                'name_ar' => 'يد تحكم GameSir T3 Lite السلكية',
                'price' => 89_00, 'cost' => 60_00,
                'stock' => 50,
                'short' => 'Wired USB controller with instant response and a lightweight comfortable design.',
                'short_ar' => 'يد تحكم سلكية عبر USB باستجابة فورية وتصميم خفيف ومريح.',
                'description' => "Enjoy stable, fast gaming with the GameSir T3 Lite, engineered for instant response thanks to its wired USB connection. A comfortable design suits long play sessions, with precise buttons and smooth sticks for excellent control across all game genres.\n\n• Fast, stable wired USB connection\n• Comfortable lightweight design\n• Precise sticks and highly responsive buttons\n• Works with PC and Nintendo Switch\n• Durable build quality for daily use",
                'description_ar' => "استمتع بتجربة لعب مستقرة وسريعة مع GameSir T3 Lite، المصممة لتقديم استجابة فورية بفضل الاتصال السلكي عبر USB. تتميز بتصميم مريح يناسب جلسات اللعب الطويلة، مع أزرار دقيقة وعصي تحكم سلسة تمنحك تحكمًا ممتازًا في مختلف الألعاب.\n\nالمميزات:\n• اتصال USB سلكي سريع ومستقر.\n• تصميم مريح وخفيف الوزن.\n• عصا تحكم دقيقة وأزرار عالية الاستجابة.\n• توافق مع PC وNintendo Switch.\n• جودة تصنيع متينة للاستخدام اليومي.",
                'images' => [
                    ['gamesir-t3-lite-1.jpg', 'GameSir T3 Lite wired controller in grey'],
                    ['gamesir-t3-lite-2.jpg', 'GameSir T3 Lite wired controller in white'],
                ],
            ],
        ];

        foreach ($products as $data) {
            $product = Product::query()->firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'supplier_id' => $supplier->id,
                    'name' => $data['name'],
                    'name_ar' => $data['name_ar'],
                    'sku' => strtoupper(Str::slug($data['name'], '-')).'-'.rand(100, 999),
                    'short_description' => $data['short'],
                    'short_description_ar' => $data['short_ar'],
                    'description' => $data['description'],
                    'description_ar' => $data['description_ar'],
                    'cost_price_cents' => $data['cost'],
                    'price_cents' => $data['price'],
                    'status' => 'active',
                    'is_featured' => $data['featured'] ?? false,
                    'published_at' => now(),
                ],
            );

            // Never clobber photos an admin may have uploaded since.
            if ($product->images()->doesntExist()) {
                foreach ($data['images'] as $i => [$file, $alt]) {
                    $product->images()->create([
                        'path' => 'products/'.$file,
                        'alt' => $alt,
                        'position' => $i,
                        'is_primary' => $i === 0,
                    ]);
                }
            }

            if ($data['stock'] > 0 && $product->stockLevels()->doesntExist()) {
                $adjust->handle($warehouse, $product, null, $data['stock'], MovementType::Initial, 'Opening stock', $adminId);
            }
        }
    }
}
