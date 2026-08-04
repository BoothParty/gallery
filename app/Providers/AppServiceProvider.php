<?php

namespace App\Providers;

use App\Services\CrudDiscoveryService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\ServiceProvider;
use Notion\Notion;

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
        //
    }
}
