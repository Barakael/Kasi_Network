<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/.azampay-logs')) {
    http_response_code(403);
    echo 'forbidden';
    return true;
}

if ($path === '/' || $path === '/callback.php' || $path === '/callback') {
    require __DIR__.'/callback.php';
    return true;
}

return false;
