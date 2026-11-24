<?php
declare(strict_types=1);

use Valet\Drivers\ValetDriver;

/** Route Herd and Valet requests through the same controller used on Apache. */
final class LocalValetDriver extends ValetDriver
{
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return is_file($sitePath . '/_app/bootstrap.php');
    }

    public function isStaticFile(string $sitePath, string $siteName, string $uri): string|false
    {
        return false;
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): string
    {
        return $sitePath . '/index.php';
    }
}
