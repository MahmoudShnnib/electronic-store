<?php

declare(strict_types=1);

namespace App\Services\Pos;

/**
 * An ESC/POS byte stream, built one command at a time.
 *
 * Only the handful of commands a receipt actually needs are here, and each is
 * spelled out rather than hidden behind a driver library: the whole job is
 * "burn this bitmap, feed, cut, kick the drawer", and every one of those is a
 * three-to-five byte sequence that every Xprinter, Epson and clone implements
 * identically. A dependency would buy nothing and would add a code-page layer
 * this design deliberately does not use.
 *
 * Commands are kept as separate segments rather than one string so the stream
 * can be split for delivery **without ever cutting a command in half** — see
 * `chunks()`, which is what keeps a tall receipt printable.
 */
final class EscPosDocument
{
    /**
     * Rows per raster band.
     *
     * Small on purpose. Each `GS v 0` forces the head to print that strip, so
     * short bands give the printer regular opportunities to drain rather than
     * asking it to hold a whole slip in RAM.
     */
    private const BAND_ROWS = 24;

    /** @var list<string> */
    private array $segments = [];

    /** `ESC @` — reset the printer to its power-on defaults. */
    public function initialise(): self
    {
        return $this->raw("\x1B\x40");
    }

    /** `GS v 0` — burn a bitmap at full density, one band per segment. */
    public function image(Raster $raster): self
    {
        $bytesPerRow = $raster->bytesPerRow();

        for ($row = 0; $row < $raster->height; $row += self::BAND_ROWS) {
            $rows = min(self::BAND_ROWS, $raster->height - $row);

            $this->raw("\x1D\x76\x30\x00".pack('vv', $bytesPerRow, $rows).$raster->band($row, $rows));
        }

        return $this;
    }

    /** `ESC d n` — feed n lines. */
    public function feed(int $lines): self
    {
        return $lines > 0 ? $this->raw("\x1B\x64".chr(min($lines, 255))) : $this;
    }

    /** `GS V B n` — feed n dots, then partial-cut the roll. */
    public function cut(): self
    {
        return $this->raw("\x1D\x56\x42\x00");
    }

    /**
     * `ESC p m t1 t2` — pulse the drawer solenoid.
     *
     * 25ms on and 250ms off is the interval Xprinter documents; a shorter pulse
     * does not always throw the latch on a full drawer.
     */
    public function kickDrawer(int $pin = 0): self
    {
        return $this->raw("\x1B\x70".chr($pin === 0 ? 0 : 1)."\x19\xFA");
    }

    public function raw(string $bytes): self
    {
        $this->segments[] = $bytes;

        return $this;
    }

    /**
     * The stream split into deliverable pieces of at most `$maxBytes`.
     *
     * A cheap thermal controller holds far less than a slip in RAM and does not
     * reliably apply flow control, so a whole receipt written in one go
     * overruns it: it loses command sync and prints the remaining raster bytes
     * as text — pages of GB18030 gibberish. Handing it a few kilobytes at a
     * time, and letting each delivery complete before the next starts, keeps it
     * inside its buffer.
     *
     * Splits only ever fall **between** commands. A band larger than `$maxBytes`
     * is emitted whole rather than truncated — a short slip that prints beats a
     * neat chunk boundary that corrupts one.
     *
     * @return list<string>
     */
    public function chunks(int $maxBytes): array
    {
        if ($maxBytes <= 0) {
            return [$this->bytes()];
        }

        $chunks = [];
        $current = '';

        foreach ($this->segments as $segment) {
            if ($current !== '' && strlen($current) + strlen($segment) > $maxBytes) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= $segment;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    public function bytes(): string
    {
        return implode('', $this->segments);
    }
}
