<?php

namespace App\Services\Duffel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class DuffelClient
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.duffel.com',
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.duffel.api_key'),
                'Duffel-Version' => 'v2',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    public function get(string $uri, array $query = []): array
    {
        try {
            $response = $this->client->get($uri, [
                'query' => $query,
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (RequestException $e) {
            throw new \Exception($this->formatError($e));
        }
    }

    public function post(string $uri, array $payload = []): array
    {
        try {
            $response = $this->client->post($uri, [
                'json' => $payload,
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (RequestException $e) {
            throw new \Exception($this->formatError($e));
        }
    }

    public function patch(string $uri, array $payload = []): array 
    {
        try {
            $response = $this->client->patch($uri, [
                'json' => $payload
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (RequestException $e) {
            throw new \Exception($this->formatError($e));
        }
    }

    protected function formatError(RequestException $e): string
    {
        $body = '';

        if ($e->hasResponse()) {
            $body = (string) $e->getResponse()->getBody();
        }

        return 'Duffel API error: ' . $e->getMessage() . ($body ? ' | ' . $body : '');
    }
}