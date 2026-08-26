<?php

namespace App\Providers;

use App\Services\CrudDiscoveryService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Notion\Notion;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Zoho\Provider as ZohoProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CrudDiscoveryService::class);

        $this->app->singleton(Notion::class, fn (): Notion => Notion::createWithPsrImplementations(
            new GuzzleClient([
                'timeout' => (int) config('services.notion.timeout'),
                'connect_timeout' => 3,
            ]),
            new HttpFactory,
            (string) config('services.notion.token'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('zoho', ZohoProvider::class);
        });

        /**
         * Guests at the same event typically share one public IP (the venue's
         * wifi), so keying on the IP alone would let them throttle each
         * other. Scoping the key to the party as well as the IP means the
         * quota is per event, not just per network.
         *
         * The route model binding has not run yet at this point in the
         * middleware pipeline (`ThrottleRequests` is prioritised ahead of
         * `SubstituteBindings`), so the route parameter is still the raw
         * party name rather than a resolved `Party` instance.
         */
        RateLimiter::for('party-download', function (Request $request): Limit {
            $party = (string) $request->route('party');

            return Limit::perMinute(30)->by($request->ip().':'.$party);
        });
    }
}
