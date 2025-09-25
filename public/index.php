<?php
declare(strict_types=1);

use App\Bootstrap;
use App\Controllers\RatesController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

require_once __DIR__ . '/../vendor/autoload.php';

$app = Bootstrap::create();

// CORS middleware
$app->add(function (Request $req, RequestHandlerInterface $handler): Response {
    $res = $handler->handle($req);
    return $res
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});

// Preflight
$app->options('/{routes:.+}', function (Request $req, Response $res): Response {
    return $res
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->withStatus(204); // no content
});

// Root
$app->get('/', function (Request $req, Response $res): Response {
    $payload = [
        'name' => 'Rates API',
        'status' => 'ok',
        'endpoints' => [
            'GET /health',
            'POST /api/rates',
        ],
    ];
    $res->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT));
    return $res->withHeader('Content-Type', 'application/json');
});

// Rates endpoint (ensure method signature matches below)
$app->post('/api/rates', [RatesController::class, 'getRates']);

// Health
$app->get('/health', function (Request $req, Response $res): Response {
    $res->getBody()->write(json_encode(['ok' => true]));
    return $res->withHeader('Content-Type', 'application/json');
});

$app->run();
