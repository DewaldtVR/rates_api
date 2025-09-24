<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\RemoteRateClient;
use App\Transformers\PayloadTransformer;
use App\Validators\RequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class RatesController
{
    public function getRates(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $config = require __DIR__ . '/../Config/config.php';

        // Validate
        $errors = RequestValidator::validate($data);
        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors], JSON_PRETTY_PRINT));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // Resolve Unit Type ID from Unit Name
        $unitName = (string)$data['Unit Name'];
        $map = $config['unit_name_to_type_id'];

        if (!array_key_exists($unitName, $map)) {
            // For test/dev: allow explicit override if client passed a "Unit Type ID" field (optional)
            $maybeId = $data['Unit Type ID'] ?? null;

            if (!is_int($maybeId)) {
                $response->getBody()->write(json_encode([
                    'errors' => ["Unknown Unit Name '{$unitName}'. Please configure mapping or provide 'Unit Type ID'."],
                    'hint'   => "For testing, use one of: -2147483637, -2147483456"
                ], JSON_PRETTY_PRINT));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $unitTypeId = $maybeId;
        } else {
            $unitTypeId = $map[$unitName];
        }

        // Transform dates & ages
        $arrivalYmd   = PayloadTransformer::dmyToYmd((string)$data['Arrival']);
        $departureYmd = PayloadTransformer::dmyToYmd((string)$data['Departure']);
        $guests       = PayloadTransformer::agesToGuests($data['Ages'], $config['adult_age']);

        // Build remote payload
        $remotePayload = [
            'Unit Type ID' => $unitTypeId,
            'Arrival'      => $arrivalYmd,
            'Departure'    => $departureYmd,
            'Guests'       => $guests,
        ];

        // Call remote
        $client = new RemoteRateClient($config['remote']);
        [$status, $body] = $client->postRates($remotePayload);

        // Relay back to frontend
        $response->getBody()->write(json_encode([
            'request' => [
                'received' => $data,
                'transformed' => $remotePayload,
            ],
            'remote' => [
                'status' => $status,
                'body' => $body
            ]
        ], JSON_PRETTY_PRINT));

        return $response->withStatus($status >= 200 && $status < 300 ? 200 : 502)
                        ->withHeader('Content-Type', 'application/json');
    }
}
