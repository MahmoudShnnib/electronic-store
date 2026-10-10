<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Cart\MergeGuestCart;
use App\Actions\Cart\ResolveCart;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pos\PrinterConfig;
use App\Services\Pos\Printers\CommandPrinter;
use App\Services\Pos\Printers\DevicePrinter;
use App\Services\Pos\Printers\NetworkPrinter;
use App\Services\Pos\Printers\NullPrinter;
use App\Services\Pos\Printers\PrinterConnection;
use App\Services\Storefront\HomePageService;
use App\Support\HomeCache;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Which wire the register's receipt goes down. Bound rather than newed
        // up so a shop can change transport in .env alone, and so tests can
        // swap in NullPrinter and read the ESC/POS back.
        $this->app->bind(PrinterConnection::class, function (): PrinterConnection {
            $printer = PrinterConfig::all();

            return match ($printer['driver']) {
                'network' => new NetworkPrinter((string) $printer['host'], (int) $printer['port'], (float) $printer['timeout']),
                'device' => new DevicePrinter((string) $printer['device']),
                'command' => new CommandPrinter((string) $printer['command'], (float) $printer['timeout']),
                default => new NullPrinter,
            };
        });
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

        // The storefront header's "All categories" menu — same 10-minute cache
        // the homepage rail uses, so the list costs nothing per request.
        Inertia::share('navCategories', fn () => app(HomePageService::class)->categoryCards());

        // The footer's contact block. Settings are cached forever and busted on
        // save, so this is an array lookup, not a query.
        Inertia::share('storeContact', fn () => [
            'phone' => Setting::get('contact_info')['phone'] ?? null,
            'email' => Setting::get('contact_info')['email'] ?? null,
        ]);

        // Footer social icons: only networks the dashboard has a link for.
        Inertia::share('socialLinks', fn () => array_filter(Setting::get('social_links') ?? []));

        // Anything the homepage renders busts its cache, so dashboard edits show
        // up immediately instead of after the 10-minute TTL.
        foreach ([Brand::class, Category::class, Product::class, ProductImage::class, ProductVariant::class, Warehouse::class] as $model) {
            $model::saved(HomeCache::flush(...));
            $model::deleted(HomeCache::flush(...));
        }

        // Signing in on the web (password, registration or Google) keeps whatever
        // the visitor put in the cart as a guest.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && request()->hasCookie(ResolveCart::GUEST_COOKIE)) {
                app(MergeGuestCart::class)->handle((string) request()->cookie(ResolveCart::GUEST_COOKIE), $event->user);
            }
        });

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
