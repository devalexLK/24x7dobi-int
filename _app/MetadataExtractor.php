<?php
final class MetadataExtractor
{
    public static function parse(string $html, string $path): array
    {
        $warnings = $errors = [];
        if (!mb_check_encoding($html, 'UTF-8')) {
            $errors[] = 'Save the document as UTF-8.';
        }
        $doc = new DOMDocument();
        if (!$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $errors[] = 'Supply a readable HTML document.';
        }
        $xp = new DOMXPath($doc);
        $one = static fn(string $query): string => ContentValidator::clean($xp->evaluate('string((' . $query . ')[1])'));
        $meta = static fn(string $key): string => $one('//meta[@name="' . $key . '" or @property="' . $key . '"]/@content');
        $block = $xp->query('//script[@id="dobi-metadata"]');
        $data = [];
        if ($block->length) {
            $node = $block->item(0);
            try {
                $decoded = json_decode($node->textContent, false, 16, JSON_THROW_ON_ERROR);
                if (!$decoded instanceof stdClass || $node->getAttribute('type') !== 'application/json' || $block->length !== 1) {
                    throw new RuntimeException();
                }
                $data = (array)$decoded;
            } catch (Throwable) {
                $errors[] = 'Repair the single application/json DOBI metadata block.';
            }
        }
        $field = static fn(string $key): string => ContentValidator::clean($data[$key] ?? '');
        $list = static fn(string $key): array => is_array($data[$key] ?? null)
            ? array_values(array_filter(array_map([ContentValidator::class, 'clean'], array_slice($data[$key], 0, 10)))) : [];
        $category = explode('/', $path)[1];
        if (isset($data['category']) && $field('category') !== $category) {
            $warnings[] = 'Match the metadata category to the directory.';
        }
        if (isset($data['schemaVersion']) && $data['schemaVersion'] !== 1) {
            $errors[] = 'Use metadata schemaVersion 1.';
        }
        $status = $data['status'] ?? 'published';
        if (!in_array($status, ['draft', 'archived', 'published'], true)) {
            $errors[] = 'Use draft, archived or published for status.';
        }
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $title = $field('title') ?: ($one('//title') ?: $one('//h1'));
        $summary = $field('summary') ?: ($meta('description') ?: ($meta('og:description') ?: $one('(//article//p | //main//p | //body//p)[string-length(normalize-space(.)) >= 30]')));
        if (!$title) {
            $warnings[] = 'Add an HTML title or H1.';
            $title = ucwords(str_replace(['-', '_'], ' ', preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $filename)));
        }
        if (!$summary) {
            $warnings[] = 'Add a summary or meta description.';
        }
        $dates = [];
        foreach (['published', 'updated'] as $key) {
            $dates[$key] = ContentValidator::date($field($key));
            if (array_key_exists($key, $data) && !$dates[$key]) {
                $warnings[] = 'Correct the ' . $key . ' date.';
            }
        }
        preg_match('/^(\d{4}-\d{2}-\d{2})-/', $filename, $match);
        if ($match && !ContentValidator::date($match[1])) {
            $warnings[] = 'Correct the date in the filename.';
        }
        $published = $dates['published'] ?: (ContentValidator::date($meta('article:published_time')) ?: (ContentValidator::date($one('//time[@datetime]/@datetime')) ?: ContentValidator::date($match[1] ?? '')));
        $image = '';
        foreach ([$field('image'), $meta('og:image'), $one('//img/@src')] as $candidate) {
            $local = ContentValidator::local($candidate, $path);
            if (in_array(ContentValidator::asset($local), ['png','jpg','jpeg','gif','webp','avif','svg'], true)) {
                $image = $local;
                break;
            }
        }
        $headings = [];
        foreach ($xp->query('//h2 | //h3') as $node) {
            $headings[] = ContentValidator::clean($node->textContent, 160);
            if (count($headings) >= 30) {
                break;
            }
        }
        foreach ($xp->query('//img[not(@alt)]') as $node) {
            $warnings[] = 'Supply alt text for each image, using empty alt for decorative images.';
            break;
        }
        $sources = [];
        foreach (array_slice(is_array($data['sources'] ?? null) ? $data['sources'] : [], 0, 20) as $source) {
            if ($source instanceof stdClass && ($name = ContentValidator::clean($source->name ?? '')) &&
                is_string($source->url ?? null) && filter_var($source->url, FILTER_VALIDATE_URL) &&
                in_array(parse_url($source->url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $sources[] = ['name' => $name, 'url' => $source->url];
            }
        }
        $type = $field('type') ?: 'Publication';
        if (!$sources && preg_match('/intelligence|research|report/i', $type)) {
            $warnings[] = 'List the original sources for this report.';
        }
        if ($xp->query('//base | //meta[translate(@http-equiv,"REFSH","refsh")="refresh"] | //iframe | //object | //embed | //form')->length
            || preg_match('/<\?(?:php|=)/i', $html)) {
            $errors[] = 'Remove base tags, redirects, embedded frames, objects, forms or server-side code.';
        }
        foreach ($xp->query('//img/@src | //script/@src | //link[@rel="stylesheet"]/@href | //source/@src') as $attribute) {
            $value = trim($attribute->value);
            if ($value !== '' && !preg_match('~^(?:https?:|//|data:)~i', $value) &&
                !ContentValidator::asset(ContentValidator::local($value, $path))) {
                $warnings[] = 'Check local asset: ' . ContentValidator::clean($value, 150);
            }
        }
        if ($xp->query('//script[not(@id="dobi-metadata") and not(@type="application/ld+json")]')->length) {
            $warnings[] = 'Review executable JavaScript and its dependencies before upload.';
        }
        foreach ($xp->query('//link[contains(concat(" ", translate(normalize-space(@rel), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), " "), " canonical ")]/@href') as $link) {
            if ($link->value !== $path) {
                $warnings[] = 'Remove the declared canonical. DOBI supplies the canonical URL from the file path.';
                break;
            }
        }
        $robots = $xp->evaluate('string(//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]/@content)');
        $cta = (array)($data['cta'] ?? []);
        $language = strtolower($field('language') ?: $one('//html/@lang'));
        $language = preg_match('/^(en|si|ta)(?:-[a-z]{2})?$/', $language) ? $language : 'en';
        $highlights = $list('highlights') ?: array_slice(array_filter($headings), 0, 6);
        $tags = $list('tags') ?: array_slice(array_values(array_filter(array_map('trim', explode(',', $meta('keywords'))))), 0, 10);
        $body = ContentValidator::clean($xp->evaluate('string((//article | //main | //body)[last()])'), 4000);
        $featured = ($data['featured'] ?? false) === true;
        $noindex = (bool)preg_match('/\b(?:noindex|none)\b/i', $robots . ' ' . $meta('googlebot'));
        return compact('path', 'category', 'title', 'summary', 'published', 'image', 'headings', 'sources',
            'type', 'language', 'highlights', 'tags', 'status', 'featured', 'noindex', 'body', 'warnings', 'errors')
            + ['updated' => $dates['updated'], 'cta' => ['label' => ContentValidator::clean($cta['label'] ?? ''), 'service' => ContentValidator::clean($cta['service'] ?? '')]];
    }
}
