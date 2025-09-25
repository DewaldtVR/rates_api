<?php
declare(strict_types=1);

namespace App\Validators;

final class RequestValidator
{
    private const KIDS_6_13 = 'Kids 6-13';
    private const KIDS_0_5 = 'Kids 0-5';
    public static function validate(array $data): array
    {
        $errors = [];

        foreach (['Arrival', 'Departure'] as $k) {
            if (!isset($data[$k]))
            {
                $errors[] = "Missing: {$k}";
            }
        }

        $hasAges   = array_key_exists('Ages', $data);
        $hasCounts = array_key_exists('Adults', $data) ||
                     array_key_exists(self::KIDS_6_13, $data) ||
                     array_key_exists(self::KIDS_0_5, $data);

        if (!$hasAges && !$hasCounts) {
            $errors[] = "Provide either 'Ages' array OR the counts: 'Adults', 'Kids 6-13', 'Kids 0-5'.";
        }

        if ($hasAges && !is_array($data['Ages']) || array_filter($data['Ages'], fn($a) => !is_int($a) || $a < 0)) {
            $errors[] = "Ages must be an array of non-negative integers.";
        }

        $intFields = ['Adults', self::KIDS_6_13, self::KIDS_0_5, 'Occupants'];
        foreach ($intFields as $f) {
            if (isset($data[$f]) && (!is_int($data[$f]) || $data[$f] < 0)) {
                $errors[] = "{$f} must be a non-negative integer.";
            }
        }

        // Occupants check (if available)
        if (isset($data['Occupants'])) {
            $occ = (int)$data['Occupants'];
            if ($hasAges && $occ !== count($data['Ages'])) {
                $errors[] = "Occupants must equal the number of entries in Ages.";
            }
            if ($hasCounts) {
                $sum = (int)($data['Adults'] ?? 0) + (int)($data[self::KIDS_6_13] ?? 0) + (int)($data[self::KIDS_0_5] ?? 0);
                if ($occ !== $sum) {
                    $errors[] = "Occupants must equal Adults + Kids 6-13 + Kids 0-5.";
                }
            }
        }

        // Date format
        foreach (['Arrival', 'Departure'] as $dateKey) {
            if (isset($data[$dateKey]) &&
                !preg_match('#^(\d{2}/\d{2}/\d{4}|\d{4}-\d{2}-\d{2})$#', (string)$data[$dateKey])) {
                $errors[] = "$dateKey must be dd/mm/yyyy or yyyy-mm-dd.";
            }
        }

        // at least one of Unit Name or Unit Type ID should be present
        if (!array_key_exists('Unit Name', $data) && !array_key_exists('Unit Type ID', $data)) {
            $errors[] = "Provide either 'Unit Name' or 'Unit Type ID'.";
        }

        return $errors;
    }
}
