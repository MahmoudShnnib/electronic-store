<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\HomeCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // Super Admin bypasses every permission check.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('super_admin') ? true : null);

        // Admin area entry: any staff role. Individual pages still check
        // their own permission (products.view, orders.view, …).
        Gate::define('access-admin', fn (User $user) => $user->isStaff());

        $this->configureRateLimiting();

        // Anything the homepage renders busts its cache, so dashboard edits show
        // up immediately instead of after the 10-minute TTL.
        foreach ([Brand::class, Category::class, Product::class, ProductImage::class] as $model) {
            $model::saved(HomeCache::flush(...));
            $model::deleted(HomeCache::flush(...));
        }

        // Stamp every audit record with request context.
        Activity::creating(function (Activity $activity): void {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            $activity->properties = $activity->properties->merge([
                'ip' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?? $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth-email:'.mb_strtolower((string) $request->input('email'))),
        ]);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?? $request->ip()));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?? $request->ip()));
    }
}
