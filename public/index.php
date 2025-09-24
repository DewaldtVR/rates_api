<?php
declare(strict_types=1);

use App\Bootstrap;
use App\Controllers\RatesController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$app = Bootstrap::create();

$app->post('/api/rates', [RatesController::class, 'getRates']);

$app->get('/health', function (Request $req, Response $res) {
    $res->getBody()->write(json_encode(['ok' => true]));
    return $res->withHeader('Content-Type', 'application/json');
});

$app->run();
