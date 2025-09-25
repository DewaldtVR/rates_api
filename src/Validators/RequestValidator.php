<?php
declare(strict_types=1);

namespace App\Validators;

final class RequestValidator
{
    private const KIDS_6_13 = 'Kids 6-13';
    private const KIDS_0_5  = 'Kids 0-5';

    /** @return list<string> */
    public static function validate(array $data): array
    {
        $errors = [];

        $errors = array_merge(
            $errors,
            self::requiredKeysErrors($data, ['Arrival', 'Departure']),
            self::agesOrCountsErrors($data),
            self::intFieldErrors($data, ['Adults', self::KIDS_6_13, self::KIDS_0_5, 'Occupants']),
            self::occupantsConsistencyErrors($data),
            self::dateErrors($data, ['Arrival', 'Departure']),
            self::unitIdentifierErrors($data)
        );

        return $errors;
    }

    /** @return list<string> */
    private static function requiredKeysErrors(array $data, array $keys): array
    {
        $errors = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) {
                $errors[] = "Missing: {$k}";
            }
        }
        return $errors;
    }

    /** @return list<string> */
    private static function agesOrCountsErrors(array $data): array
    {
        $errors   = [];
        $hasAges  = array_key_exists('Ages', $data);
        $hasCount = self::hasCounts($data);

        if (!$hasAges && !$hasCount) {
            $errors[] = "Provide either 'Ages' array OR the counts: 'Adults', 'Kids 6-13', 'Kids 0-5'.";
            return $errors;
        }

        if ($hasAges) {
            $ages = $data['Ages'];
            if (!is_array($ages)) {
                $errors[] = "Ages must be an array of non-negative integers.";
            } else {
                $invalid = array_filter($ages, static fn($a) => !is_int($a) || $a < 0);
                if ($invalid) {
                    $errors[] = "Ages must be an array of non-negative integers.";
                }
            }
        }

        return $errors;
    }

    private static function hasCounts(array $data): bool
    {
        return array_key_exists('Adults', $data)
            || array_key_exists(self::KIDS_6_13, $data)
            || array_key_exists(self::KIDS_0_5, $data);
    }

    /** @return list<string> */
    private static function intFieldErrors(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data) && (!is_int($data[$f]) || $data[$f] < 0)) {
                $errors[] = "{$f} must be a non-negative integer.";
            }
        }
        return $errors;
    }

    /** @return list<string> */
    private static function occupantsConsistencyErrors(array $data): array
    {
        if (!array_key_exists('Occupants', $data)) {
            return [];
        }

        $errors   = [];
        $occ      = (int) $data['Occupants'];
        $hasAges  = array_key_exists('Ages', $data);
        $hasCount = self::hasCounts($data);

        if ($hasAges && is_array($data['Ages']) && $occ !== count($data['Ages'])) {
            $errors[] = "Occupants must equal the number of entries in Ages.";
        }

        if ($hasCount) {
            $sum = (int)($data['Adults'] ?? 0)
                 + (int)($data[self::KIDS_6_13] ?? 0)
                 + (int)($data[self::KIDS_0_5] ?? 0);
            if ($occ !== $sum) {
                $errors[] = "Occupants must equal Adults + Kids 6-13 + Kids 0-5.";
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private static function dateErrors(array $data, array $keys): array
    {
        $errors = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) {
                // missing key handled by requiredKeysErrors
                continue;
            }
            $v = (string) $data[$k];
            if (!self::isValidDateFormat($v)) {
                $errors[] = "$k must be dd/mm/yyyy or yyyy-mm-dd.";
            }
        }
        return $errors;
    }

    private static function isValidDateFormat(string $v): bool
    {
        // Allowed: dd/mm/yyyy or yyyy-mm-dd
        return (bool) preg_match('#^(\d{2}/\d{2}/\d{4}|\d{4}-\d{2}-\d{2})$#', $v);
    }

    /** @return list<string> */
    private static function unitIdentifierErrors(array $data): array
    {
        if (!array_key_exists('Unit Name', $data) && !array_key_exists('Unit Type ID', $data)) {
            return ["Provide either 'Unit Name' or 'Unit Type ID'."];
        }
        return [];
    }
}
