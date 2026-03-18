<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class UmamiClient
{
    private string $baseUrl;

    private ?string $token = null;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('umami.base_url', ''), '/');
    }

    /**
     * Authenticate against the Umami API and store the token for subsequent requests.
     */
    public function login(?string $username = null, ?string $password = null): static
    {
        $response = Http::post("{$this->baseUrl}/api/auth/login", [
            'username' => $username ?? config('umami.admin_username', 'admin'),
            'password' => $password ?? config('umami.admin_password', 'umami'),
        ])->throw();

        $token = $response->json('token');

        if (empty($token)) {
            throw new \RuntimeException('Umami login did not return a valid token.');
        }

        $this->token = $token;

        return $this;
    }

    /**
     * Create a new website in Umami and return its data (including the 'id').
     *
     * @return array<string, mixed>
     */
    public function createWebsite(string $name, string $domain): array
    {
        return $this->http()
            ->post("{$this->baseUrl}/api/websites", [
                'name' => $name,
                'domain' => $domain,
            ])
            ->throw()
            ->json();
    }

    /**
     * Return the names of all custom events tracked for a website between $startAt and now (or $endAt).
     *
     * @return array<int, string>
     */
    public function getEventNames(string $websiteId, Carbon $startAt, ?Carbon $endAt = null): array
    {
        $response = $this->http()
            ->get("{$this->baseUrl}/api/websites/{$websiteId}/event-data/events", [
                'startAt' => $startAt->timestamp * 1000,
                'endAt' => ($endAt ?? now())->addSecond()->timestamp * 1000,
            ])
            ->throw()
            ->json();

        $events = is_array($response) && array_key_exists('data', $response)
            ? $response['data']
            : (array) $response;

        return array_column($events, 'eventName');
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token ?? '');
    }
}
