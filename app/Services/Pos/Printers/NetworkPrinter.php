<?php

declare(strict_types=1);

namespace App\Services\Pos\Printers;

/**
 * A printer with its own IP, spoken to over raw JetDirect (port 9100).
 *
 * The only transport that works when PHP and the printer are on the same
 * network but not the same machine.
 */
final class NetworkPrinter implements PrinterConnection
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 9100,
        private readonly float $timeout = 3,
    ) {}

    public function write(string $bytes): void
    {
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $code,
            $message,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            throw new PrinterUnavailable("Could not reach the printer at {$this->host}:{$this->port} ({$message}).", (int) $code);
        }

        // Without this a half-open printer leaves the request hanging until
        // PHP's own limit, and the cashier stares at a spinner.
        stream_set_timeout($socket, (int) $this->timeout);

        try {
            $written = @fwrite($socket, $bytes);

            if ($written === false || $written < strlen($bytes)) {
                throw new PrinterUnavailable("The printer at {$this->host} accepted only part of the receipt.");
            }
        } finally {
            fclose($socket);
        }
    }
}
