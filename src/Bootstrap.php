<?php
declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

final class Bootstrap
{
    public static function create()
    {
        $app = AppFactory::create();

        // Error middleware (dev-friendly defaults)
        $displayErrorDetails = true;
        $logErrors = true;
        $logErrorDetails = true;
        $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails);

        // Load .env
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }

        // JSON body parsing
        $app->addBodyParsingMiddleware();

        return $app;
    }
}
