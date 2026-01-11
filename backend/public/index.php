<?php
declare(strict_types=1);

// Minimal PHP API example.
// Expected base path: /api

date_default_timezone_set('UTC');

$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (!getenv($k)) {
            putenv("$k=$v");
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    return $val === false ? $default : $val;
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/api#', '', $path);
$path = '/' . ltrim($path, '/');

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($path === '/health' && $method === 'GET') {
    respond([
        'status' => 'ok',
        'app' => env('APP_NAME', 'sub_users'),
        'time' => gmdate('c'),
    ]);
}

if ($path === '/echo' && $method === 'POST') {
    respond([
        'received' => jsonBody(),
        'app' => env('APP_NAME', 'sub_users'),
        'time' => gmdate('c'),
    ]);
}

respond(['error' => 'Not Found', 'path' => $path], 404);