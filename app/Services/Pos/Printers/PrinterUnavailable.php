<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

use RuntimeException;

/**
 * The printer could not be reached.
 *
 * Always recoverable from the cashier's side: the register catches this and
 * falls back to the browser print dialog, because a sale that is already rung
 * up must never be blocked by a cable someone kicked out.
 */
final class PrinterUnavailable extends RuntimeException {}
