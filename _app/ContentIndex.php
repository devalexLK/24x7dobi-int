<?php
final class ContentIndex
{
    public const VERSION = 1;

    public static function build(array $config, array $old, bool $force): array
    {
        $scanner = new DirectoryScanner();
        [$categories, $files] = $scanner->scan($config);
        $entries = $public = $duplicates = $warningsFromBuild = [];
        foreach ($files as $path => $fingerprint) {
            $previous = $old['entries'][$path] ?? null;
            $entry = null;
            if ($previous && !$force && $previous['fingerprint'] === $fingerprint && $fingerprint[2] < ($old['checked'] ?? 0)) {
                $entry = $previous;
            } elseif ($fingerprint[0] > $config['maxBytes'] || $fingerprint[0] === 0) {
                $entry = ['errors' => ['Upload a nonempty file within the configured file-size limit.']];
            } else {
                $file = safeFile($path);
                $html = $file ? file_get_contents($file, false, null, 0, $config['maxBytes'] + 1) : false;
                if ($html === false || strlen($html) !== $fingerprint[0]) {
                    throw new RuntimeException('Upload changed during scan. Retry after upload completes.');
                }
                $hash = hash('sha256', $html);
                $entry = !$force && ($previous['hash'] ?? '') === $hash
                    ? $previous
                    : MetadataExtractor::parse($html, $path) + ['hash' => $hash];
            }
            $entry['fingerprint'] = $fingerprint;
            $entry['canonical'] = $config['origin'] . $path;
            $entry['indexable'] = !$entry['errors'] && ($entry['status'] ?? '') === 'published' && !$entry['noindex'];
            $entries[$path] = $entry;
            if (isset($entry['hash'])) {
                if (isset($duplicates[$entry['hash']])) {
                    $warningsFromBuild[] = 'Duplicate content at ' . $path . ' and ' . $duplicates[$entry['hash']];
                }
                $duplicates[$entry['hash']] = $path;
            }
            if (!$entry['errors'] && $entry['status'] === 'published') {
                $public[$path] = $entry;
            }
        }
        $withContent = array_fill_keys(array_column($public, 'category'), true);
        $categories = array_filter($categories, static fn($category) => $category['enabled'] || isset($withContent[$category['slug']]));
        $warnings = array_merge($scanner->warnings, $warningsFromBuild);
        return compact('entries', 'public', 'categories', 'warnings') + ['checked' => time(), 'version' => self::VERSION];
    }
}
