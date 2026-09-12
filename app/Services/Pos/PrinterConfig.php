<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Setting;

/**
 * Where the printer's wiring is read from.
 *
 * Two sources, in this order:
 *
 *  1. `.env` / `config/pos.php` — explicit deployment config always wins, so a
 *     server can pin its printer and no database row can move it.
 *  2. The `settings` table — what `pos:printer` writes.
 *
 * The second exists because a receipt printer is the one piece of this system
 * that is tied to a physical desk. It gets unplugged, moved to another machine,
 * replaced with a LAN model — and none of that should need a redeploy or a
 * developer with shell access to edit `.env`.
 */
final class PrinterConfig
{
    private const KEYS = ['driver', 'host', 'port', 'device', 'command', 'queue', 'density', 'width', 'threshold', 'feed', 'cut', 'chunk_bytes', 'raster_mode', 'cash_drawer', 'cash_drawer_pin', 'timeout'];

    public static function get(string $key): mixed
    {
        $fromEnv = config("pos.printer.{$key}");

        // Guard on the driver rather than on each value: a half-set .env (host
        // but no driver) should not silently blend with a stored setting and
        // produce a printer nobody configured.
        if (self::envDrives()) {
            return $fromEnv;
        }

        $stored = Setting::get('pos_printer');

        return $stored[$key] ?? $fromEnv;
    }

    /** True when a transport is resolvable at all; false means fall back to the browser. */
    public static function isConfigured(): bool
    {
        return in_array(self::get('driver'), ['cups', 'network', 'device', 'command'], true);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return array_combine(self::KEYS, array_map(self::get(...), self::KEYS));
    }

    /** @param array<string, mixed> $values */
    public static function store(array $values): void
    {
        Setting::set('pos_printer', array_intersect_key($values, array_flip(self::KEYS)));
    }

    public static function forget(): void
    {
        Setting::set('pos_printer', null);
    }

    public static function envDrives(): bool
    {
        return in_array(config('pos.printer.driver'), ['cups', 'network', 'device', 'command'], true);
    }
}
