<?php

namespace App\Providers;

use App\Services\CrudDiscoveryService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Event;
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
    }
}
