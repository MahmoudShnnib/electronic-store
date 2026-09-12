<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

/** Somewhere a finished ESC/POS stream can be handed to a print head. */
interface PrinterConnection
{
    /**
     * @throws PrinterUnavailable when the stream could not be delivered.
     */
    public function write(string $bytes): void;
}
