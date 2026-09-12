<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Prints the receipt through the manufacturer's own CUPS driver.
 *
 * Everything else in this namespace hands the printer ESC/POS we generated.
 * This one does the opposite: it gives the vendor queue a plain image and lets
 * `rastertosnailep-xprinter` produce the ESC/POS itself.
 *
 * That matters because the failure this exists to solve is a hardware one. A
 * counter-top controller has a small buffer and poor flow control, and a tall
 * receipt written straight at it overruns the thing: it loses command sync and
 * prints the remaining raster as GB18030 text. Pacing it ourselves works but
 * costs a CUPS job per chunk — 88 seconds for one slip, which is no use at a
 * till. The vendor filter knows its own printer's limits and streams inside a
 * single job.
 *
 * Cut and drawer survive the move: the XP-80 PPD exposes both as job options,
 * so nothing is given up by not writing the bytes by hand.
 */
final class CupsDriverPrinter
{
    /** 576 dots across 72mm of paper — the head's true resolution. */
    private const DPI = 203.2;

    /** 72mm in PostScript points, the PPD's `MaxMediaWidth`. */
    private const WIDTH_POINTS = 204;

    public function __construct(
        private readonly string $queue,
        private readonly float $timeout = 20,
    ) {}

    /**
     * @param  string  $png  The rasterised receipt.
     * @param  bool  $openDrawer  Whether this sale took cash.
     *
     * @throws PrinterUnavailable
     */
    public function print(string $png, bool $openDrawer, bool $cut = true, int $density = 5): void
    {
        $file = $this->spool($png, $height);

        try {
            $process = new Process(
                $this->arguments($file, $height, $openDrawer, $cut, $density),
                timeout: $this->timeout,
            );

            $process->run();

            if (! $process->isSuccessful()) {
                throw new PrinterUnavailable(sprintf(
                    'The printer queue `%s` rejected the receipt (exit %d): %s',
                    $this->queue,
                    (int) $process->getExitCode(),
                    trim($process->getErrorOutput()) ?: trim($process->getOutput()),
                ));
            }
        } catch (ExceptionInterface $e) {
            throw new PrinterUnavailable("Could not run `lp` for queue `{$this->queue}`: {$e->getMessage()}.", 0, $e);
        } finally {
            @unlink($file);
        }
    }

    /**
     * The `lp` invocation for one receipt.
     *
     * Separate from running it so the page geometry and the cut/drawer options
     * can be asserted on without a printer attached.
     *
     * @return list<string>
     */
    public function arguments(string $file, int $height, bool $openDrawer, bool $cut = true, int $density = 5): array
    {
        return [
            'lp', '-d', $this->queue,
            // The page is cut to the receipt's own length. Left at the PPD's
            // fixed 210mm, every slip would waste most of a foot of roll.
            '-o', 'PageSize=Custom.'.self::WIDTH_POINTS.'x'.$this->points($height),
            '-o', 'PrintDensity='.max(1, min(8, $density)),
            '-o', 'PageCutType='.($cut ? '1PartialCutPage' : '0NoCutPage'),
            // After printing, never before: a drawer that opens while the head
            // is still running invites a hand into the cutter.
            '-o', 'CashDrawer1Setting='.($openDrawer ? '2CashDrawer1AfterPrinting' : '0NotCashDrawer1'),
            // These four matter on classic CUPS and are **ignored on macOS**,
            // where Apple's `cgimagetopdf` handles the image and reports its own
            // 96dpi regardless. Kept for Linux; the geometry that actually does
            // the work everywhere is `PageSize` above, which the filter honours
            // exactly (verified: a 576x200 image lands as a 576x200 raster with
            // no padding). Do not add margin options here expecting to shrink
            // the blank strip at the top of a slip — that is not where it comes
            // from; see docs/agents/pos-printing.md.
            '-o', 'ppi='.(int) round(self::DPI),
            '-o', 'position=top-left',
            '-o', 'page-top=0',
            '-o', 'page-bottom=0',
            '-o', 'page-left=0',
            '-o', 'page-right=0',
            $file,
        ];
    }

    /** Writes the slip somewhere `lp` can read it, and reports its height. */
    private function spool(string $png, ?int &$height): string
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            throw new RuntimeException('The receipt image could not be decoded.');
        }

        $height = imagesy($image);
        imagedestroy($image);

        $file = tempnam(sys_get_temp_dir(), 'receipt-').'.png';

        if (@file_put_contents($file, $png) === false) {
            throw new PrinterUnavailable("Could not write the receipt to {$file}.");
        }

        return $file;
    }

    /** Dot rows to PostScript points. */
    private function points(int $rows): int
    {
        return max(1, (int) ceil($rows * 72 / self::DPI));
    }
}
