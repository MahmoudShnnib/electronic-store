<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductReturn;
use App\Models\User;

final class ProductReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('returns.manage');
    }

    public function view(User $user, ProductReturn $return): bool
    {
        return $user->can('returns.manage') || $return->user_id === $user->id;
    }

    public function manage(User $user, ProductReturn $return): bool
    {
        return $user->can('returns.manage');
    }
}
