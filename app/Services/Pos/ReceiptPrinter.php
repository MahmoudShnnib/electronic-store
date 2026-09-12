<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Services\Pos\Printers\CupsDriverPrinter;
use App\Services\Pos\Printers\EscposBridge;
use App\Services\Pos\Printers\PrinterConnection;
use App\Services\Pos\Printers\PrinterUnavailable;
use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\Printer as EscposPrinter;
use RuntimeException;

/**
 * Turns the browser's picture of a receipt into a printed, cut slip.
 *
 * The whole receipt is one bitmap by design. Composing the slip out of ESC/POS
 * text runs would mean picking a printer code page, and there is no code page
 * that renders joined Arabic, Latin product names and an LTR-isolated dinar
 * amount on the same line. Rasterising in the browser sidesteps all of it and
 * has the useful property that the slip cannot drift from the receipt page —
 * they are the same markup.
 */
final class ReceiptPrinter
{
    public function __construct(private readonly PrinterConnection $connection) {}

    /** Whether a transport is configured at all; false means fall back to the browser. */
    public static function isConfigured(): bool
    {
        return PrinterConfig::isConfigured();
    }

    /**
     * @param  string  $image  Raw bytes of the rasterised receipt.
     * @param  bool  $openDrawer  Whether this sale took cash.
     *
     * @throws PrinterUnavailable
     */
    public function print(string $image, bool $openDrawer = false): void
    {
        // The vendor driver builds its own ESC/POS, so none of ours applies.
        if (PrinterConfig::get('driver') === 'cups') {
            (new CupsDriverPrinter((string) PrinterConfig::get('queue'), (float) PrinterConfig::get('timeout')))
                ->print(
                    $image,
                    $openDrawer && (bool) PrinterConfig::get('cash_drawer'),
                    (bool) PrinterConfig::get('cut'),
                    (int) PrinterConfig::get('density'),
                );

            return;
        }

        if (PrinterConfig::get('raster_mode') === 'column') {
            $this->printColumnFormat($image, $openDrawer);

            return;
        }

        // Delivered a few kilobytes at a time, each write completing before the
        // next begins. Counter-top controllers hold far less than a slip in RAM
        // and do not reliably apply flow control: hand one a whole 80KB receipt
        // and it loses command sync partway, then prints the rest of the raster
        // as GB18030 text. This is the difference between a receipt and a foot
        // of gibberish.
        foreach ($this->document($image, $openDrawer)->chunks((int) PrinterConfig::get('chunk_bytes')) as $chunk) {
            $this->connection->write($chunk);
        }
    }

    /** The finished ESC/POS stream, exposed separately so it can be asserted on. */
    public function build(string $image, bool $openDrawer = false): string
    {
        return $this->document($image, $openDrawer)->bytes();
    }

    private function document(string $image, bool $openDrawer): EscPosDocument
    {
        $raster = Raster::fromImage(
            $image,
            (int) PrinterConfig::get('width'),
            (int) PrinterConfig::get('threshold'),
        );

        $document = (new EscPosDocument)
            ->initialise()
            ->image($raster)
            ->feed((int) PrinterConfig::get('feed'));

        // Kicked after the slip rather than before it: the drawer opening is
        // the cashier's cue that the sale is finished, and a drawer that pops
        // while the head is still printing invites a hand into the cutter.
        if ($openDrawer && PrinterConfig::get('cash_drawer')) {
            $document->kickDrawer((int) PrinterConfig::get('cash_drawer_pin'));
        }

        if (PrinterConfig::get('cut')) {
            $document->cut();
        }

        return $document;
    }

    /**
     * The `ESC *` path, built by `mike42/escpos-php`.
     *
     * Column format sends one short strip per line with a feed after it, so the
     * head prints as the data arrives instead of being asked to hold a whole
     * slip. On firmware that mishandles a large `GS v 0` this is what makes a
     * tall receipt come out as a receipt. It is slower and the library marks it
     * experimental, which is why it is not the default.
     */
    private function printColumnFormat(string $image, bool $openDrawer): void
    {
        $gd = @imagecreatefromstring($image);

        if ($gd === false) {
            throw new RuntimeException('The receipt image could not be decoded.');
        }

        $connector = new EscposBridge($this->connection, (int) PrinterConfig::get('chunk_bytes'));
        $printer = new EscposPrinter($connector);

        try {
            $escposImage = new GdEscposImage;
            $escposImage->readImageFromGdResource($this->fitToHead($gd));

            $printer->bitImageColumnFormat($escposImage);
            $printer->feed(max(1, (int) PrinterConfig::get('feed')));

            if ($openDrawer && PrinterConfig::get('cash_drawer')) {
                $printer->pulse((int) PrinterConfig::get('cash_drawer_pin'));
            }

            if (PrinterConfig::get('cut')) {
                $printer->cut(EscposPrinter::CUT_PARTIAL);
            }
        } finally {
            // Flushes whatever is still buffered in the bridge.
            $printer->close();
            imagedestroy($gd);
        }
    }

    /** Scale to the head's width and drop alpha against white, as Raster does. */
    private function fitToHead(\GdImage $source): \GdImage
    {
        $dotWidth = (int) PrinterConfig::get('width');
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > $dotWidth) {
            $height = max(1, (int) round($height * $dotWidth / $width));
            $width = $dotWidth;
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $canvas;
    }
}
