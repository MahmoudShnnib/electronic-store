<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

use Mike42\Escpos\PrintConnectors\PrintConnector;

/**
 * Lets `mike42/escpos-php` generate the commands while we keep control of how
 * they reach the printer.
 *
 * The library's own `CupsPrintConnector` buffers a whole receipt and hands it
 * to `lp` as a single job, which is precisely what a counter-top controller
 * cannot swallow: it overruns its buffer, loses command sync, and prints the
 * rest of the raster as GB18030 text. The library knows — `bitImageColumnFormat`
 * ships with a commented-out `sleep(0.1)` and the note "Reduces the amount of
 * trouble that a TM-U220 has keeping up with large images".
 *
 * So we take its command stream and deliver it in small pieces instead, each
 * write completing before the next begins. Every `write()` the library makes is
 * a whole command, so grouping them can never split one down the middle.
 */
final class EscposBridge implements PrintConnector
{
    private string $buffer = '';

    /** @var list<string> */
    private array $delivered = [];

    public function __construct(
        private readonly PrinterConnection $target,
        private readonly int $chunkBytes = 8192,
    ) {}

    public function __destruct()
    {
        // The library calls finalize() through Printer::close(); this is only a
        // backstop so an exception mid-receipt cannot strand bytes here.
        $this->buffer = '';
    }

    public function write(string $data): void
    {
        if ($this->chunkBytes > 0 && $this->buffer !== '' && strlen($this->buffer) + strlen($data) > $this->chunkBytes) {
            $this->flush();
        }

        $this->buffer .= $data;
    }

    public function finalize(): void
    {
        $this->flush();
    }

    /** This printer is written to, never interrogated. */
    public function read(int $len): bool|string
    {
        return false;
    }

    /** @return list<string> What was actually handed over, for assertions. */
    public function deliveries(): array
    {
        return $this->delivered;
    }

    private function flush(): void
    {
        if ($this->buffer === '') {
            return;
        }

        $this->delivered[] = $this->buffer;
        $this->target->write($this->buffer);
        $this->buffer = '';
    }
}
