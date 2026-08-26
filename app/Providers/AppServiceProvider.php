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
         * The key uses the raw route parameter rather than a resolved
         * `Party` model because `ThrottleRequests` is prioritised ahead of
         * `SubstituteBindings` in the middleware pipeline, so the binding has
         * not run yet. That raw value is exactly the identifier the public
         * gallery is addressed by: `Party::getRouteKeyName()` returns
         * `name`. The throttling bucket is therefore just as discriminating
         * as the public URL itself — no more, no less. The schema does not
         * enforce uniqueness on `parties.name`, so two homonymous parties
         * would share a bucket; they would already be indistinguishable at
         * the URL, which is a pre-existing, separate concern.
         */
        RateLimiter::for('party-download', function (Request $request): Limit {
            $party = (string) $request->route('party');

            return Limit::perMinute(30)->by($request->ip().':'.$party);
        });
    }
}
