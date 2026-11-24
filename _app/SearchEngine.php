<?php
final class SearchEngine
{
    public static function query(array $public, array $filters): array
    {
        $terms = preg_split('/\s+/u', self::fold($filters['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $scores = $results = [];
        foreach ($public as $path => $item) {
            if (($filters['category'] && $item['category'] !== $filters['category']) ||
                ($filters['type'] && $item['type'] !== $filters['type']) ||
                ($filters['year'] && substr($item['published'], 0, 4) !== $filters['year']) ||
                ($filters['month'] && substr($item['published'], 5, 2) !== $filters['month'])) {
                continue;
            }
            $score = 0;
            foreach ($terms as $term) {
                $match = 0;
                foreach (['title' => $item['title'], 'tags' => implode(' ', $item['tags']), 'category' => str_replace('-', ' ', $item['category']),
                    'headings' => implode(' ', $item['headings']), 'summary' => $item['summary'], 'body' => $item['body']] as $field => $value) {
                    if (str_contains(self::fold($value), $term)) {
                        $match += ['title' => 24, 'tags' => 8, 'category' => 6, 'headings' => 4, 'summary' => 3, 'body' => 1][$field];
                    }
                }
                if (!$match) {
                    continue 2;
                }
                $score += $match;
            }
            $scores[$path] = $score;
            $results[] = $item;
        }
        usort($results, static function ($a, $b) use ($scores, $filters) {
            return ($filters['sort'] === 'relevance' ? $scores[$b['path']] <=> $scores[$a['path']] : 0)
                ?: ((bool)$b['published'] <=> (bool)$a['published'])
                ?: (($filters['sort'] === 'oldest' ? 1 : -1) * strcmp($a['published'], $b['published']))
                ?: strcmp($a['path'], $b['path']);
        });
        return $results;
    }

    public static function fold(string $value): string
    {
        $normalized = class_exists(Normalizer::class) ? Normalizer::normalize($value) : $value;
        return mb_convert_case($normalized ?: $value, MB_CASE_FOLD, 'UTF-8');
    }
}
