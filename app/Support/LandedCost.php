<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

/**
 * What received purchase-order units really cost: each line's price with the PO's
 * shipping and discount spread over it, in whole cents that add up to the PO total
 * exactly.
 *
 * Rounding each unit on its own lost money — 1,000 cables at 0.50 with 4.00
 * shipping booked 500.00 and dropped the shipping. Here the PO total is split
 * across lines first (largest remainder), each line's share across its ordered
 * units, and a receipt books the difference between what was received before and
 * after it. Receiving every unit, in any number of receipts, books the PO total to
 * the cent.
 */
final class LandedCost
{
    /**
     * Each line's share of the PO total, keyed by item id. Lines at 0 carry no
     * price (they fall back to the product cost) and so take no share.
     *
     * @return array<int, int>
     */
    public static function lineTotals(PurchaseOrder $po): array
    {
        $items = $po->items->sortBy('id');
        $weights = $items->mapWithKeys(fn (PurchaseOrderItem $i) => [$i->id => max(0, $i->unit_cost_cents * $i->quantity_ordered)])->all();
        $sum = array_sum($weights);

        if ($sum <= 0) {
            return array_map(fn () => 0, $weights);
        }

        $total = max(0, $po->total_cents);
        $shares = [];
        $remainders = [];

        foreach ($weights as $id => $weight) {
            $shares[$id] = intdiv($weight * $total, $sum);
            $remainders[$id] = ($weight * $total) % $sum;
        }

        // The cents lost to rounding go one each to the lines that lost the most.
        arsort($remainders);
        $left = $total - array_sum($shares);

        foreach (array_keys($remainders) as $id) {
            if ($left <= 0) {
                break;
            }
            if ($weights[$id] > 0) {
                $shares[$id]++;
                $left--;
            }
        }

        return $shares;
    }

    /**
     * The cost slices for receiving $quantity more units of a line whose share of
     * the PO total is $lineTotal, with $alreadyReceived units booked before. At most
     * two slices: the cents that don't divide evenly make some units 1 cent dearer.
     *
     * @return list<array{cost: int, qty: int, received_at: string}>
     */
    public static function receiptLayers(int $lineTotal, int $quantityOrdered, int $alreadyReceived, int $quantity): array
    {
        $cost = self::bookedFor($lineTotal, $quantityOrdered, $alreadyReceived + $quantity)
            - self::bookedFor($lineTotal, $quantityOrdered, $alreadyReceived);

        $base = intdiv($cost, $quantity);
        $dearer = $cost - $base * $quantity;
        $at = CostLayers::stamp(now());

        return array_values(array_filter([
            ['cost' => $base + 1, 'qty' => $dearer, 'received_at' => $at],
            ['cost' => $base, 'qty' => $quantity - $dearer, 'received_at' => $at],
        ], fn (array $layer) => $layer['qty'] > 0));
    }

    /** The part of a line's share booked once $units of it have arrived; all of it once every unit has. */
    private static function bookedFor(int $lineTotal, int $quantityOrdered, int $units): int
    {
        return $units >= $quantityOrdered ? $lineTotal : intdiv($lineTotal * $units, $quantityOrdered);
    }
}
