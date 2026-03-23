<?php

namespace Tests\Traits;

use App\Services\UmamiClient;

trait RefreshUmamiWebsite
{
    public string $umamiWebsiteId;

    protected function createUmamiWebsite(
        string $domain = 'test.localhost',
        string $name = 'Test Website'
    ): void {
        $response = app(UmamiClient::class)->login()->createWebsite(
            $name,
            $domain
        );

        $this->umamiWebsiteId = $response['id']
            ?? throw new \RuntimeException(
                'Failed to create Umami test website. Response: '.json_encode($response)
            );

        config(['umami.website_id' => $this->umamiWebsiteId]);
    }

    protected function deleteUmamiWebsite(): void
    {
        if (isset($this->umamiWebsiteId)) {
            app(UmamiClient::class)->login()->deleteWebsite($this->umamiWebsiteId);
            unset($this->umamiWebsiteId);
        }
    }
}
