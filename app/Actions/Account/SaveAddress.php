<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an address, demoting the user's previous defaults in the
 * same transaction.
 *
 * Demotion and write must be atomic: clearing the old default and then failing
 * to persist the new one would leave the account with no default address at all.
 */
final class SaveAddress
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes, ?Address $address = null): Address
    {
        return DB::transaction(function () use ($user, $attributes, $address): Address {
            $this->demoteExistingDefaults($user, $attributes, $address);

            if ($address === null) {
                return $user->addresses()->create($attributes);
            }

            $address->update($attributes);

            return $address->refresh();
        });
    }

    /**
     * Only one default of each kind may exist per user.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function demoteExistingDefaults(User $user, array $attributes, ?Address $address): void
    {
        foreach (['is_default_shipping', 'is_default_billing'] as $flag) {
            if (! ($attributes[$flag] ?? false)) {
                continue;
            }

            $user->addresses()
                ->when($address !== null, fn ($query) => $query->whereKeyNot($address->getKey()))
                ->update([$flag => false]);
        }
    }
}
