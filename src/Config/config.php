<?php
declare(strict_types=1);

return [

    // Map inbound "Unit Name" → remote "Unit Type ID"
    // Add real mappings here. For testing, they gave IDs: -2147483637, -2147483456
    'unit_name_to_type_id' => [
        // Example mappings (edit/fill in as needed)
        'Desert Lodge Family Room' => -2147483637,
        'Desert Lodge Twin'        => -2147483456,
    ],

    // Fallback when client provides Unit Type ID directly (optional extension):
    // If you plan to accept "Unit Type ID" directly, you can read it and bypass mapping.

    'adult_age' => (int)($_ENV['ADULT_AGE'] ?? 12),

    'remote' => [
        'url'       => $_ENV['REMOTE_RATES_URL'] ?? 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php',
        'transport' => $_ENV['REMOTE_TRANSPORT'] ?? 'json', // json|form
        'timeout'   => 20,
    ],
];
