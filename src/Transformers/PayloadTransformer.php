<?php
declare(strict_types=1);

namespace App\Transformers;

final class PayloadTransformer
{
    /**
     * Convert dd/mm/yyyy -> yyyy-mm-dd
     */
    public static function dmyToYmd(string $dmy): string
    {
        [$dd, $mm, $yyyy] = explode('/', $dmy);
        return sprintf('%04d-%02d-%02d', (int)$yyyy, (int)$mm, (int)$dd);
    }

    /**
     * Convert ages -> Guests [{ "Age Group": "Adult"|"Child" }]
     */
    public static function agesToGuests(array $ages, int $adultAge): array
    {
        $guests = [];
        foreach ($ages as $age) {
            $guests[] = [
                'Age Group' => ($age >= $adultAge) ? 'Adult' : 'Child'
            ];
        }
        return $guests;
    }
}
