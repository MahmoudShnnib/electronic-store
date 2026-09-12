<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

/**
 * Swallows the stream and records its size.
 *
 * What a shop with no printer configured gets, and what the test suite asserts
 * against — the ESC/POS is still built in full, it just goes nowhere.
 */
final class NullPrinter implements PrinterConnection
{
    /** @var list<string> */
    private array $written = [];

    public function write(string $bytes): void
    {
        $this->written[] = $bytes;
    }

    /** @return list<string> */
    public function writes(): array
    {
        return $this->written;
    }

    public function lastWrite(): ?string
    {
        return $this->written === [] ? null : $this->written[array_key_last($this->written)];
    }
}
