<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

/**
 * A device node written to directly: `/dev/usb/lp0` on Linux, a shared
 * `\\PC\POS80` queue on Windows.
 *
 * The fastest path when the printer hangs off the same box as PHP, and the
 * only one that does not need a spooler installed — but it needs the web user
 * to own (or be in the group of) the node, which on Linux usually means adding
 * it to `lp`.
 */
final class DevicePrinter implements PrinterConnection
{
    public function __construct(private readonly string $device) {}

    public function write(string $bytes): void
    {
        $handle = @fopen($this->device, 'wb');

        if ($handle === false) {
            throw new PrinterUnavailable("Could not open the printer device {$this->device}. Check that it exists and that the web user may write to it.");
        }

        try {
            if (@fwrite($handle, $bytes) === false) {
                throw new PrinterUnavailable("Writing the receipt to {$this->device} failed.");
            }
        } finally {
            fclose($handle);
        }
    }
}
