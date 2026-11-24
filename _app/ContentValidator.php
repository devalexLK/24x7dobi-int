<?php
final class ContentValidator
{
    public static function segment(string $value, bool $file = false): bool
    {
        return preg_match($file ? '/\A[a-zA-Z0-9][a-zA-Z0-9_-]*\.(?:html?)\z/i' : '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $value)
            && !preg_match('/\A(?:index|drafts?|private|template|backup|admin|tmp|temp|archive|vendor)(?:[-_.]|$)/i', $value);
    }

    public static function date(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?:T(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d))?\z/', $value, $parts)) {
            return '';
        }
        return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]) ? substr($value, 0, 10) : '';
    }

    public static function clean(mixed $value, int $max = 320): string
    {
        return is_string($value) ? mb_substr(trim(preg_replace('/[\s\x00-\x1f]+/u', ' ', $value) ?? ''), 0, $max) : '';
    }

    public static function local(string $value, string $from): string
    {
        $path = parse_url($value, PHP_URL_PATH);
        if (!$path || preg_match('~[:\\\\]|^//~', $value)) {
            return '';
        }
        $parts = [];
        foreach (explode('/', str_starts_with($path, '/') ? $path : dirname($from) . '/' . $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                if (!preg_match('/\A[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)?\z/', $part)) {
                    return '';
                }
                $parts[] = $part;
            }
        }
        return '/' . implode('/', $parts);
    }

    public static function asset(string $path): ?string
    {
        if (!preg_match('~\A/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(png|jpe?g|gif|webp|avif|svg|css|js|json|csv|woff2|mp4|webm|mp3|ico)\z~i', $path, $match)
            || !str_contains($path, '/assets/') || str_starts_with($path, '/_')
            || str_ends_with(strtolower($path), '/category.json')) {
            return null;
        }
        $parts = explode('/', trim($path, '/'));
        foreach (array_slice($parts, 0, -1) as $part) {
            if (!self::segment($part) || in_array($part, ['cache', 'logs', 'tests', 'examples', 'system'], true)) {
                return null;
            }
        }
        return safeFile($path) ? strtolower($match[1]) : null;
    }
}
