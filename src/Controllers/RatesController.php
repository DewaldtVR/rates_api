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
    private const UNIT_NAME    = 'Unit Name';
    private const UNIT_TYPE_ID = 'Unit Type ID';

    public function getRates(Request $request, Response $response, array $args = []): Response
    {
        /** @var array{remote: array, unit_name_to_type_id: array, adult_age:int} $config */
        $config = require_once __DIR__ . '/../Config/config.php';

        // 0) Read input (JSON or form). BodyParsingMiddleware should parse JSON, but we add a fallback.
        $data = $this->readInput($request);

        // 1) Validate
        if ($err = $this->validateRequest($data, $response)) {
            return $err;
        }

        // 2) Resolve Unit Type ID
        $unitTypeId = $this->resolveUnitTypeId($data, $config, $response);
        if ($unitTypeId instanceof Response) {
            return $unitTypeId;
        }

        // 3) Transform Dates
        $dates = $this->transformDates($data, $response);
        if ($dates instanceof Response) {
            return $dates;
        }
        [$arrivalYmd, $departureYmd] = $dates;

        // 4) Transform Guests
        $guests = $this->transformGuests($data, $config);

        // 5) Build Remote Payload
        $remotePayload = $this->buildRemotePayload($unitTypeId, $arrivalYmd, $departureYmd, $guests);

        // 6) Call Remote and Build Summary
        [$status, $body, $summary] = $this->callRemoteAndBuildSummary(
            $remotePayload,
            $config,
            $arrivalYmd,
            $departureYmd,
            $unitTypeId
        );

        // 7) Write Response
        return $this->writeFinalResponse($response, $data, $remotePayload, $status, $body, $summary);
    }

    /** Parse JSON or form body into an array. */
    private function readInput(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }
        $raw = (string) $request->getBody();
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    /** Return Response on error, null on success. */
    private function validateRequest(array $data, Response $response): ?Response
    {
        $errors = RequestValidator::validate($data);
        return !empty($errors)
            ? $this->writeJsonError($response, ['errors' => $errors], 422)
            : null;
    }

    /** @return int|Response */
    private function resolveUnitTypeId(array $data, array $config, Response $response): int|Response
    {
        $map = $config['unit_name_to_type_id'];

        if (isset($data[self::UNIT_TYPE_ID]) && is_int($data[self::UNIT_TYPE_ID])) {
            return (int) $data[self::UNIT_TYPE_ID];
        }

        if (isset($data[self::UNIT_NAME]) && array_key_exists($data[self::UNIT_NAME], $map)) {
            return (int) $map[$data[self::UNIT_NAME]];
        }

        $unknown = (string)($data[self::UNIT_NAME] ?? 'N/A');
        return $this->writeJsonError($response, [
            'errors' => ["Unknown Unit. Provide '" . self::UNIT_TYPE_ID . "' or a known 'Unit Name'."],
            'hint'   => "For testing, use one of: -2147483637, -2147483456 (Unit Name was '{$unknown}')",
        ], 400);
    }

    /** @return array{0:string,1:string}|Response */
    private function transformDates(array $data, Response $response): array|Response
    {
        try {
            $arrivalYmd   = PayloadTransformer::anyToYmd((string)($data['Arrival'] ?? ''));
            $departureYmd = PayloadTransformer::anyToYmd((string)($data['Departure'] ?? ''));
            return [$arrivalYmd, $departureYmd];
        } catch (\InvalidArgumentException $e) {
            return $this->writeJsonError($response, ['errors' => [$e->getMessage()]], 422);
        }
    }

    private function transformGuests(array $data, array $config): array
    {
        if (isset($data['Ages']) && is_array($data['Ages'])) {
            return PayloadTransformer::agesToGuests($data['Ages'], (int) $config['adult_age']);
        }
        $adults  = (int)($data['Adults'] ?? 0);
        $kids613 = (int)($data['Kids 6-13'] ?? 0);
        $kids05  = (int)($data['Kids 0-5'] ?? 0);
        return PayloadTransformer::countsToGuests($adults, $kids613, $kids05);
    }

    private function buildRemotePayload(int $unitTypeId, string $arrivalYmd, string $departureYmd, array $guests): array
    {
        return [
            self::UNIT_TYPE_ID => $unitTypeId,
            'Arrival'          => $arrivalYmd,
            'Departure'        => $departureYmd,
            'Guests'           => $guests,
        ];
    }

    /** @return array{0:int,1:mixed,2:array} */
    private function callRemoteAndBuildSummary(
        array $remotePayload,
        array $config,
        string $arrivalYmd,
        string $departureYmd,
        int $unitTypeId
    ): array {
        $client = new RemoteRateClient($config['remote']);
        [$status, $body] = $client->postRates($remotePayload);

        $summary = [
            'availability'      => is_array($body) ? PayloadTransformer::availabilityFromRemote($body) : null,
            'rooms'             => is_array($body) && isset($body['Rooms']) ? (int) $body['Rooms'] : null,
            'totalCharge'       => is_array($body) && isset($body['Total Charge']) ? (int) $body['Total Charge'] : null,
            'effectiveDailyMin' => is_array($body) ? PayloadTransformer::minEffectiveDaily($body) : null,
            'unitTitle'         => is_array($body)
                ? PayloadTransformer::parseSpecialRateTitle($body['Legs'][0]['Special Rate Description'] ?? null)
                : null,
            'unitTypeId'        => is_array($body) && isset($body['Legs'][0]['Booking Client ID'])
                ? (int) $body['Legs'][0]['Booking Client ID']
                : $unitTypeId,
            'arrival'           => $arrivalYmd,
            'departure'         => $departureYmd,
        ];

        return [$status, $body, $summary];
    }

    private function writeFinalResponse(
        Response $response,
        array $data,
        array $remotePayload,
        int $status,
        $body,
        array $summary
    ): Response {
        $response->getBody()->write(json_encode([
            'request' => [
                'received'    => $data,
                'transformed' => $remotePayload,
            ],
            'remote'   => [
                'status' => $status,
                'body'   => $body,
            ],
            'summary'  => $summary,
        ], JSON_PRETTY_PRINT));

        return $response
            ->withStatus(($status >= 200 && $status < 300) ? 200 : 502)
            ->withHeader('Content-Type', 'application/json');
    }

    private function writeJsonError(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
