<?php

declare(strict_types=1);

/**
 * AzamPay sandbox catcher for pay.teratech.co.tz.
 * POST  → log payload, return {"status":"success"}
 * GET   → health check
 * GET ?view=1&token=… → last callbacks (JSON)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$viewToken = '9cb09f384989efce79ea3759511bcc97';
$logDir = __DIR__ . '/.azampay-logs';
$logFile = $logDir . '/callbacks.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0700, true);
}

$deny = $logDir . '/.htaccess';
if (!is_file($deny)) {
    file_put_contents($deny, "Require all denied\nDeny from all\n");
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && isset($_GET['view'])) {
    $given = (string) ($_GET['token'] ?? '');
    if (!hash_equals($viewToken, $given)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    $lines = is_file($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $entries = array_map(static fn (string $line) => json_decode($line, true), array_slice($lines, -50));
    echo json_encode(['count' => count($lines), 'latest' => array_reverse($entries)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    echo json_encode([
        'ok' => true,
        'service' => 'kasi-azampay-sandbox-callback',
        'hint' => 'AzamPay should POST here',
    ]);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders() ?: [];
}

$entry = [
    'at' => gmdate('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'headers' => $headers,
    'query' => $_GET,
    'body' => $raw,
    'json' => json_decode($raw, true),
];

file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

http_response_code(200);
echo json_encode(['status' => 'success']);
