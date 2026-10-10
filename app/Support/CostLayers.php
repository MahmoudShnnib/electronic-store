<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Cost slices of a set of units, oldest first: [{cost, qty, received_at}, ...].
 * An order line keeps the slices it took from stock, so giving the units back
 * puts them back at exactly those costs.
 */
final class CostLayers
{
    /** A slice's age as stored: UTC, to the second (the batches' precision). */
    public static function stamp(DateTimeInterface $at): string
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }

    /** @param  list<array{cost: int, qty: int, received_at: string}>  $layers */
    public static function total(array $layers): int
    {
        return array_sum(array_map(fn (array $l): int => $l['cost'] * $l['qty'], $layers));
    }

    /** @param  list<array{cost: int, qty: int, received_at: string}>  $layers */
    public static function units(array $layers): int
    {
        return array_sum(array_column($layers, 'qty'));
    }

    /**
     * The first $qty units of $layers, and the slices left after them.
     *
     * @param  list<array{cost: int, qty: int, received_at: string}>  $layers
     * @return array{0: list<array{cost: int, qty: int, received_at: string}>, 1: list<array{cost: int, qty: int, received_at: string}>}
     */
    public static function split(array $layers, int $qty): array
    {
        $taken = [];
        $rest = [];

        foreach ($layers as $layer) {
            $take = min($layer['qty'], max(0, $qty));
            $qty -= $take;

            if ($take > 0) {
                $taken[] = [...$layer, 'qty' => $take];
            }
            if ($layer['qty'] > $take) {
                $rest[] = [...$layer, 'qty' => $layer['qty'] - $take];
            }
        }

        return [$taken, $rest];
    }
}
