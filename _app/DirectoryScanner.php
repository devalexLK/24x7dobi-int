<?php
final class DirectoryScanner
{
    public array $warnings = [];

    public function scan(array $config): array
    {
        $categories = $files = [];
        foreach (new DirectoryIterator(DOBI_ROOT) as $entry) {
            if ($entry->isDot() || $entry->isLink() || !$entry->isDir()) {
                continue;
            }
            $slug = $entry->getFilename();
            if (str_starts_with($slug, '.') || in_array($slug, ['_app','assets','cache','logs','system','tests','examples','var','privacy-policy','terms','search','latest','explore'], true)) {
                continue;
            }
            if (!ContentValidator::segment($slug)) {
                $this->warnings[] = 'Use a public category slug for ' . $slug;
                continue;
            }
            $settings = [];
            $settingsPath = safeFile('/' . $slug . '/category.json');
            if ($settingsPath && filesize($settingsPath) <= 32768) {
                try {
                    $decoded = json_decode(file_get_contents($settingsPath), true, 8, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) {
                        throw new RuntimeException();
                    }
                    $settings = $decoded;
                } catch (Throwable) {
                    $this->warnings[] = 'Repair category.json for ' . $slug;
                    continue;
                }
            }
            if ($settingsPath && filesize($settingsPath) > 32768) {
                $this->warnings[] = 'Reduce category.json to 32 KiB for ' . $slug;
                continue;
            }
            if (($settings['enabled'] ?? null) === false) {
                continue;
            }
            $category = ['slug' => $slug, 'enabled' => ($settings['enabled'] ?? null) === true,
                'priority' => is_int($settings['priority'] ?? null) ? $settings['priority'] : 100];
            foreach (['name','description','icon','image','seoTitle','seoDescription'] as $key) {
                $category[$key] = ContentValidator::clean($settings[$key] ?? '');
            }
            $category['name'] = $category['name'] ?: ucwords(str_replace('-', ' ', $slug));
            $category['image'] = ContentValidator::local($category['image'], '/' . $slug . '/');
            $category['cta'] = [];
            foreach (['label', 'service'] as $key) {
                $category['cta'][$key] = ContentValidator::clean($settings['cta'][$key] ?? '');
            }
            $categories[$slug] = $category;
            $this->walk('/' . $slug, $config['depth'], $files);
        }
        uasort($categories, static fn($a, $b) => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);
        ksort($files);
        return [$categories, $files];
    }

    private function walk(string $path, int $depth, array &$files): void
    {
        foreach (new DirectoryIterator(DOBI_ROOT . $path) as $entry) {
            if ($entry->isDot() || $entry->isLink()) {
                continue;
            }
            $name = $entry->getFilename();
            $relative = $path . '/' . $name;
            if ($entry->isDir() && $depth > 0 && ContentValidator::segment($name) &&
                !in_array($name, ['assets','cache','logs','system','tests','examples','var'], true)) {
                $this->walk($relative, $depth - 1, $files);
            }
            if ($entry->isFile() && ContentValidator::segment($name, true)) {
                $files[$relative] = [$entry->getSize(), $entry->getMTime(), $entry->getCTime(), $entry->getInode()];
            } elseif ($entry->isFile() && preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|asp|aspx|shtml)(?:\.|$)/i', $name)) {
                $this->warnings[] = 'Move executable file out of content: ' . $relative;
            }
        }
    }
}
