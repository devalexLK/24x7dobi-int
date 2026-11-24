<?php
final class Router
{
    private array $index = [];
    public const POLICY = "default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self' blob:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'";
    private const TYPES = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','avif'=>'image/avif','svg'=>'image/svg+xml','css'=>'text/css','js'=>'text/javascript','json'=>'application/json','csv'=>'text/csv','woff2'=>'font/woff2','ico'=>'image/x-icon','mp4'=>'video/mp4','webm'=>'video/webm','mp3'=>'audio/mpeg'];

    public function __construct(private array $config) {}

    public function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        $policy = self::POLICY;
        header('Content-Security-Policy: ' . $policy);
        $path = explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0];
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            return;
        }
        if (strlen($path) > 1024 || !preg_match('~\A/(?:[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)?/)*[a-zA-Z0-9_-]*(?:\.[a-zA-Z0-9_-]+)?\z~', $path)) {
            $this->missing();
            return;
        }
        if (in_array($path, ['/index.php','/index.html','/index.htm','/privacy-policy/index.html','/terms/index.html'], true)) {
            $this->redirect(substr($path, 0, (int)strrpos($path, '/') + 1));
            return;
        }
        $target = $this->config['redirects'][$path] ?? '';
        if (is_string($target) && $target !== $path && preg_match('~^/(?!/)[a-zA-Z0-9_./-]*$~D', $target)) {
            $this->redirect($target);
            return;
        }
        if ($path === '/robots.txt') {
            $this->send("User-agent: *\nDisallow: /_app/\nSitemap: " . $this->config['origin'] . "/sitemap.xml\n", 'text/plain');
            return;
        }
        $assetPath = $path === '/favicon.ico' ? '/assets/favicon.ico' : $path;
        if ($extension = ContentValidator::asset($assetPath)) {
            if ($extension === 'svg') {
                header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'");
            }
            $file = safeFile($assetPath);
            if ($file && ($bytes = @file_get_contents($file, false, null, 0, 16 * 1024 * 1024 + 1)) !== false && strlen($bytes) <= 16 * 1024 * 1024) {
                $this->send($bytes, self::TYPES[$extension], filemtime($file));
                return;
            }
        }
        if (in_array($path, ['/privacy-policy/', '/terms/'], true) && ($file = safeFile($path . 'index.html'))) {
            $this->send(file_get_contents($file), 'text/html', filemtime($file));
            return;
        }
        if (str_starts_with($path, '/_') || preg_match('/\.(?:php\d*|phtml|phar|json)$/i', $path)) {
            $this->missing();
            return;
        }
        $this->index = $this->liveIndex(CacheManager::get($this->config));
        $categories = $this->index['categories'];
        $slug = explode('/', $path)[1];
        $category = $categories[$slug] ?? null;
        $special = ['search', 'latest', 'explore', 'privacy-policy', 'terms'];
        if ($path === '/' . $slug && ($category || in_array($slug, $special, true))) {
            $this->redirect($path . '/');
            return;
        }
        if ($path === '/') {
            $recent = array_slice(SearchEngine::query($this->index['public'], $this->filters()), 0, 4);
            $config = $this->config;
            $analytics = $this->analytics('', '');
            $this->sendPagePolicy();
            $html = $this->render('home', compact('recent', 'categories', 'config', 'analytics'));
            $this->send($html, showShortcuts: false);
            return;
        }
        if (preg_match('~^/(?:([a-z0-9-]+)/)?sitemap(?:-([1-9][0-9]{0,8}))?\.xml$~D', $path, $match) && (empty($match[1]) || $category)) {
            $xml = SitemapGenerator::sitemap($this->index, $this->config['origin'], isset($match[2]) ? (int)$match[2] : null, ($match[1] ?? "") ?: null);
            if ($xml !== null) {
                $this->send($xml, 'application/xml');
                return;
            }
        }
        if ($path === '/feed.xml' || ($category && $path === '/' . $slug . '/feed.xml')) {
            $filters = $this->filters();
            $filters['category'] = $category ? $slug : '';
            $items = SearchEngine::query(array_filter($this->index['public'], static fn($item) => $item['indexable']), $filters);
            $this->send(SitemapGenerator::feed($items, $this->config['origin'], $path), 'application/rss+xml');
            return;
        }
        if (isset($this->index['public'][$path])) {
            $this->publication($this->index['public'][$path], $category);
            return;
        }
        if ($path === '/' . $slug . '/' && ($category || in_array($slug, ['search','latest','explore'], true))) {
            $this->listing($path, $slug, $category);
            return;
        }
        $this->missing();
    }

    private function liveIndex(array $index): array
    {
        foreach ($index['public'] as $path => $item) {
            $file = safeFile($path);
            $stat = $file ? @stat($file) : false;
            if (!$stat || [$stat['size'], $stat['mtime'], $stat['ctime'], $stat['ino']] !== $item['fingerprint']) {
                unset($index['public'][$path]);
            }
        }
        $populated = array_fill_keys(array_column($index['public'], 'category'), true);
        $index['categories'] = array_filter($index['categories'], static fn($category) => $category['enabled'] || isset($populated[$category['slug']]));
        return $index;
    }

    private function filters(bool $fromQuery = false): array
    {
        $values = [];
        foreach (['q','category','type','year','month','sort'] as $key) {
            $values[$key] = $fromQuery ? ContentValidator::clean($_GET[$key] ?? '', 120) : '';
        }
        if (!in_array($values['sort'], ['latest','oldest','relevance'], true)) {
            $values['sort'] = $values['q'] ? 'relevance' : 'latest';
        }
        return $values;
    }

    private function listing(string $path, string $slug, ?array $category): void
    {
        $filters = $this->filters(true);
        if ($category) {
            $filters['category'] = $slug;
        }
        $public = $this->index['public'];
        $scope = array_filter($public, static fn($item) => !$filters['category'] || $item['category'] === $filters['category']);
        $results = SearchEngine::query($public, $filters);
        $total = count($results);
        $pageRaw = $_GET['page'] ?? '1';
        $page = is_string($pageRaw) && preg_match('/^[1-9][0-9]{0,8}$/D', $pageRaw) ? (int)$pageRaw : 0;
        $perPage = max(1, (int)$this->config['perPage']);
        $pages = max(1, (int)ceil($total / $perPage));
        if (!$page || $page > $pages) {
            $this->missing();
            return;
        }
        $items = array_slice($results, ($page - 1) * $perPage, $perPage);
        $featured = ($slug === 'explore' && !$_GET) ? array_slice(array_values(array_filter($results, static fn($item) => $item['featured'])), 0, 4) : [];
        $heading = $category['name'] ?? match ($slug) {
            'search' => 'Search intelligence', 'latest' => 'Latest publications', default => 'Explore intelligence',
        };
        $description = $category['description'] ?? '';
        $title = ($category['seoTitle'] ?? '') ?: $heading;
        $title .= ($page > 1 ? ' · Page ' . $page : '') . ' | 24×7 DOBI';
        $canonical = $path . ($page > 1 ? '?page=' . $page : '');
        $noindex = $slug === 'search' || (bool)array_diff(array_keys($_GET), ['page']);
        $schema = [
            '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $heading,
            'mainEntity' => ['@type'=>'ItemList', 'itemListElement'=>[]],
        ];
        foreach ($items as $offset => $item) {
            $schema['mainEntity']['itemListElement'][] = ['@type'=>'ListItem', 'position'=>($page - 1) * $perPage + $offset + 1, 'url'=>$item['canonical']];
        }
        $settings = compact('slug','path','category','filters','scope','items','featured','total','page','pages','heading','description');
        $content = $this->render('listing', $settings + $this->common());
        $this->page($title, $canonical, $content, ($category['seoDescription'] ?? '') ?: $description, $noindex, [$schema], $heading, $category['slug'] ?? '', '', $category['image'] ?? '');
    }

    private function publication(array $item, array $category): void
    {
        $file = safeFile($item['path']);
        $html = $file ? @file_get_contents($file, false, null, 0, $this->config['maxBytes'] + 1) : false;
        if ($html === false || !hash_equals($item['hash'], hash('sha256', $html))) {
            $this->missing();
            return;
        }
        $related = array_filter($this->index['public'], static fn($other) => $other['path'] !== $item['path'] &&
            ($other['category'] === $item['category'] || array_intersect($item['tags'], $other['tags'])));
        usort($related, static fn($a, $b) =>
            (count(array_intersect($item['tags'], $b['tags'])) <=> count(array_intersect($item['tags'], $a['tags'])))
            ?: strcmp($b['published'], $a['published']));
        $related = array_slice($related, 0, 3);
        $chrome = $this->render('publication', compact('item', 'category', 'related') + $this->common());
        $head = $this->metadata($item['title'], $item['path'], $item['summary'], $item['noindex'], $item['image']);
        $head .= $this->analytics($item['category'], $item['path']);
        if (preg_match('/\b(?:article|report|guide|research|intelligence)\b/i', $item['type']) &&
            preg_match('/<article\b/i', $html)) {
            $schema = ['@context'=>'https://schema.org','@type'=>'Article','headline'=>$item['title'],'url'=>$item['canonical'],'inLanguage'=>$item['language']];
            foreach (['published'=>'datePublished','updated'=>'dateModified','image'=>'image'] as $key=>$property) {
                if ($item[$key]) {
                    $schema[$property] = $key === 'image' ? $this->config['origin'] . $item[$key] : $item[$key];
                }
            }
            $head .= '<script type="application/ld+json">' . jsonData($schema) . '</script>';
        }
        $hasFooter = str_contains($html, '<!-- DOBI:FOOTER -->');
        $hasHeader = str_contains($html, '<!-- DOBI:HEADER -->');
        $parts = explode('<!-- DOBI:SPLIT -->', $chrome, 2);
        if ($hasHeader || $hasFooter) {
            $html = str_replace(['<!-- DOBI:HEADER -->','<!-- DOBI:FOOTER -->'], $parts, $html);
        }
        $head = '<title>' . h($item['title']) . '</title>' . $head;
        $head .= '<script type="application/ld+json">' . jsonData([
            '@context'=>'https://schema.org', '@type'=>'BreadcrumbList', 'itemListElement'=>[
                ['@type'=>'ListItem','position'=>1,'name'=>'24×7 DOBI','item'=>$this->config['origin'] . '/'],
                ['@type'=>'ListItem','position'=>2,'name'=>$category['name'],'item'=>$this->config['origin'] . '/' . $category['slug'] . '/'],
            ],
        ]) . '</script>';
        $html = $this->integrateDocument($html, $head, $hasHeader ? '' : $parts[0], $hasFooter ? '' : $parts[1], $item['language']);
        header('Link: <' . $item['canonical'] . '>; rel="canonical"');
        if ($item['noindex']) {
            header('X-Robots-Tag: noindex');
        }
        $this->sendPagePolicy(publication: true);
        $this->send($html, 'text/html', $item['fingerprint'][1], true);
    }

    private function integrateDocument(string $html, string $head, string $headerChrome, string $footerChrome, string $language, bool $replaceMetadata = true): string
    {
        // Preserve the original bytes of scripts, styles, comments and unrelated tags.
        $pattern = '~<!--[^-]*(?:-(?!->)[^-]*)*-->|<(script|style|title|textarea)\b[^>]*>.*?</\1\s*>'
            . '|</?(?:html|head|body)\b[^>]*>|<(?:link|meta)\b(?:[^>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*>~is';
        $opened = $closed = $bodyOpened = $bodyClosed = false;
        $integrated = preg_replace_callback($pattern, static function ($match) use ($head, $headerChrome, $footerChrome, $replaceMetadata, &$opened, &$closed, &$bodyOpened, &$bodyClosed) {
            $tag = $match[0];
            if (preg_match('~^<head(?:\s|>)~i', $tag)) {
                $opened = true;
                return $tag;
            }
            if (preg_match('~^</head\b~i', $tag) && !$closed) {
                $closed = true;
                return $head . $tag;
            }
            if (!$bodyOpened && preg_match('~^<body\b~i', $tag)) {
                $bodyOpened = true;
                $missingHead = $closed ? '' : ($opened ? $head . '</head>' : '<head>' . $head . '</head>');
                $closed = true;
                return $missingHead . $tag . $headerChrome;
            }
            if (preg_match('~^</body\b~i', $tag) && !$bodyClosed) {
                $bodyClosed = true;
                return $footerChrome . $tag;
            }
            if ($replaceMetadata && $opened && !$closed && (strtolower($match[1] ?? '') === 'title' ||
                preg_match('~^<link\b[^>]*\brel\s*=\s*(?:"[^"]*\bcanonical\b[^"]*"|\x27[^\x27]*\bcanonical\b[^\x27]*\x27|canonical(?=[\s/>]))~i', $tag) ||
                preg_match('~^<meta\b[^>]*\b(?:name|property)\s*=\s*["\x27]?(?:description|og:(?:url|title|description|image)|twitter:card)(?=[\s"\x27/>])~i', $tag))) {
                return '';
            }
            return $tag;
        }, $html);
        if ($integrated === null) {
            throw new RuntimeException('Publication markup exceeds integration limits.');
        }
        if (!$closed) {
            $header = '<head>' . $head . '</head>';
            $integrated = preg_replace_callback('~<html(?:\s[^>]*)?>~i', static fn($match) => $match[0] . $header, $integrated, 1, $count);
            if (!$count) {
                return '<!doctype html><html lang="' . h($language) . '">' . $header . '<body>' . $headerChrome . $integrated . $footerChrome . '</body></html>';
            }
        }
        if (!$bodyOpened && $headerChrome) {
            $integrated = preg_replace_callback('~</head\s*>~i', static fn($match) => $match[0] . $headerChrome, $integrated, 1);
        }
        return $integrated . ($bodyClosed ? '' : $footerChrome);
    }

    private function common(): array
    {
        return ['categories'=>$this->index['categories'] ?? [], 'config'=>$this->config];
    }

    private function page(string $title, string $path, string $content, string $description = '', bool $noindex = false,
        array $schema = [], string $heading = '', string $category = '', string $publication = '', string $image = ''): void
    {
        $origin = $this->config['origin'];
        $schema[] = ['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'24×7 DOBI','url'=>$origin . '/'];
        $schema[] = ['@context'=>'https://schema.org','@type'=>'Organization','name'=>'24×7 DOBI','url'=>$origin . '/'];
        if ($heading && $path !== '/') {
            $schema[] = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
                ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$origin . '/'],
                ['@type'=>'ListItem','position'=>2,'name'=>$heading,'item'=>$origin . $path],
            ]];
        }
        $metadata = $this->metadata($title, $path, $description, $noindex, $image);
        $analytics = $this->analytics($category, $publication);
        $this->sendPagePolicy();
        $this->send($this->render('layout', compact('title','content','metadata','schema','analytics') + $this->common()));
    }

    private function metadata(string $title, string $path, string $description, bool $noindex, string $image): string
    {
        $url = h($this->config['origin'] . $path);
        $html = '<link rel="canonical" href="' . $url . '"><meta property="og:url" content="' . $url . '">'
            . '<meta property="og:title" content="' . h($title) . '"><meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">';
        if ($description) {
            $html .= '<meta name="description" content="' . h($description) . '"><meta property="og:description" content="' . h($description) . '">';
        }
        if ($image && ContentValidator::asset($image)) {
            $html .= '<meta property="og:image" content="' . h($this->config['origin'] . $image) . '">';
        }
        return $html . ($noindex ? '<meta name="robots" content="noindex,follow">' : '');
    }

    private function analytics(string $category, string $publication): string
    {
        if (!preg_match('/^G-[A-Z0-9]+$/D', $this->config['ga'])) {
            return '';
        }
        $event = $publication ? 'publication_open' : ($category ? 'category_view' : (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/search/') ? 'internal_search' : ''));
        return '<script defer src="/assets/analytics.js" data-id="' . h($this->config['ga']) . '" data-event="' . h($event) .
            '" data-category="' . h($category) . '" data-publication="' . h($publication) . '"></script>';
    }

    private function sendPagePolicy(bool $publication = false): void
    {
        $sources = $publication ? ($this->config['publicationSources'] ?? []) : [];
        if (preg_match('/^G-[A-Z0-9]+$/D', $this->config['ga'])) {
            $sources['script-src'][] = 'https://www.googletagmanager.com';
            $sources['connect-src'][] = 'https://www.google-analytics.com';
            $sources['connect-src'][] = 'https://region1.google-analytics.com';
            $sources['img-src'][] = 'https://www.google-analytics.com';
        }
        $directives = explode('; ', self::POLICY);
        foreach ($directives as &$directive) {
            $name = explode(' ', $directive, 2)[0];
            $allowed = array_filter($sources[$name] ?? [], static fn($source) =>
                is_string($source) && preg_match('~\Ahttps://[a-zA-Z0-9.-]+(?:/[a-zA-Z0-9_./@%+-]*)?\z~', $source));
            if ($allowed) {
                $directive .= ' ' . implode(' ', $allowed);
            }
        }
        unset($directive);
        header('Content-Security-Policy: ' . implode('; ', $directives));
    }

    private function render(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require __DIR__ . '/templates/' . $template . '.php';
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 301);
    }

    private function missing(): void
    {
        http_response_code(404);
        $this->page('Page unavailable | 24×7 DOBI', '/', '<h1>Page unavailable</h1><p><a href="/explore/">Browse publications</a></p>', '', true);
    }

    private function send(string $body, string $type = 'text/html', ?int $modified = null, bool $revalidate = false, bool $showShortcuts = true): void
    {
        if ($type === 'text/html') {
            $head = '<link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">'
                . '<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-dobi.png">';
            if ($showShortcuts) {
                $head .= '<link rel="stylesheet" href="/assets/chrome.css">';
            }
            $body = $this->integrateDocument($body, $head, $showShortcuts ? $this->render('shortcuts', []) : '', '', 'en', false);
        }
        header('Content-Type: ' . $type . (preg_match('~^(?:text/|application/(?:xml|rss\+xml|json))~', $type) ? '; charset=UTF-8' : ''));
        if ((http_response_code() ?: 200) < 400) {
            $etag = '"' . hash('sha256', $body) . '"';
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=' . ($revalidate ? '0, must-revalidate' : '30'));
            if ($modified !== null) {
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
            }
            $tags = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            $matches = array_map(static fn($tag) => preg_replace('/^W\//', '', trim($tag)), explode(',', $tags));
            if ($tags && (in_array($etag, $matches, true) || in_array('*', $matches, true))) {
                http_response_code(304);
                return;
            }
        } else {
            header('Cache-Control: no-store');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo $body;
        }
    }
}
