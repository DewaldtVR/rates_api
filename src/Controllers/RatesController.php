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

        // Resolve Unit Type ID
        $map = $config['unit_name_to_type_id'];
        $unitTypeId = null;
        if (isset($data['Unit Type ID']) && is_int($data['Unit Type ID'])) {
            $unitTypeId = (int)$data['Unit Type ID'];
        } elseif (isset($data['Unit Name']) && array_key_exists($data['Unit Name'], $map)) {
            $unitTypeId = $map[$data['Unit Name']];
        } else {
            $unknown = (string)($data['Unit Name'] ?? 'N/A');
            $response->getBody()->write(json_encode([
                'errors' => ["Unknown Unit. Provide 'Unit Type ID' or a known 'Unit Name'."],
                'hint'   => "For testing, use one of: -2147483637, -2147483456 (Unit Name was '{$unknown}')"
            ], JSON_PRETTY_PRINT));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Dates
        try {
            $arrivalYmd   = PayloadTransformer::anyToYmd((string)$data['Arrival']);
            $departureYmd = PayloadTransformer::anyToYmd((string)$data['Departure']);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['errors' => [$e->getMessage()]], JSON_PRETTY_PRINT));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // Guests
        $guests = [];
        if (isset($data['Ages']) && is_array($data['Ages'])) {
            $guests = PayloadTransformer::agesToGuests($data['Ages'], (int)$config['adult_age']);
        } else {
            $adults  = (int)($data['Adults']     ?? 0);
            $kids613 = (int)($data['Kids 6-13']  ?? 0);
            $kids05  = (int)($data['Kids 0-5']   ?? 0);
            $guests  = PayloadTransformer::countsToGuests($adults, $kids613, $kids05);
        }

        // Remote payload
        $remotePayload = [
            'Unit Type ID' => $unitTypeId,
            'Arrival'      => $arrivalYmd,
            'Departure'    => $departureYmd,
            'Guests'       => $guests,
        ];

        // Call remote
        $client = new RemoteRateClient($config['remote']);
        [$status, $body] = $client->postRates($remotePayload);

        // Build display-ready summary
        $summary = [
            'availability'     => is_array($body) ? PayloadTransformer::availabilityFromRemote($body) : null,
            'rooms'            => is_array($body) && isset($body['Rooms']) ? (int)$body['Rooms'] : null,
            'totalCharge'      => is_array($body) && isset($body['Total Charge']) ? (int)$body['Total Charge'] : null,
            'effectiveDailyMin'=> is_array($body) ? PayloadTransformer::minEffectiveDaily($body) : null,
            'unitTitle'        => is_array($body) ? PayloadTransformer::parseSpecialRateTitle($body['Legs'][0]['Special Rate Description'] ?? null) : null,
            'unitTypeId'       => is_array($body) && isset($body['Legs'][0]['Booking Client ID']) ? (int)$body['Legs'][0]['Booking Client ID'] : $unitTypeId,
            'arrival'          => $arrivalYmd,
            'departure'        => $departureYmd,
        ];

        // Relay back
        $response->getBody()->write(json_encode([
            'request' => [
                'received'    => $data,
                'transformed' => $remotePayload,
            ],
            'remote' => [
                'status' => $status,
                'body'   => $body
            ],
            'summary' => $summary
        ], JSON_PRETTY_PRINT));

        return $response->withStatus($status >= 200 && $status < 300 ? 200 : 502)
                        ->withHeader('Content-Type', 'application/json');
    }
}
