<?php
final class CacheManager
{
    public static function get(array $config, bool $force = false): array
    {
        clearstatcache(true);
        $directory = $config['cache'];
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Cache directory unavailable');
        }
        $path = $directory . '/index.json';
        $read = static function () use ($path): ?array {
            $value = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
            return ($value['version'] ?? null) === ContentIndex::VERSION ? $value : null;
        };
        $old = $read();
        if (!$force && $old && time() - $old['checked'] < $config['refresh']) {
            return $old;
        }
        $lock = fopen($directory . '/index.lock', 'c');
        if (!$lock) {
            throw new RuntimeException('Cache lock unavailable');
        }
        try {
            if (!$force && $old && !flock($lock, LOCK_EX | LOCK_NB)) {
                return $old;
            }
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock cache');
            }
            $old = $read() ?? $old;
            if (!$force && $old && time() - $old['checked'] < $config['refresh']) {
                return $old;
            }
            $next = ContentIndex::build($config, $old ?? [], $force);
            $temp = tempnam($directory, 'index-');
            if (!$temp) {
                throw new RuntimeException('Cannot create cache snapshot');
            }
            try {
                $json = jsonData($next);
                if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $path)) {
                    throw new RuntimeException('Cannot commit cache snapshot');
                }
            } finally {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }
            return $next;
        } catch (Throwable $e) {
            if (!$old || $force) {
                throw $e;
            }
            error_log('DOBI index refresh failed: ' . $e->getMessage());
            return $old;
        } finally {
            fclose($lock);
        }
    }
}
