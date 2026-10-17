<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\CashierShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockBatch;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Pos\PrinterConfig;
use App\Services\Pos\ReceiptPrinter;
use App\Services\Reports\ReportDateRange;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the register, the receipt and the shift report read.
 *
 * The register is a single-screen app: it loads its whole sellable catalogue
 * once and searches it client-side, because a cashier scanning a barcode
 * cannot wait on a round trip per keystroke.
 */
final class PosQueryService
{
    /**
     * Props for admin/pos/Register.
     *
     * @return array<string, mixed>
     */
    public function registerProps(CashierShift $shift, bool $withCost = false): array
    {
        $shift->loadMissing('warehouse');

        $products = $this->sellableProducts($shift->warehouse_id, $withCost);

        return [
            'shift' => $this->shiftSummary($shift),
            'products' => $products,
            'categories' => $this->categoriesOf($products),
            'brands' => $this->brandsOf($products),
            'paymentMethods' => array_map(
                fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()],
                // The register takes money in the room: cash, or a transfer the
                // customer makes on the spot. COD is meaningless at a counter.
                [PaymentMethod::Cash, PaymentMethod::BankTransfer],
            ),
            'recentSales' => $this->recentSales($shift),
        ];
    }

    /**
     * Props for admin/pos/OpenShift — the warehouses a cashier may sell from.
     *
     * @return array<string, mixed>
     */
    public function openShiftProps(): array
    {
        // What the last drawer at each branch was counted at when it closed.
        // The cash stays in the drawer unless someone takes it out, so that is
        // the float the next shift starts from — opening at 0 over a full
        // drawer books the whole lot as "over" at the next close.
        $lastCounts = CashierShift::query()
            ->where('status', CashierShiftStatus::Closed)
            ->whereNotNull('counted_cash_cents')
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->get(['warehouse_id', 'counted_cash_cents'])
            ->unique('warehouse_id')
            ->pluck('counted_cash_cents', 'warehouse_id');

        return [
            'warehouses' => Warehouse::query()
                ->active()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_default'])
                ->map(fn (Warehouse $w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'code' => $w->code,
                    'is_default' => (bool) $w->is_default,
                    'last_counted' => $lastCounts->has($w->id) ? Money::toDecimal((int) $lastCounts[$w->id]) : null,
                    'last_counted_formatted' => $lastCounts->has($w->id) ? Money::format((int) $lastCounts[$w->id]) : null,
                ]),
        ];
    }

    /**
     * The count-down screen: what the drawer should hold, and what it took in
     * through each channel so the cashier can see why.
     *
     * @return array<string, mixed>
     */
    public function closeShiftProps(CashierShift $shift): array
    {
        return [
            'shift' => $this->shiftSummary($shift),
        ];
    }

    /**
     * Props for admin/pos/Sales — every counter receipt, filterable, with the
     * totals of what is shown. A cashier (no orders.manage) sees only their own.
     *
     * @return array<string, mixed>
     */
    public function salesIndexProps(Request $request): array
    {
        ReportDateRange::validate($request);
        [$from, $to] = ReportDateRange::localBounds($request);
        $user = $request->user();
        $ownOnly = ! $user->can('orders.manage');

        $base = Order::query()
            ->where('placed_via', 'pos')
            ->when($ownOnly, fn ($q) => $q->whereHas('cashierShift', fn ($s) => $s->where('user_id', $user->id)))
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->whereHas('cashierShift', fn ($s) => $s->where('warehouse_id', (int) $request->query('warehouse_id'))))
            ->when($request->filled('cashier_id'), fn ($q) => $q->whereHas('cashierShift', fn ($s) => $s->where('user_id', (int) $request->query('cashier_id'))))
            ->when(in_array($request->query('payment_method'), ['cash', 'bank_transfer'], true), fn ($q) => $q->where('payment_method', (string) $request->query('payment_method')))
            ->when(in_array($request->query('status'), ['completed', 'refunded'], true), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $q->where(fn ($w) => $w->where('number', 'ilike', "%{$term}%")->orWhereRaw("customer->>'phone' ILIKE ?", ["%{$term}%"])->orWhereRaw("customer->>'name' ILIKE ?", ["%{$term}%"]));
            });

        $totals = (clone $base)->selectRaw("COUNT(*) AS sales, COALESCE(SUM(total_cents),0) AS total, COALESCE(SUM(total_cents) FILTER (WHERE payment_method = 'cash'),0) AS cash, COALESCE(SUM(total_cents) FILTER (WHERE payment_method = 'bank_transfer'),0) AS bank, COUNT(*) FILTER (WHERE status = 'refunded') AS returned, COALESCE(SUM(total_cents) FILTER (WHERE status = 'refunded'),0) AS returned_total")->first();

        $sales = (clone $base)
            ->with(['cashierShift:id,user_id,warehouse_id', 'cashierShift.user:id,name', 'cashierShift.warehouse:id,name', 'user:id,name'])
            ->withSum('items as units', 'quantity')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'at' => Dates::dateTime($o->created_at),
                'branch' => $o->cashierShift?->warehouse?->name,
                'cashier' => $o->cashierShift?->user?->name,
                'shift_id' => $o->cashier_shift_id,
                // A linked account, else whatever name was typed, else a cash customer.
                'customer' => $o->user?->name ?? $this->namedCustomer($o),
                'is_member' => $o->user_id !== null,
                'units' => (int) $o->units,
                'payment_label' => $o->payment_method->label(),
                'total_formatted' => Money::format($o->total_cents, $o->currency),
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'discount_formatted' => $o->manual_discount_cents > 0 ? Money::format($o->manual_discount_cents, $o->currency) : null,
            ]);

        return [
            'sales' => $sales,
            'totals' => [
                'sales' => (int) $totals->sales,
                'total_formatted' => Money::format((int) $totals->total),
                'cash_formatted' => Money::format((int) $totals->cash),
                'bank_formatted' => Money::format((int) $totals->bank),
                'returned' => (int) $totals->returned,
                'returned_formatted' => Money::format((int) $totals->returned_total),
                'net_formatted' => Money::format((int) $totals->total - (int) $totals->returned_total),
            ],
            'filters' => (object) $request->only(['q', 'from', 'to', 'warehouse_id', 'cashier_id', 'payment_method', 'status']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name'])->map(fn (Warehouse $w) => ['value' => $w->id, 'label' => $w->name]),
            'cashiers' => $ownOnly ? [] : CashierShift::query()->with('user:id,name')->get(['user_id'])->unique('user_id')
                ->filter(fn (CashierShift $s) => $s->user !== null)
                ->map(fn (CashierShift $s) => ['value' => $s->user_id, 'label' => $s->user->name])->values(),
            'ownOnly' => $ownOnly,
        ];
    }

    /**
     * Props for admin/pos/Receipt — the 80mm slip handed to the customer.
     *
     * @return array<string, mixed>
     */
    public function receiptProps(Order $order): array
    {
        $order->load(['items', 'payments', 'cashierShift.user:id,name', 'cashierShift.warehouse:id,name']);

        $paidCents = (int) $order->payments->where('status', 'confirmed')->sum('amount_cents');

        // Amounts here drop the LTR isolate. Every one of them stands alone in
        // its own cell, so there is no neighbouring text to protect them from,
        // and forcing left-to-right inside an Arabic slip pushes the number to
        // the left of د.ل — the reverse of how a price is read on paper.

        return [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'created_at' => Dates::dayDateTime($order->created_at),
                'payment_method' => $order->payment_method->label(),
                'cashier' => $order->cashierShift?->user?->name,
                'customer_name' => $this->namedCustomer($order),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => Money::format($item->unit_price_cents, $order->currency, isolate: false),
                    // Before discount: the slip subtracts the discount once, on its own line.
                    'line_total_formatted' => Money::format($item->unit_price_cents * $item->quantity, $order->currency, isolate: false),
                ]),
                'subtotal_formatted' => Money::format($order->subtotal_cents, $order->currency, isolate: false),
                'discount_cents' => $order->discount_cents,
                'discount_formatted' => Money::format($order->discount_cents, $order->currency, isolate: false),
                'coupon_code' => $order->coupon_code,
                'total_formatted' => Money::format($order->total_cents, $order->currency, isolate: false),
                'paid_formatted' => Money::format($paidCents, $order->currency, isolate: false),
            ],
            'store' => [
                'name' => Setting::get('store_name') ?: config('app.name'),
                'details' => Setting::get('invoice_details'),
                // Already converted to black-on-white and inlined; see ReceiptLogo.
                'logo' => Setting::get('receipt_logo'),
            ],
            // Told to the page rather than discovered by it: with no printer
            // wired up the slip still has to reach the customer, so the page
            // opens the browser dialog instead of silently doing nothing.
            'printer' => [
                'direct' => ReceiptPrinter::isConfigured(),
                'width' => (int) PrinterConfig::get('width'),
            ],
        ];
    }

    /**
     * The customer's name, or null when there isn't really one.
     *
     * A counter sale has no account, so CompleteSale stamps the "Walk-in
     * customer" placeholder to give the order a shape. That is bookkeeping, not
     * something to hand back to the person who just bought a cable — a line
     * telling them they are anonymous is noise on an 80mm slip.
     *
     * Matched across every locale: the sale may have been rung up in Arabic and
     * reprinted in English, and the placeholder is stored translated.
     */
    public function namedCustomer(Order $order): ?string
    {
        $name = $order->customer['name'] ?? null;

        if ($name === null) {
            return null;
        }

        $placeholders = array_map(
            fn (string $locale) => __('Walk-in customer', [], $locale),
            (array) config('store.locales', ['en']),
        );

        return in_array($name, $placeholders, true) ? null : $name;
    }

    /**
     * Props for admin/pos/Shifts — the shift log, newest first.
     *
     * @return array<string, mixed>
     */
    public function shiftsProps(): array
    {
        $shifts = CashierShift::query()
            ->with(['user:id,name', 'warehouse:id,name'])
            ->withCount('orders')
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        $shifts->through(fn (CashierShift $shift) => [
            'id' => $shift->id,
            'cashier' => $shift->user?->name,
            'warehouse' => $shift->warehouse?->name,
            'status' => $shift->status->value,
            'status_label' => $shift->status->label(),
            'orders_count' => $shift->orders_count,
            'opened_at' => Dates::dateTime($shift->opened_at),
            'closed_at' => Dates::dateTime($shift->closed_at),
            'opening_float_formatted' => Money::format($shift->opening_float_cents),
            // Open shifts have no stored figures yet, so show the live one.
            'expected_formatted' => Money::format($shift->expected_cash_cents ?? $shift->expectedCashCents()),
            'counted_formatted' => $shift->counted_cash_cents === null ? null : Money::format($shift->counted_cash_cents),
            'difference_cents' => $shift->difference_cents,
            'difference_formatted' => $shift->difference_cents === null
                ? null
                : Money::format(abs($shift->difference_cents)),
        ]);

        return ['shifts' => $shifts];
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftSummary(CashierShift $shift): array
    {
        $shift->loadMissing(['user:id,name', 'warehouse:id,name']);

        $takings = $shift->takingsByMethod();
        $expected = $shift->expected_cash_cents ?? $shift->expectedCashCents();

        return [
            'id' => $shift->id,
            'status' => $shift->status->value,
            'cashier' => $shift->user?->name,
            'warehouse' => ['id' => $shift->warehouse_id, 'name' => $shift->warehouse?->name],
            'opened_at' => Dates::dateTime($shift->opened_at),
            'opening_float_cents' => $shift->opening_float_cents,
            'opening_float_formatted' => Money::format($shift->opening_float_cents),
            'orders_count' => $shift->orders()->count(),
            'takings' => collect($takings)
                ->map(fn (int $cents, string $method) => [
                    'method' => $method,
                    'label' => PaymentMethod::tryFrom($method)?->label() ?? $method,
                    'amount_formatted' => Money::format($cents),
                ])
                ->values(),
            'cash_refunded_formatted' => Money::format($shift->cashRefundedCents()),
            'has_cash_refunds' => $shift->cashRefundedCents() > 0,
            'expected_cash_cents' => $expected,
            'expected_cash_formatted' => Money::format($expected),
        ];
    }

    /**
     * The catalogue as the register sees it: active, purchasable, with the
     * on-hand quantity for this cashier's warehouse so they can tell a customer
     * "we have two left" without leaving the screen.
     *
     * With $withCost, each item also carries the highest price paid for the units
     * still in this warehouse (its FIFO batches) — never sent otherwise.
     */
    private function sellableProducts(int $warehouseId, bool $withCost = false): Collection
    {
        $stock = StockLevel::query()
            ->where('warehouse_id', $warehouseId)
            ->get(['product_id', 'product_variant_id', 'quantity', 'reserved']);

        $available = $stock->mapWithKeys(fn (StockLevel $level) => [
            $level->product_id.':'.($level->product_variant_id ?? 0) => (int) $level->quantity,
        ]);

        $maxCost = ! $withCost ? collect() : StockBatch::query()
            ->join('stock_levels', 'stock_levels.id', '=', 'stock_batches.stock_level_id')
            ->where('stock_levels.warehouse_id', $warehouseId)
            ->where('stock_batches.quantity_remaining', '>', 0)
            ->groupBy('stock_levels.product_id', 'stock_levels.product_variant_id')
            ->selectRaw('stock_levels.product_id, stock_levels.product_variant_id, MAX(stock_batches.unit_cost_cents) AS cost')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->product_id.':'.($row->product_variant_id ?? 0) => (int) $row->cost]);
        $cost = fn (string $key): array => isset($maxCost[$key]) ? ['max_cost_formatted' => Money::format($maxCost[$key])] : [];
        // The product card stands for all its variants, so it carries the highest of them.
        $productMaxCost = $maxCost->mapToGroups(fn (int $value, string $key) => [strtok($key, ':') => $value])->map->max();

        return Product::query()
            ->where('status', ProductStatus::Active)
            ->with([
                'category:id,name,name_ar',
                'brand:id,name,name_ar',
                'variants:id,product_id,name,sku,barcode,price_cents,sale_price_cents,image_path,is_active',
                // Primary first, then by position: the tile shows exactly one,
                // and firstWhere() over an unordered set picked at random.
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('position'),
            ])
            ->orderBy('name')
            ->get(['id', 'category_id', 'brand_id', 'name', 'name_ar', 'sku', 'barcode', 'price_cents', 'sale_price_cents', 'sale_starts_at', 'sale_ends_at', 'has_variants'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'name_ar' => $p->name_ar,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'has_variants' => (bool) $p->has_variants,
                'category_id' => $p->category_id,
                'category' => $p->category === null
                    ? null
                    : ['id' => $p->category->id, 'name' => $p->category->name, 'name_ar' => $p->category->name_ar],
                'brand_id' => $p->brand_id,
                'brand' => $p->brand === null
                    ? null
                    : ['id' => $p->brand->id, 'name' => $p->brand->name, 'name_ar' => $p->brand->name_ar],
                'price' => Money::toDecimal($p->currentPriceCents()),
                'price_formatted' => Money::format($p->currentPriceCents()),
                'available' => $available[$p->id.':0'] ?? 0,
                ...(isset($productMaxCost[$p->id]) ? ['max_cost_formatted' => Money::format($productMaxCost[$p->id])] : []),
                'image' => $this->url($p->images->first()?->path),
                'variants' => $p->variants
                    ->where('is_active', true)
                    ->map(fn (ProductVariant $v) => [
                        'id' => $v->id,
                        'name' => $v->name,
                        'sku' => $v->sku,
                        'barcode' => $v->barcode,
                        'price' => Money::toDecimal($v->setRelation('product', $p)->currentPriceCents()),
                        'price_formatted' => Money::format($v->currentPriceCents()),
                        'available' => $available[$p->id.':'.$v->id] ?? 0,
                        ...$cost($p->id.':'.$v->id),
                        // A variant without its own shot falls back to the
                        // product's: a tile with no picture is worse than one
                        // showing the right product in the wrong colour.
                        'image' => $this->url($v->image_path ?? $p->images->first()?->path),
                    ])
                    ->values(),
            ])
            ->values();
    }

    /**
     * The brands on the shelf, for the second filter. Derived from the grid for
     * the same reason the categories are: a brand the cashier cannot sell from
     * this warehouse has no business being offered as a filter.
     *
     * @param  Collection<int, array<string, mixed>>  $products
     */
    private function brandsOf(Collection $products): Collection
    {
        return $products
            ->filter(fn (array $p) => $p['brand'] !== null)
            ->groupBy(fn (array $p) => $p['brand']['id'])
            ->map(fn (Collection $group) => [
                ...$group->first()['brand'],
                'products_count' => $group->count(),
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * The categories worth showing as filter chips: only those the cashier can
     * actually sell from this warehouse right now. Deriving them from the grid
     * rather than querying the category tree means a chip can never lead to an
     * empty screen, and the nesting is flattened away — a register is not the
     * place to navigate a hierarchy.
     *
     * @param  Collection<int, array<string, mixed>>  $products
     */
    private function categoriesOf(Collection $products): Collection
    {
        return $products
            ->filter(fn (array $p) => $p['category'] !== null)
            ->groupBy(fn (array $p) => $p['category']['id'])
            ->map(fn (Collection $group) => [
                ...$group->first()['category'],
                'products_count' => $group->count(),
            ])
            ->sortBy('name')
            ->values();
    }

    private function url(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    /** The last handful of sales on this drawer, for a quick reprint. */
    private function recentSales(CashierShift $shift): Collection
    {
        return $shift->orders()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'number', 'total_cents', 'currency', 'created_at'])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                // The till's wall clock, not UTC.
                'created_at' => Dates::time($order->created_at),
            ]);
    }
}
