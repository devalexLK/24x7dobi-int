<?php
declare(strict_types=1);
define('DOBI_ROOT', realpath(defined('DOBI_CONTENT_ROOT') ? DOBI_CONTENT_ROOT : dirname(__DIR__)));
date_default_timezone_set('Asia/Colombo');
ini_set('display_errors', '0');
foreach (['ContentValidator', 'MetadataExtractor', 'DirectoryScanner', 'CacheManager', 'ContentIndex', 'SearchEngine', 'SitemapGenerator', 'Router'] as $class) {
    require __DIR__ . '/' . $class . '.php';
}
function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function safeFile(string $path): ?string
{
    $file = DOBI_ROOT . $path;
    return is_file($file) && realpath($file) === $file ? $file : null;
}
function jsonData(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
