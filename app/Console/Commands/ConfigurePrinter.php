<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pos\PrinterConfig;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Reads and writes the counter printer's wiring without touching `.env`.
 *
 * A receipt printer is the one part of this system bolted to a physical desk:
 * it gets unplugged, moved to another till, swapped for a LAN model. None of
 * that should need a deploy, so the settings live in the database and this is
 * the way in.
 */
final class ConfigurePrinter extends Command
{
    protected $signature = 'pos:printer
        {--driver= : cups|network|device|command}
        {--queue= : CUPS queue name, with --driver=cups}
        {--density= : Head burn strength 1-8, with --driver=cups}
        {--host= : Printer IP, with --driver=network}
        {--port= : Printer port, with --driver=network}
        {--device= : Device node, with --driver=device}
        {--command= : Spooler command, with --driver=command}
        {--width= : Printable dots across the head (576 for 80mm, 384 for 58mm)}
        {--raster= : raster (GS v 0, default) or column (ESC *, for firmware that garbles tall images)}
        {--chunk= : Largest single write handed to the printer, in bytes}
        {--queues : List the print queues this machine can see}
        {--clear : Forget the stored settings and fall back to .env}';

    protected $description = 'Show or change the counter receipt printer settings';

    public function handle(): int
    {
        if ($this->option('queues')) {
            return $this->listQueues();
        }

        if ($this->option('clear')) {
            PrinterConfig::forget();
            $this->components->info('Stored printer settings cleared.');

            return $this->show();
        }

        // CLI option => setting key. `--raster` and `--chunk` read better on a
        // command line than the config keys they write.
        $map = [
            'driver' => 'driver',
            'host' => 'host',
            'port' => 'port',
            'device' => 'device',
            'command' => 'command',
            'queue' => 'queue',
            'density' => 'density',
            'width' => 'width',
            'raster' => 'raster_mode',
            'chunk' => 'chunk_bytes',
        ];

        $changes = [];

        foreach ($map as $option => $key) {
            if ($this->option($option) !== null) {
                $changes[$key] = $this->option($option);
            }
        }

        if ($changes === []) {
            return $this->show();
        }

        if (PrinterConfig::envDrives()) {
            $this->components->warn('POS_PRINTER_DRIVER is set in .env and always wins. Stored settings will be ignored until it is removed.');
        }

        PrinterConfig::store([...PrinterConfig::all(), ...$changes]);

        $this->components->info('Saved.');

        return $this->show();
    }

    private function show(): int
    {
        $printer = PrinterConfig::all();
        $source = PrinterConfig::envDrives() ? '.env' : 'settings table';

        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>Setting</>', '<options=bold>Value</>');

        foreach (['driver', 'host', 'port', 'device', 'command', 'queue', 'density', 'width', 'raster_mode', 'chunk_bytes'] as $key) {
            $this->components->twoColumnDetail($key, $printer[$key] === null ? '<fg=gray>—</>' : (string) $printer[$key]);
        }

        $this->newLine();

        if (! PrinterConfig::isConfigured()) {
            $this->components->warn('No printer configured — the register will use the browser print dialog.');
            $this->line('  <fg=gray>See the queues on this machine:</> php artisan pos:printer --queues');

            return self::SUCCESS;
        }

        $this->components->info("Configured from the {$source}. Test it with: php artisan pos:test-print --drawer");

        return self::SUCCESS;
    }

    /** What the OS spooler already knows about, so the queue name need not be guessed. */
    private function listQueues(): int
    {
        $process = Process::fromShellCommandline('lpstat -p 2>/dev/null');
        $process->run();

        $queues = array_values(array_filter(explode("\n", trim($process->getOutput()))));

        if ($queues === []) {
            $this->components->warn('No print queues found (or `lpstat` is unavailable on this machine).');

            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($queues as $queue) {
            $this->line('  '.$queue);
        }

        $this->newLine();
        $this->line('  <fg=gray>Wire one up with:</>');
        $this->line('  php artisan pos:printer --driver=command --command="lp -d <queue> -o raw"');

        return self::SUCCESS;
    }
}
