<?php
final class SitemapGenerator
{
    public static function sitemap(array $index, string $origin, ?int $part = null, ?string $category = null): ?string
    {
        $paths = $category ? ['/' . $category . '/' => null] : array_fill_keys(['/', '/explore/', '/latest/', '/privacy-policy/', '/terms/'], null);
        if (!$category) {
            foreach ($index['categories'] as $slug => $settings) {
                $paths['/' . $slug . '/'] = null;
            }
        }
        foreach ($index['public'] as $item) {
            if ($item['indexable'] && (!$category || $item['category'] === $category)) {
                $paths[$item['path']] = gmdate('Y-m-d\TH:i:s\Z', $item['fingerprint'][1]);
            }
        }
        $pages = array_chunk($paths, 10000, true);
        if ($part !== null && !isset($pages[$part - 1])) {
            return null;
        }
        if (count($pages) > 1 && $part === null) {
            $prefix = $category ? '/' . $category . '/sitemap-' : '/sitemap-';
            $urls = [];
            foreach ($pages as $i => $page) {
                $urls[] = '<sitemap><loc>' . h($origin . $prefix . ($i + 1) . '.xml') . '</loc></sitemap>';
            }
            return self::xml('sitemapindex', implode('', $urls));
        }
        $rows = [];
        foreach ($pages[($part ?? 1) - 1] as $path => $date) {
            $rows[] = '<url><loc>' . h($origin . $path) . '</loc>' . ($date ? '<lastmod>' . $date . '</lastmod>' : '') . '</url>';
        }
        return self::xml('urlset', implode('', $rows));
    }

    private static function xml(string $tag, string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><' . $tag . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $body . '</' . $tag . '>';
    }

    public static function feed(array $items, string $origin, string $path): string
    {
        $channel = $path === '/feed.xml' ? '24×7 DOBI Intelligence' : '24×7 DOBI · ' . ucwords(str_replace('-', ' ', explode('/', $path)[1]));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>' . h($channel) . '</title><link>' . h($origin . dirname($path) . (dirname($path) === '/' ? '' : '/')) . '</link><description>Publications from 24×7 DOBI.</description>';
        foreach (array_slice($items, 0, 30) as $item) {
            if (!$item['indexable']) {
                continue;
            }
            $url = h($origin . $item['path']);
            $xml .= '<item><title>' . h($item['title']) . '</title><link>' . $url . '</link><guid>' . $url . '</guid><description>' . h($item['summary']) . '</description>';
            if ($item['published']) {
                $xml .= '<pubDate>' . date(DATE_RSS, strtotime($item['published'])) . '</pubDate>';
            }
            $xml .= '</item>';
        }
        return $xml . '</channel></rss>';
    }
}
