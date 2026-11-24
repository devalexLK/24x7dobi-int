<?php
declare(strict_types=1);
require __DIR__ . '/_app/bootstrap.php';
try {
    (new Router(require __DIR__ . '/_app/config.php'))->run();
} catch (Throwable $e) {
    error_log('DOBI request failure: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'Temporarily unavailable. Please try again shortly.';
}
