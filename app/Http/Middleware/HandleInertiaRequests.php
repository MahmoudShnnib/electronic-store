<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Cart\ResolveCart;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at,
                    'is_staff' => $user->isStaff(),
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'cartCount' => fn (): int => $this->cartCount($request),
            'wishlistCount' => fn (): int => $user?->wishlistItems()->count() ?? 0,
        ];
    }

    private function cartCount(Request $request): int
    {
        $cart = app(ResolveCart::class)->handle($request, create: false);

        return $cart === null ? 0 : (int) $cart->items()->sum('quantity');
    }
}
