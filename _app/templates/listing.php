<nav class="crumb" aria-label="Breadcrumb"><a href="/">Home</a> / <span aria-current="page"><?= h($heading) ?></span></nav>
<h1><?= h($heading) ?></h1>
<?php if ($description): ?><p><?= h($description) ?></p><?php endif ?>
<?php if ($slug === 'explore' && $categories): ?>
<section id="categories"><h2>Categories</h2><ul class="category-list">
  <?php foreach ($categories as $entry): ?><li><a href="/<?= h($entry['slug']) ?>/"><?= h($entry['name']) ?></a></li><?php endforeach ?>
</ul></section>
<?php endif ?>
<?php if ($featured): ?><section><h2>Featured</h2><div class="cards cols-<?= (int)$config['columns'] ?>">
  <?php foreach ($featured as $item) { require __DIR__ . '/card.php'; } ?>
</div></section><?php endif ?>
<?php
$types = array_values(array_unique(array_column($scope, 'type')));
sort($types, SORT_NATURAL | SORT_FLAG_CASE);
$years = array_values(array_unique(array_filter(array_map(static fn($item) => substr($item['published'], 0, 4), $scope))));
rsort($years);
$months = [];
for ($month = 1; $month <= 12; $month++) {
    $months[sprintf('%02d', $month)] = date('F', mktime(12, 0, 0, $month, 1, 2000));
}
$controls = [
    'type'=>['Type', array_combine($types, $types)],
    'year'=>['Year', array_combine($years, $years)],
    'month'=>['Month', $months],
    'sort'=>['Sort', ['relevance'=>'Relevance', 'latest'=>'Latest', 'oldest'=>'Oldest']],
];
if (!$category) {
    $controls = ['category'=>['Category', array_column($categories, 'name', 'slug')]] + $controls;
}
?>
<form class="filters" method="get" action="<?= h($path) ?>" role="search">
<label>Search <input type="search" name="q" maxlength="120" value="<?= h($filters['q']) ?>"></label>
<?php foreach ($controls as $key => [$label, $options]): ?>
<label><?= h($label) ?><select name="<?= h($key) ?>">
<?php if ($key !== 'sort'): ?><option value="">All</option><?php endif ?>
<?php foreach ($options as $value => $name): ?><option value="<?= h($value) ?>"<?= (string)$value === $filters[$key] ? ' selected' : '' ?>><?= h($name) ?></option><?php endforeach ?>
</select></label>
<?php endforeach ?>
<button type="submit">Apply filters</button>
<?php if ($_GET): ?><a href="<?= h($path) ?>">Clear</a><?php endif ?>
</form>
<p><?= $total ?> <?= $total === 1 ? 'publication' : 'publications' ?></p>
<?php if (!$items): ?><p>No publications match these filters.</p><?php endif ?>
<div class="cards cols-<?= (int)$config['columns'] ?>">
<?php foreach ($items as $item) { require __DIR__ . '/card.php'; } ?>
</div>
<?php if ($pages > 1):
$links = array_filter($filters);
if ($category) { unset($links['category']); }
if ($links['sort'] === ($filters['q'] ? 'relevance' : 'latest')) { unset($links['sort']); }
$pageUrl = static fn($n) => $path . (($query = http_build_query($links + ($n > 1 ? ['page'=>$n] : []))) ? '?' . $query : '');
?>
<nav class="pagination" aria-label="Publication pages">
<?php if ($page > 1): ?><a rel="prev" href="<?= h($pageUrl($page - 1)) ?>">Previous</a><?php endif ?>
<span aria-current="page">Page <?= $page ?> of <?= $pages ?></span>
<?php if ($page < $pages): ?><a rel="next" href="<?= h($pageUrl($page + 1)) ?>">Next</a><?php endif ?>
</nav>
<?php endif ?>
<?php if ($category): ?>
<aside class="assistance"><h2>Need local assistance?</h2><p>The first two minutes are free. We agree any fees before proceeding.</p>
<?php $ctaItem = []; $ctaCategory = $category; require __DIR__ . '/cta.php'; ?>
 <a href="/<?= h($slug) ?>/feed.xml">Category RSS</a>
</aside>
<?php endif ?>
