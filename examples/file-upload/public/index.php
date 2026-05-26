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

// ── Upload form (GET /uploads/form) ───────────────────────────────────────────
if ($request->method() === 'GET' && str_ends_with($path, '/form')) {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Upload File</title>
    </head>
    <body>
        <h1>Upload a File</h1>
        <form method="POST" action="/uploads" enctype="multipart/form-data">
            <label>File: <input type="file" name="file" accept="image/*,.pdf" required></label><br><br>
            <button type="submit">Upload</button>
        </form>
        <hr>
        <p><a href="/uploads">List uploaded files</a></p>
    </body>
    </html>
    HTML;
    exit;
}

// ── CRUD routes ───────────────────────────────────────────────────────────────
$knownResources = ['uploads'];

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
