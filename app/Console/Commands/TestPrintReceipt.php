<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pos\PrinterConfig;
use App\Services\Pos\Printers\PrinterConnection;
use App\Services\Pos\Printers\PrinterUnavailable;
use App\Services\Pos\ReceiptPrinter;
use GdImage;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Prints a test slip so the counter hardware can be proved without ringing up
 * a sale.
 *
 * The slip is generated here rather than in a browser — the point is to test
 * the wire, the head width, the cut and the drawer, none of which care what the
 * bitmap says. A real receipt still comes from `Receipt.vue`.
 */
final class TestPrintReceipt extends Command
{
    protected $signature = 'pos:test-print
        {--driver= : Override POS_PRINTER_DRIVER for this run (network|device|command)}
        {--host= : Printer IP, with --driver=network}
        {--port= : Printer port, with --driver=network}
        {--device= : Device node, with --driver=device}
        {--command= : Spooler command, with --driver=command}
        {--drawer : Also pulse the cash drawer}';

    protected $description = 'Print a test slip on the counter receipt printer';

    public function handle(): int
    {
        $this->applyOverrides();

        if (! PrinterConfig::isConfigured()) {
            $this->components->error('No printer is configured.');
            $this->explainConfiguration();

            return self::FAILURE;
        }

        $driver = (string) PrinterConfig::get('driver');
        $width = (int) PrinterConfig::get('width');

        $this->components->info("Printing a test slip via `{$driver}` at {$width} dots.");

        try {
            app(ReceiptPrinter::class)->print($this->slip($width), $this->option('drawer'));
        } catch (PrinterUnavailable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->components->error("The test slip could not be built: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info('Sent. Check the paper.');
        $this->line('  <fg=gray>Blank slip?</> the threshold is too low — raise POS_PRINTER_THRESHOLD.');
        $this->line('  <fg=gray>Right edge missing?</> php artisan pos:printer --width=384');

        return self::SUCCESS;
    }

    /**
     * Let the command be pointed at a printer that is not the configured one.
     *
     * Written into `config` rather than the settings table on purpose: config
     * outranks stored settings, so an override takes effect for this run only
     * and cannot leave the shop's real printer changed behind it.
     */
    private function applyOverrides(): void
    {
        foreach (['driver', 'host', 'port', 'device', 'command'] as $key) {
            if ($this->option($key) !== null) {
                config(["pos.printer.{$key}" => $this->option($key)]);
            }
        }

        // The container may hold a connection built before those overrides.
        app()->forgetInstance(PrinterConnection::class);
    }

    private function explainConfiguration(): void
    {
        $this->newLine();
        $this->line('  <fg=gray># USB printer on this machine, through a raw queue</>');
        $this->line('  <options=bold>php artisan pos:printer --queues</>');
        $this->line('  <options=bold>php artisan pos:printer --driver=command --command="lp -d POS80 -o raw"</>');
        $this->newLine();
        $this->line('  <fg=gray># Printer with its own IP</>');
        $this->line('  <options=bold>php artisan pos:printer --driver=network --host=192.168.1.50</>');
    }

    /**
     * A slip that proves the things a receipt cannot: that the full head width
     * is reachable, that mid-greys survive the threshold, and that the cut
     * lands past the last line.
     */
    private function slip(int $width): string
    {
        // Drawn at a third size and scaled up, because GD's built-in fonts top
        // out at 9x15px — unreadable at 576 dots across.
        $scale = 3;
        $canvas = imagecreatetruecolor((int) ($width / $scale), 150);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);

        imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $white);

        $lines = [
            'RECEIPT PRINTER TEST',
            '',
            'Driver : '.PrinterConfig::get('driver'),
            'Width  : '.$width.' dots',
            'Cut    : '.(PrinterConfig::get('cut') ? 'yes' : 'no'),
            'Drawer : '.($this->option('drawer') ? 'pulsed' : 'not pulsed'),
            '',
            'Full line of text to check the',
            'head reaches both edges ......',
        ];

        foreach ($lines as $row => $text) {
            imagestring($canvas, 2, 2, 4 + $row * 12, $text, $black);
        }

        // Edge ticks: if either is missing, the width is wrong.
        imagefilledrectangle($canvas, 0, 120, 8, 128, $black);
        imagefilledrectangle($canvas, imagesx($canvas) - 9, 120, imagesx($canvas) - 1, 128, $black);

        // Grey ramp: how much of it survives tells you where the threshold sits.
        for ($step = 0; $step < 8; $step++) {
            $shade = (int) (255 - $step * 32);
            imagefilledrectangle(
                $canvas,
                (int) ($step * imagesx($canvas) / 8),
                134,
                (int) (($step + 1) * imagesx($canvas) / 8) - 2,
                146,
                imagecolorallocate($canvas, $shade, $shade, $shade),
            );
        }

        return $this->encode($canvas, $scale);
    }

    private function encode(GdImage $canvas, int $scale): string
    {
        $scaled = imagescale($canvas, imagesx($canvas) * $scale, imagesy($canvas) * $scale, IMG_NEAREST_NEIGHBOUR);
        imagedestroy($canvas);

        ob_start();
        imagepng($scaled);
        $png = (string) ob_get_clean();
        imagedestroy($scaled);

        return $png;
    }
}
