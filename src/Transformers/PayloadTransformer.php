<?php
declare(strict_types=1);

namespace App\Transformers;

final class PayloadTransformer
{
    private const AGE_GROUP = 'Age Group';
    /**
     * Accept dd/mm/yyyy OR yyyy-mm-dd and return yyyy-mm-dd
     */
    public static function anyToYmd(string $date): string
    {
        $date = trim($date);
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $date)) {
            [$dd, $mm, $yyyy] = explode('/', $date);
            return sprintf('%04d-%02d-%02d', (int)$yyyy, (int)$mm, (int)$dd);
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
            return $date;
        }
        throw new \InvalidArgumentException("Date must be dd/mm/yyyy or yyyy-mm-dd.");
    }

    /**
     * Convert ages -> Guests [{ "Age Group": "Adult"|"Child" }]
     * Uses $adultAge cutoff.
     */
    public static function agesToGuests(array $ages, int $adultAge): array
    {
        $guests = [];
        foreach ($ages as $age) {
            $guests[] = [self::AGE_GROUP => ($age >= $adultAge) ? 'Adult' : 'Child'];
        }
        return $guests;
    }

    /**
     * Convert counts (Adults, Kids 6–13, Kids 0–5) to Guests array.
     */
    public static function countsToGuests(int $adults, int $kids613, int $kids05): array
    {
        $guests = [];
        for ($i = 0; $i < $adults; $i++)   $guests[] = [self::AGE_GROUP => 'Adult'];
        for ($i = 0; $i < ($kids613 + $kids05); $i++) $guests[] = [self::AGE_GROUP => 'Child'];
        return $guests;
    }

    /**
     * Helpers for display summary from remote body
     */
    public static function availabilityFromRemote(array $remote): ?bool
    {
        if (isset($remote['Rooms']) && is_numeric($remote['Rooms'])) {
            return ((int)$remote['Rooms']) > 0;
        }
        return null;
    }

    public static function minEffectiveDaily(array $remote): ?int
    {
        $legs = $remote['Legs'] ?? [];
        if (!is_array($legs) || empty($legs)) 
    {
            return null;
        }
        $vals = array_values(array_filter(array_map(
            fn($l) => is_array($l) && isset($l['Effective Average Daily Rate']) && is_numeric($l['Effective Average Daily Rate'])
                ? (int)$l['Effective Average Daily Rate'] : null,
            $legs
        )));
        return empty($vals) ? null : min($vals);
    }

    public static function parseSpecialRateTitle(?string $desc): ?string
    {
        if (!$desc) 
        {
                return null;
        }
        $cleaned = preg_replace('/^\*\s*/', '', $desc);
        if (!is_string($cleaned)) 
        {
            $cleaned = $desc;
        }
        if (preg_match('/-\s*([^-]+)\s*$/', $cleaned, $m)) {
            return trim($m[1]);
        }
        return trim($cleaned);
    }
}
