<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Models\User;

/**
 * Matches a phone number typed at the till to an existing customer account, so a
 * regular's counter purchases land in their history. Never creates anyone: a
 * walk-in with no account stays a cash customer.
 *
 * Libyan numbers are written many ways (0912345678, +218 91 234 5678,
 * 218912345678); they are compared on their last nine digits.
 */
final class FindCustomerByPhone
{
    public function handle(?string $phone): ?User
    {
        $key = self::key($phone);

        if ($key === null) {
            return null;
        }

        return User::query()
            ->role('customer')
            ->where('is_active', true)
            ->where('phone', 'like', '%'.substr($key, -4))
            ->get(['id', 'name', 'email', 'phone'])
            ->first(fn (User $user) => self::key($user->phone) === $key);
    }

    /** The last nine digits, or null when there aren't enough to identify anyone. */
    public static function key(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }
}
