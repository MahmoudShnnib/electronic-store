<?php

use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\PosQueryService;
use App\Services\Pos\EscPosDocument;
use App\Services\Pos\PrinterConfig;
use App\Services\Pos\Printers\CupsDriverPrinter;
use App\Services\Pos\Printers\NullPrinter;
use App\Services\Pos\Printers\PrinterConnection;
use App\Services\Pos\Raster;
use App\Services\Pos\ReceiptPrinter;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;

/** A PNG whose left half is solid black, so the packed bits are predictable. */
function slipPng(int $width = 8, int $height = 1): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 255, 255, 255));
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 0, 0, 0));

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

function slipUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('receipt.png', slipPng(576, 4));
}

function posSale(User $cashier, PaymentMethod $method = PaymentMethod::Cash): Order
{
    $product = Product::factory()->create(['price_cents' => 25_000]);
    giveStock($product, 5);

    return app(CompleteSale::class)->handle(
        app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0),
        [['product_id' => $product->id, 'quantity' => 1]],
        $method,
    );
}

/** Route the printer at nothing, and hand the test the bytes it would have sent. */
function capturePrinter(): NullPrinter
{
    config(['pos.printer.driver' => 'device', 'pos.printer.device' => '/dev/null']);

    $printer = new NullPrinter;
    app()->instance(PrinterConnection::class, $printer);

    return $printer;
}

// -- Raster -------------------------------------------------------------------

it('packs eight dots into one byte, most significant bit leftmost', function (): void {
    $raster = Raster::fromImage(slipPng(8, 1), 8);

    expect($raster->width)->toBe(8)
        ->and($raster->height)->toBe(1)
        ->and($raster->bytesPerRow())->toBe(1)
        // Left half black, right half white.
        ->and(bin2hex($raster->bits))->toBe('f0');
});

it('scales an oversized image down to the head width', function (): void {
    $raster = Raster::fromImage(slipPng(1200, 10), 576);

    expect($raster->width)->toBe(576)
        ->and($raster->height)->toBe(5)
        ->and(strlen($raster->bits))->toBe(576 / 8 * 5);
});

it('refuses a head width that is not a whole number of bytes', function (): void {
    Raster::fromImage(slipPng(), 570);
})->throws(RuntimeException::class);

it('refuses bytes that are not an image', function (): void {
    Raster::fromImage('this is not a png', 576);
})->throws(RuntimeException::class);

// -- ESC/POS ------------------------------------------------------------------

it('builds a stream that initialises, rasterises, cuts and kicks the drawer', function (): void {
    $bytes = app(ReceiptPrinter::class)->build(slipPng(576, 4), openDrawer: true);

    expect($bytes)->toStartWith("\x1B\x40")            // ESC @
        ->and($bytes)->toContain("\x1D\x76\x30\x00")   // GS v 0
        ->and($bytes)->toContain("\x1B\x70\x00\x19\xFA") // ESC p — drawer
        ->and($bytes)->toEndWith("\x1D\x56\x42\x00");  // GS V B — partial cut
});

it('leaves the drawer shut when the sale was not cash', function (): void {
    $bytes = app(ReceiptPrinter::class)->build(slipPng(576, 4), openDrawer: false);

    expect($bytes)->not->toContain("\x1B\x70");
});

it('bands a tall slip into short strips the head can drain', function (): void {
    $bytes = app(ReceiptPrinter::class)->build(slipPng(576, 300));

    // 300 rows at 24 to a band is thirteen GS v 0 headers.
    expect(substr_count($bytes, "\x1D\x76\x30\x00"))->toBe(13);
});

it('delivers a tall receipt in several small writes rather than one blob', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);
    $printer = capturePrinter();
    config(['pos.printer.chunk_bytes' => 8192]);

    // A realistic slip: 576 dots wide, roughly the height of a one-item receipt.
    $this->actingAs($cashier)
        ->post(route('admin.pos.receipt.print', $order), [
            'image' => UploadedFile::fake()->createWithContent('receipt.png', slipPng(576, 1150)),
        ])
        ->assertOk();

    $writes = $printer->writes();

    expect(count($writes))->toBeGreaterThan(1)
        // Nothing handed over may exceed the buffer we promised to respect.
        ->and(max(array_map(strlen(...), $writes)))->toBeLessThanOrEqual(8192)
        // Reassembled, it is still exactly the document that was built.
        ->and(implode('', $writes))->toEndWith("\x1D\x56\x42\x00");
});

it('never splits a chunk in the middle of a command', function (): void {
    $document = (new EscPosDocument)->initialise()
        ->image(Raster::fromImage(slipPng(576, 600), 576))
        ->feed(4)
        ->cut();

    // Every chunk must open on an ESC or GS introducer; a split inside a raster
    // band is exactly what makes a printer dump the rest of the slip as text.
    foreach ($document->chunks(8192) as $chunk) {
        expect($chunk[0])->toBeIn(["\x1B", "\x1D"]);
    }

    expect(implode('', $document->chunks(8192)))->toBe($document->bytes());
});

it('sends a single chunk when chunking is switched off', function (): void {
    $document = (new EscPosDocument)->initialise()->feed(1)->cut();

    expect($document->chunks(0))->toBe([$document->bytes()]);
});

// -- The endpoint -------------------------------------------------------------

it('sends the slip to the configured printer and pops the drawer on cash', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);
    $printer = capturePrinter();

    $this->actingAs($cashier)
        ->post(route('admin.pos.receipt.print', $order), ['image' => slipUpload()])
        ->assertOk()
        ->assertJson(['printed' => true]);

    expect($printer->lastWrite())->toContain("\x1B\x70\x00\x19\xFA");
});

it('never pops the drawer for a transfer, whatever the browser posts', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier, PaymentMethod::BankTransfer);
    $printer = capturePrinter();

    $this->actingAs($cashier)
        ->post(route('admin.pos.receipt.print', $order), ['image' => slipUpload(), 'drawer' => true])
        ->assertOk();

    expect($printer->lastWrite())->not->toContain("\x1B\x70");
});

it('tells the register to fall back when no printer is configured', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);

    config(['pos.printer.driver' => null]);

    $this->actingAs($cashier)
        ->post(route('admin.pos.receipt.print', $order), ['image' => slipUpload()])
        ->assertStatus(409)
        ->assertJson(['printed' => false, 'reason' => 'not_configured']);
});

it('rejects anything that is not a PNG', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);
    capturePrinter();

    $this->actingAs($cashier)
        ->postJson(route('admin.pos.receipt.print', $order), ['image' => UploadedFile::fake()->create('slip.pdf', 10)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('is closed to staff without pos.use', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);
    capturePrinter();

    $this->actingAs(staff('inventory_manager'))
        ->post(route('admin.pos.receipt.print', $order), ['image' => slipUpload()])
        ->assertForbidden();
});

// -- Where the wiring is read from --------------------------------------------

it('takes the printer from the settings table when .env is silent', function (): void {
    config(['pos.printer.driver' => null, 'pos.printer.command' => null]);

    PrinterConfig::store(['driver' => 'command', 'command' => 'lp -d POS80 -o raw', 'width' => 384]);

    expect(PrinterConfig::isConfigured())->toBeTrue()
        ->and(PrinterConfig::get('driver'))->toBe('command')
        ->and(PrinterConfig::get('width'))->toBe(384);
});

it('lets .env outrank a stored setting, so a server can pin its printer', function (): void {
    config(['pos.printer.driver' => 'network', 'pos.printer.host' => '10.0.0.9']);

    PrinterConfig::store(['driver' => 'command', 'command' => 'lp -d SOMEWHERE-ELSE -o raw']);

    expect(PrinterConfig::get('driver'))->toBe('network')
        ->and(PrinterConfig::get('host'))->toBe('10.0.0.9');
});

it('falls back to the browser once the stored settings are cleared', function (): void {
    config(['pos.printer.driver' => null]);

    PrinterConfig::store(['driver' => 'command', 'command' => 'lp -d POS80 -o raw']);
    expect(PrinterConfig::isConfigured())->toBeTrue();

    PrinterConfig::forget();
    expect(PrinterConfig::isConfigured())->toBeFalse();
});

it('prints through a printer that only the settings table knows about', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);

    config(['pos.printer.driver' => null]);
    PrinterConfig::store(['driver' => 'command', 'command' => 'lp -d POS80 -o raw']);

    $printer = new NullPrinter;
    app()->instance(PrinterConnection::class, $printer);

    $this->actingAs($cashier)
        ->post(route('admin.pos.receipt.print', $order), ['image' => slipUpload()])
        ->assertOk()
        ->assertJson(['printed' => true]);

    expect($printer->lastWrite())->toStartWith("\x1B\x40");
});

// -- The vendor driver path ---------------------------------------------------

it('counts the vendor driver as a configured printer', function (): void {
    config(['pos.printer.driver' => 'cups', 'pos.printer.queue' => 'XP80']);

    expect(PrinterConfig::isConfigured())->toBeTrue();
});

it('cuts the page to the receipt height instead of a fixed 210mm', function (): void {
    $printer = new CupsDriverPrinter('XP80');

    // 1152 dot rows at 203.2dpi is 408 points — not the PPD's 595-point default.
    $arguments = $printer->arguments('/tmp/slip.png', 1152, openDrawer: false);

    expect($arguments)->toContain('PageSize=Custom.204x409')
        ->and($arguments)->toContain('ppi=203')
        ->and($arguments)->toContain('position=top-left');
});

it('leaves no margin for CUPS to pad the slip with', function (): void {
    $arguments = (new CupsDriverPrinter('XP80'))->arguments('/tmp/slip.png', 1152, openDrawer: false);

    // The image filter otherwise insets a quarter-inch, which reads as a band of
    // blank roll above the logo on every single receipt.
    foreach (['page-top=0', 'page-bottom=0', 'page-left=0', 'page-right=0'] as $margin) {
        expect($arguments)->toContain($margin);
    }
});

it('asks the driver to cut and to pop the drawer only on cash', function (): void {
    $printer = new CupsDriverPrinter('XP80');

    expect($printer->arguments('/tmp/s.png', 400, openDrawer: true))
        ->toContain('CashDrawer1Setting=2CashDrawer1AfterPrinting')
        ->toContain('PageCutType=1PartialCutPage');

    expect($printer->arguments('/tmp/s.png', 400, openDrawer: false))
        ->toContain('CashDrawer1Setting=0NotCashDrawer1');

    expect($printer->arguments('/tmp/s.png', 400, openDrawer: false, cut: false))
        ->toContain('PageCutType=0NoCutPage');
});

it('keeps the burn density inside the range the head accepts', function (): void {
    $printer = new CupsDriverPrinter('XP80');

    expect($printer->arguments('/tmp/s.png', 400, false, true, 99))->toContain('PrintDensity=8')
        ->and($printer->arguments('/tmp/s.png', 400, false, true, 0))->toContain('PrintDensity=1');
});

// -- What the slip shows ------------------------------------------------------

it('keeps the anonymous walk-in placeholder off the customer receipt', function (): void {
    $order = posSale(staff('cashier'));

    $props = app(PosQueryService::class)->receiptProps($order);

    // CompleteSale stamps the placeholder on the order; the slip must not.
    expect($order->customer['name'])->toBe('Walk-in customer')
        ->and($props['order']['customer_name'])->toBeNull()
        ->and($props['order'])->not->toHaveKey('branch');
});

it('still prints a customer who actually gave a name', function (): void {
    $product = Product::factory()->create(['price_cents' => 25_000]);
    giveStock($product, 5);

    $order = app(CompleteSale::class)->handle(
        app(OpenShift::class)->handle(staff('cashier'), defaultWarehouse(), 0),
        [['product_id' => $product->id, 'quantity' => 1]],
        PaymentMethod::Cash,
        ['name' => 'Fatima Ali'],
    );

    expect(app(PosQueryService::class)->receiptProps($order)['order']['customer_name'])->toBe('Fatima Ali');
});

it('hides the placeholder even when the sale was rung up in another locale', function (): void {
    $order = posSale(staff('cashier'));

    // Sold in English, reprinted in Arabic — the stored name is still the
    // English placeholder, and it must still be recognised as anonymous.
    app()->setLocale('ar');

    expect(app(PosQueryService::class)->receiptProps($order)['order']['customer_name'])->toBeNull();
});

// -- Money on the slip --------------------------------------------------------

it('leaves the amount free to follow the slip direction', function (): void {
    $order = posSale(staff('cashier'));

    $props = app(PosQueryService::class)->receiptProps($order);

    // U+2066 LTR ISOLATE would pin the number to the left of the symbol, which
    // on an Arabic receipt reads backwards.
    foreach (['subtotal_formatted', 'total_formatted', 'paid_formatted'] as $key) {
        expect($props['order'][$key])->not->toContain("\u{2066}")
            ->and($props['order'][$key])->not->toContain("\u{2069}");
    }

    expect($props['order']['items'][0]['unit_price_formatted'])->not->toContain("\u{2066}")
        ->and($props['order']['items'][0]['line_total_formatted'])->not->toContain("\u{2066}");
});

it('still isolates amounts everywhere else, so a price inside a sentence holds', function (): void {
    // 100+ call sites rely on the default; only the receipt opts out.
    expect(Money::format(1_234_00))->toStartWith("\u{2066}")->toEndWith("\u{2069}")
        ->and(Money::format(1_234_00, isolate: false))->toBe('1,234.000 LYD');
});

// -- The till never leaves the register ---------------------------------------

it('returns the cashier to the register rather than a receipt page', function (): void {
    $cashier = staff('cashier');
    $product = Product::factory()->create(['price_cents' => 25_000]);
    giveStock($product, 5);
    app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0);

    $response = $this->actingAs($cashier)
        ->from(route('admin.pos.register'))
        ->post(route('admin.pos.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Cash->value,
        ]);

    $response->assertRedirect(route('admin.pos.register'))
        ->assertSessionHas('justSold');

    // The whole slip travels back, so the register can print it without a
    // second request.
    $sold = session('justSold');

    expect($sold['order']['items'])->toHaveCount(1)
        ->and($sold['store']['name'])->not->toBeEmpty()
        ->and($sold['printer']['direct'])->toBeBool();
});

it('hands the register the slip to print, once', function (): void {
    $cashier = staff('cashier');
    $product = Product::factory()->create(['price_cents' => 25_000]);
    giveStock($product, 5);
    app(OpenShift::class)->handle($cashier, defaultWarehouse(), 0);

    $this->actingAs($cashier)
        ->from(route('admin.pos.register'))
        ->post(route('admin.pos.sales.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => PaymentMethod::Cash->value,
        ]);

    $this->actingAs($cashier)
        ->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/Register')
            ->has('justSold.order.number')
            ->has('justSold.order.items', 1)
            ->has('justSold.store.name')
            ->has('justSold.printer.direct')
            ->etc()
        );

    // Flash, so a refresh does not reprint the last sale.
    $this->actingAs($cashier)
        ->get(route('admin.pos.register'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('justSold', null));
});

it('prints the product name alone, with no SKU under it', function (): void {
    $order = posSale(staff('cashier'));

    $item = app(PosQueryService::class)->receiptProps($order)['order']['items'][0];

    expect($item)->not->toHaveKey('sku')
        ->and($item['name'])->not->toContain('-');
});

it('writes the dinar with one dot, in the middle', function (): void {
    app()->setLocale('ar');

    // "د.ل." carried a trailing full stop that read as a sentence ending.
    expect(Money::format(1_234_00, 'LYD', isolate: false))->toBe('1,234.000 د.ل');
});

// -- Opening a receipt is looking at it ---------------------------------------

it('does not arm the printer when a receipt is merely opened', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);

    $this->actingAs($cashier)
        ->get(route('admin.pos.receipt', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/pos/Receipt')
            ->where('autoPrint', false)
            ->etc()
        );
});

it('arms it only when the caller asks for paper', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);

    $this->actingAs($cashier)
        ->get(route('admin.pos.receipt', ['order' => $order, 'print' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('autoPrint', true)->etc());
});

// -- The delivery slip --------------------------------------------------------

it('burns a delivery slip on the counter printer without arming the drawer', function (): void {
    $cashier = staff('cashier');
    $order = posSale($cashier);
    $printer = capturePrinter();

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.slip.print', $order), ['image' => slipUpload()])
        ->assertOk()
        ->assertJson(['printed' => true]);

    // Packing paperwork, not a till action: no cash moves at the desk that
    // prints it, so the drawer pulse must never be in the stream.
    expect($printer->lastWrite())->not->toContain("\x1B\x70");
});

it('tells the order page to fall back when no printer is configured', function (): void {
    $order = posSale(staff('cashier'));
    config(['pos.printer.driver' => null]);
    PrinterConfig::forget();

    $this->actingAs(staff('order_manager'))
        ->post(route('admin.orders.slip.print', $order), ['image' => slipUpload()])
        ->assertStatus(409)
        ->assertJson(['printed' => false, 'reason' => 'not_configured']);
});

it('refuses a delivery slip print to staff who cannot see the order', function (): void {
    $order = posSale(staff('cashier'));
    capturePrinter();

    $this->actingAs(customer())
        ->post(route('admin.orders.slip.print', $order), ['image' => slipUpload()])
        ->assertForbidden();
});

it('hands the slip page the same printer contract as the receipt page', function (): void {
    $order = posSale(staff('cashier'));
    capturePrinter();

    $this->actingAs(staff('order_manager'))
        ->get(route('admin.orders.slip', $order))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/orders/DeliverySlip')
            ->where('printer.direct', true)
            ->where('printer.width', (int) PrinterConfig::get('width'))
            ->etc()
        );
});
