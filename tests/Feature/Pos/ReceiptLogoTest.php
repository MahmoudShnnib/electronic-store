<?php

use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Admin\PosQueryService;
use App\Services\Pos\ReceiptLogo;
use Illuminate\Http\UploadedFile;

/**
 * @param  array{int,int,int}  $background
 * @param  array{int,int,int}  $mark
 */
function logoPng(array $background, array $mark, int $width = 600, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, ...$background));
    // A mark covering roughly a fifth of the canvas, as a real logo does.
    imagefilledrectangle($image, (int) ($width * 0.4), (int) ($height * 0.4), (int) ($width * 0.6), (int) ($height * 0.6), imagecolorallocate($image, ...$mark));

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

/** A completed counter sale, so the receipt props have something to describe. */
function logoTestSale(): Order
{
    $product = Product::factory()->create(['price_cents' => 25_000]);
    giveStock($product, 5);

    return app(CompleteSale::class)->handle(
        app(OpenShift::class)->handle(staff('cashier'), defaultWarehouse(), 0),
        [['product_id' => $product->id, 'quantity' => 1]],
        PaymentMethod::Cash,
    );
}

/** @return array{width: int, height: int, black: int, total: int} */
function inspect(string $dataUri): array
{
    $image = imagecreatefromstring(base64_decode(explode(',', $dataUri, 2)[1]));
    $width = imagesx($image);
    $height = imagesy($image);
    $black = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ((imagecolorat($image, $x, $y) & 0xFF) < 128) {
                $black++;
            }
        }
    }

    imagedestroy($image);

    return ['width' => $width, 'height' => $height, 'black' => $black, 'total' => $width * $height];
}

it('flips a light mark on a coloured field so the paper stays the light part', function (): void {
    // The shop's own logo: white artwork on mid-blue. Thresholded as-is, the
    // blue burns black and the slip opens with a solid block.
    $prepared = ReceiptLogo::prepare(logoPng([59, 169, 220], [255, 255, 255]));

    $result = inspect($prepared);

    expect($result['black'])->toBeLessThan($result['total'] / 2)
        // The mark itself still has to be there.
        ->and($result['black'])->toBeGreaterThan(0);
});

it('leaves a dark mark on white the way round it already is', function (): void {
    $result = inspect(ReceiptLogo::prepare(logoPng([255, 255, 255], [0, 0, 0])));

    expect($result['black'])->toBeLessThan($result['total'] / 2)
        ->and($result['black'])->toBeGreaterThan(0);
});

it('scales a large logo into the slip rather than across it', function (): void {
    $result = inspect(ReceiptLogo::prepare(logoPng([255, 255, 255], [0, 0, 0], 2000, 1200)));

    // Narrower than the 576-dot head, so the logo keeps a margin.
    expect($result['width'])->toBeLessThanOrEqual(360)
        ->and($result['height'])->toBeLessThanOrEqual(240);
});

it('reduces the logo to pure black and white, with no greys to dither', function (): void {
    $image = imagecreatefromstring(base64_decode(explode(',', ReceiptLogo::prepare(logoPng([59, 169, 220], [255, 255, 255])), 2)[1]));

    $shades = [];

    for ($y = 0; $y < imagesy($image); $y++) {
        for ($x = 0; $x < imagesx($image); $x++) {
            $shades[imagecolorat($image, $x, $y) & 0xFF] = true;
        }
    }

    expect(array_keys($shades))->each->toBeIn([0, 255]);
});

it('refuses a file that is not an image', function (): void {
    ReceiptLogo::prepare('not an image at all');
})->throws(RuntimeException::class);

// -- Through the admin screen -------------------------------------------------

it('stores an uploaded logo already converted, and prints it instead of the name', function (): void {
    $admin = staff('super_admin');

    $this->actingAs($admin)
        ->post(route('admin.settings.receipt-logo.store'), [
            'logo' => UploadedFile::fake()->createWithContent('logo.png', logoPng([59, 169, 220], [255, 255, 255])),
        ])
        ->assertRedirect();

    $stored = Setting::get('receipt_logo');

    expect($stored)->toStartWith('data:image/png;base64,');

    $order = logoTestSale();

    expect(app(PosQueryService::class)->receiptProps($order)['store']['logo'])->toBe($stored);
});

it('can be taken off again', function (): void {
    Setting::set('receipt_logo', 'data:image/png;base64,abc');

    $this->actingAs(staff('super_admin'))
        ->delete(route('admin.settings.receipt-logo.destroy'))
        ->assertRedirect();

    expect(Setting::get('receipt_logo'))->toBeNull();
});

it('keeps the logo away from staff without settings.manage, admins included', function (): void {
    // `admin` is deliberately denied settings.manage by the seeder; only a
    // super admin may change what every receipt carries.
    $this->actingAs(staff('admin'))
        ->post(route('admin.settings.receipt-logo.store'), [
            'logo' => UploadedFile::fake()->createWithContent('logo.png', logoPng([255, 255, 255], [0, 0, 0])),
        ])
        ->assertForbidden();
});
