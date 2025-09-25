<?php
declare(strict_types=1);

use App\Bootstrap;
use App\Controllers\RatesController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../vendor/autoload.php';

$app = Bootstrap::create();
$app->add(function ($req, $handler) {
    $res = $handler->handle($req);
    return $res
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});
$app->options('/{routes:.+}', function ($res) {
    return $res->withHeader('Access-Control-Allow-Origin', '*')
               ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
               ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});
$app->get('/', function ($res) {
    $res->getBody()->write(json_encode([
        'name' => 'Rates API',
        'status' => 'ok',
        'endpoints' => [
            'GET /health',
            'POST /api/rates'
        ]
    ], JSON_PRETTY_PRINT));
    return $res->withHeader('Content-Type', 'application/json');
});
$app->post('/api/rates', [RatesController::class, 'getRates']);

$app->get('/health', function (Response $res) {
    $res->getBody()->write(json_encode(['ok' => true]));
    return $res->withHeader('Content-Type', 'application/json');
});

$app->run();
