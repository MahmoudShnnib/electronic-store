<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * The reset email for a request made from the storefront SPA: its link opens
 * the SPA's reset page instead of the Inertia one (which goes away at
 * cut-over). Same text and expiry as Laravel's own.
 */
final class StorefrontResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return rtrim((string) config('store.storefront_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
