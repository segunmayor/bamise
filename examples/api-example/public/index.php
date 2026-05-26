<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\PhpRequest;
use Bamise\Application\CrudApplication;
use Bamise\Application\DTO\ResponseEnvelope;

/** @var CrudApplication $app */
$app     = require __DIR__ . '/../src/Bootstrap/container.php';
$request = new PhpRequest();
$path    = $request->path();

$segment = trim(explode('/', ltrim($path, '/'))[0]);

$knownResources = ['users', 'posts'];

if (! in_array($segment, $knownResources, true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['message' => 'Resource not found']]);
    exit;
}

$envelope = $app->handle($request, $segment);
sendJson($envelope);

function sendJson(ResponseEnvelope $envelope): void
{
    http_response_code($envelope->httpStatus);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $envelope->success,
        'data'    => $envelope->data,
        'errors'  => $envelope->errors,
        'meta'    => $envelope->meta,
    ]);
}
