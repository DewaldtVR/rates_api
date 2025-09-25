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
    private const UNIT_NAME = 'Unit Name';
    private const UNIT_TYPE_ID = 'Unit Type ID';
    public function getRates(Request $request, Response $response, array $args = []): Response
    {
        $config = require_once __DIR__ . '/../Config/config.php';

        $finalResponse = null;

        // Step 1: Validate
        $validationResponse = $this->validateRequest($data, $response);
        if ($validationResponse instanceof Response) {
            $finalResponse = $validationResponse;
        }

        // Step 2: Resolve Unit Type ID
        if ($finalResponse === null) {
            $unitTypeId = $this->resolveUnitTypeId($data, $config, $response);
            if ($unitTypeId instanceof Response) {
                $finalResponse = $unitTypeId;
            }
        }

        // Step 3: Transform Dates
        if ($finalResponse === null) {
            $dates = $this->transformDates($data, $response);
            if ($dates instanceof Response) {
                $finalResponse = $dates;
            }
        }

        if ($finalResponse !== null) {
            return $finalResponse;
        }

        [$arrivalYmd, $departureYmd] = $dates;

        // Step 4: Transform Guests
        $guests = $this->transformGuests($data, $config);

        // Step 5: Build Remote Payload
        $remotePayload = $this->buildRemotePayload($unitTypeId, $arrivalYmd, $departureYmd, $guests);

        // Step 6: Call Remote and Build Summary
        [$status, $body, $summary] = $this->callRemoteAndBuildSummary($remotePayload, $config, $arrivalYmd, $departureYmd, $unitTypeId);

        // Step 7: Write Response
        return $this->writeFinalResponse($response, $data, $remotePayload, $status, $body, $summary);
    }

    private function validateRequest(array $data, Response $response)
    {
        $errors = RequestValidator::validate($data);
        if (!empty($errors)) {
            return $this->writeJsonError($response, ['errors' => $errors], 422);
        }
        return true;
    }

    private function buildRemotePayload($unitTypeId, $arrivalYmd, $departureYmd, $guests): array
    {
        return [
            self::UNIT_TYPE_ID => $unitTypeId,
            'Arrival'      => $arrivalYmd,
            'Departure'    => $departureYmd,
            'Guests'       => $guests,
        ];
    }

    private function callRemoteAndBuildSummary(array $remotePayload, array $config, $arrivalYmd, $departureYmd, $unitTypeId): array
    {
        $client = new RemoteRateClient($config['remote']);
        [$status, $body] = $client->postRates($remotePayload);

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

        return [$status, $body, $summary];
    }

    private function writeFinalResponse(Response $response, array $data, array $remotePayload, int $status, $body, array $summary): Response
    {
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

    private function resolveUnitTypeId(array $data, array $config, Response $response)
    {
        $map = $config['unit_name_to_type_id'];
        if (isset($data[self::UNIT_TYPE_ID]) && is_int($data[self::UNIT_TYPE_ID])) {
            return (int)$data[self::UNIT_TYPE_ID];
        }
        if (isset($data[self::UNIT_NAME]) && array_key_exists($data[self::UNIT_NAME], $map)) {
            return $map[$data[self::UNIT_NAME]];
        }
        $unknown = (string)($data[self::UNIT_NAME] ?? 'N/A');
        return $this->writeJsonError($response, [
            'errors' => ["Unknown Unit. Provide '" . self::UNIT_TYPE_ID . "' or a known 'Unit Name'."],
            'hint'   => "For testing, use one of: -2147483637, -2147483456 (Unit Name was '{$unknown}')"
        ], 400);
    }

    private function transformDates(array $data, Response $response)
    {
        try {
            $arrivalYmd   = PayloadTransformer::anyToYmd((string)$data['Arrival']);
            $departureYmd = PayloadTransformer::anyToYmd((string)$data['Departure']);
            return [$arrivalYmd, $departureYmd];
        } catch (\InvalidArgumentException $e) {
            return $this->writeJsonError($response, ['errors' => [$e->getMessage()]], 422);
        }
    }

    private function transformGuests(array $data, array $config): array
    {
        if (isset($data['Ages']) && is_array($data['Ages'])) {
            return PayloadTransformer::agesToGuests($data['Ages'], (int)$config['adult_age']);
        }
        $adults  = (int)($data['Adults']     ?? 0);
        $kids613 = (int)($data['Kids 6-13']  ?? 0);
        $kids05  = (int)($data['Kids 0-5']   ?? 0);
        return PayloadTransformer::countsToGuests($adults, $kids613, $kids05);
    }

    private function writeJsonError(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
