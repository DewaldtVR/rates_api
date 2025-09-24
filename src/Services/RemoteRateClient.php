<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class RemoteRateClient
{
    private Client $http;
    private string $url;
    private string $transport; // json|form

    public function __construct(array $remoteConfig)
    {
        $this->url = $remoteConfig['url'];
        $this->transport = $remoteConfig['transport'] ?? 'json';

        $this->http = new Client([
            'timeout' => $remoteConfig['timeout'] ?? 20,
        ]);
    }

    /**
     * @param array $payload remote-format payload
     * @return array [statusCode, body(array|string)]
     */
    public function postRates(array $payload): array
    {
        try {
            $options = [];

            if ($this->transport === 'form') {
                // If the remote endpoint expects application/x-www-form-urlencoded
                $options['form_params'] = $payload;
            } else {
                // Default: JSON
                $options['json'] = $payload;
            }

            $res = $this->http->post($this->url, $options);

            $status = $res->getStatusCode();
            $body = (string)$res->getBody();

            // Try to decode JSON; if not JSON, return raw string
            $decoded = json_decode($body, true);
            return [$status, $decoded ?? $body];

        } catch (GuzzleException $e) {
            return [500, ['error' => 'Remote request failed', 'details' => $e->getMessage()]];
        }
    }
}
