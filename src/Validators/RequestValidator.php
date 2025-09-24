<?php
declare(strict_types=1);

namespace App\Validators;

final class RequestValidator
{
    /**
     * Validates inbound payload:
     * {
     *  "Unit Name": "String",
     *  "Arrival": "dd/mm/yyyy",
     *  "Departure": "dd/mm/yyyy",
     *  "Occupants": <int>,
     *  "Ages": [<int>]
     * }
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $required = ['Unit Name', 'Arrival', 'Departure', 'Occupants', 'Ages'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = "Missing: {$key}";
            }
        }

        if (isset($data['Occupants']) && (!is_int($data['Occupants']) || $data['Occupants'] < 1)) {
            $errors[] = "Occupants must be a positive integer.";
        }

        if (isset($data['Ages'])) {
            if (!is_array($data['Ages']) || array_filter($data['Ages'], fn($a) => !is_int($a) || $a < 0)) {
                $errors[] = "Ages must be an array of non-negative integers.";
            }
        }

        // (Optional) Ensure Occupants == count(Ages)
        if (isset($data['Occupants'], $data['Ages']) && $data['Occupants'] !== count($data['Ages'])) {
            $errors[] = "Occupants must equal the number of entries in Ages.";
        }

        // Basic dd/mm/yyyy check
        foreach (['Arrival', 'Departure'] as $dateKey) {
            if (isset($data[$dateKey]) && !preg_match('#^\d{2}/\d{2}/\d{4}$#', (string)$data[$dateKey])) {
                $errors[] = "$dateKey must be in dd/mm/yyyy format.";
            }
        }

        return $errors;
    }
}
