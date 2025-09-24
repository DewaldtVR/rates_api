<?php
declare(strict_types=1);

return [
    'unit_name_to_type_id' => [
        'Desert Lodge Family Room' => -2147483637,
        'Desert Lodge Twin'        => -2147483456,
    ],
    'adult_age' => (int)($_ENV['ADULT_AGE'] ?? 12),
    'remote' => [
        'url'       => $_ENV['REMOTE_RATES_URL'] ?? 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php',
        'transport' => $_ENV['REMOTE_TRANSPORT'] ?? 'json',
        'timeout'   => 20,
    ],
];
