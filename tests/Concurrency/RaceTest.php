<?php

declare(strict_types=1);

/*
 * Finance audit F4 (and the truly simultaneous half of F5): real parallel requests.
 *
 * Each racer is a forked process with its own database connection, released at the same
 * instant, sending the request through the app's HTTP kernel. Data is committed (this
 * directory uses DatabaseTruncation, not a wrapping transaction) so the racers can see it.
 */

use App\Actions\Inventory\RecordMovement;
use App\Enums\MovementType;
use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../Feature/FinanceAudit/FinanceAuditSetup.php';

/**
 * Runs each closure in its own process, all starting at the same moment. Returns what each
 * one reported: ['status' => HTTP status, 'errors' => validation error keys].
 *
 * @param  list<Closure(): TestResponse>  $racers
 */
function finRace(array $racers): array
{
    $start = microtime(true) + 0.5;
    $files = [];
    $pids = [];
    DB::disconnect(); // the children must not share the parent's socket

    foreach ($racers as $i => $racer) {
        $files[$i] = tempnam(sys_get_temp_dir(), 'race');
        $pid = pcntl_fork();
        if ($pid === 0) {
            time_sleep_until($start);
            try {
                $res = $racer();
                $out = ['status' => $res->getStatusCode(), 'errors' => array_keys(session('errors')?->getBag('default')->messages() ?? [])];
            } catch (Throwable $e) {
                $out = ['status' => 500, 'errors' => [get_class($e).': '.$e->getMessage()]];
            }
            file_put_contents($files[$i], json_encode($out));
            posix_kill(posix_getpid(), SIGKILL); // skip PHPUnit's shutdown handlers
        }
        $pids[] = $pid;
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
    DB::reconnect();

    return array_map(fn ($f) => tap(json_decode((string) file_get_contents($f), true), fn () => unlink($f)), $files);
}

beforeEach(fn () => finSetUp($this));

it('F4: two customers buying the last unit at the same instant — exactly one gets it', function (): void {
    $alice = customer(['email' => 'race-alice@example.com']);
    $bob = customer(['email' => 'race-bob@example.com']);
    foreach ([$alice, $bob] as $who) {
        $this->actingAs($who)->post('/cart/items', ['product_id' => $this->mug->id, 'quantity' => 1])->assertSessionHasNoErrors();
    }

    $results = finRace([
        fn () => $this->actingAs($alice)->post('/checkout', finCheckoutPayload($alice)),
        fn () => $this->actingAs($bob)->post('/checkout', finCheckoutPayload($bob)),
    ]);

    expect(collect($results)->pluck('status')->all())->each->toBeLessThan(500)
        ->and(collect($results)->filter(fn ($r) => $r['errors'] === [])->count())->toBe(1)
        ->and(collect($results)->filter(fn ($r) => in_array('cart', $r['errors'], true))->count())->toBe(1)
        ->and(Order::count())->toBe(1)
        ->and((int) DB::table('stock_levels')->where('product_id', $this->mug->id)->value('quantity'))->toBe(0)
        ->and((int) DB::table('stock_levels')->where('product_id', $this->mug->id)->value('reserved'))->toBe(1)
        ->and(auditFailures())->toBe([]);
});

it('F5: the same checkout sent twice at the same instant places one order', function (): void {
    $this->actingAs($this->buyer)->post('/cart/items', ['product_id' => $this->phone->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $payload = finCheckoutPayload($this->buyer, ['idempotency_key' => 'race-key']);

    $results = finRace([
        fn () => $this->actingAs($this->buyer)->post('/checkout', $payload),
        fn () => $this->actingAs($this->buyer)->post('/checkout', $payload),
    ]);

    expect(collect($results)->pluck('status')->all())->each->toBeLessThan(500)
        ->and(Order::count())->toBe(1)
        ->and((int) DB::table('stock_levels')->where('product_id', $this->phone->id)->value('quantity'))->toBe(8)
        ->and(auditFailures())->toBe([]);
});

it('F5: "record payment" clicked twice at the same instant charges once', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);

    $results = finRace([
        fn () => $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000]),
        fn () => $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/payments", ['amount' => 1000]),
    ]);

    expect(collect($results)->pluck('status')->all())->each->toBeLessThan(500)
        ->and(DB::table('payments')->count())->toBe(1)
        ->and(DB::table('treasury_transactions')->count())->toBe(1)
        ->and(auditFailures())->toBe([]);
});

it('F5: the return form submitted twice at the same instant refunds and restocks once', function (): void {
    $order = finBuy($this, $this->buyer, [[$this->phone, 1]]);
    finComplete($this, $order);
    $item = $order->items()->firstOrFail();
    $send = fn () => $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/issue", [
        'reason' => 'Returned', 'amount' => 1000, 'method' => 'cash', 'restock' => true, 'items' => [$item->id => 1],
    ]);

    $results = finRace([$send, $send]);

    expect(collect($results)->pluck('status')->all())->each->toBeLessThan(500)
        ->and(DB::table('refunds')->count())->toBe(1)
        ->and((int) DB::table('stock_levels')->where('product_id', $this->phone->id)->value('quantity'))->toBe(10)
        ->and(auditFailures())->toBe([]);
});

it('first stock for a product in a new warehouse, added twice at the same instant, lands on one row', function (): void {
    $north = Warehouse::factory()->create(); // no stock rows yet
    // Holding the transaction open after creating the row means the other racer has
    // already found no row and is waiting on the unique index when this one commits.
    $addOne = function () use ($north): TestResponse {
        DB::transaction(function () use ($north): void {
            app(RecordMovement::class)->handle(MovementType::ManualIncrease, $north, $this->mug, quantityChange: 1, reason: 'Opening stock');
            usleep(300_000);
        });

        return TestResponse::fromBaseResponse(response()->noContent());
    };

    $results = finRace([$addOne, $addOne]);

    expect(collect($results)->pluck('errors')->flatten()->all())->toBe([])
        ->and(DB::table('stock_levels')->where('warehouse_id', $north->id)->pluck('quantity')->all())->toBe([2])
        ->and(DB::table('inventory_movements')->where('warehouse_id', $north->id)->count())->toBe(2)
        ->and(auditFailures())->toBe([]);
});
