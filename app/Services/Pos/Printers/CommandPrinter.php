<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * The stream piped through a spooler — `lp -d POS80 -o raw` on macOS/Linux,
 * or a `print` style command on Windows.
 *
 * Slower than writing the device node, but it is the one local transport that
 * works without handing the web user raw device permissions, and it inherits
 * whatever the OS already knows about the queue.
 */
final class CommandPrinter implements PrinterConnection
{
    /** @param string $command A shell command that reads the stream on stdin. */
    public function __construct(
        private readonly string $command,
        private readonly float $timeout = 3,
    ) {}

    public function write(string $bytes): void
    {
        $process = Process::fromShellCommandline($this->command)
            ->setInput($bytes)
            ->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (ExceptionInterface $e) {
            throw new PrinterUnavailable("The print command `{$this->command}` could not be run: {$e->getMessage()}.", 0, $e);
        }

        if (! $process->isSuccessful()) {
            throw new PrinterUnavailable(sprintf(
                'The print command `%s` exited with %d: %s',
                $this->command,
                (int) $process->getExitCode(),
                trim($process->getErrorOutput()) ?: trim($process->getOutput()),
            ));
        }
    }
}
