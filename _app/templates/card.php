<?php
$cardCategory = $categories[$item['category']];
$cardImage = $item['image'] ?: $cardCategory['image'];
$ctaItem = $item;
$ctaCategory = $cardCategory;
?>
<article class="preview" lang="<?= h($item['language']) ?>">
  <div class="preview-head">
    <?php if (in_array(ContentValidator::asset($cardImage), ['png','jpg','jpeg','gif','webp','avif','svg'], true)): ?>
    <img src="<?= h($cardImage) ?>" loading="lazy" width="76" height="62" alt="">
    <?php else: ?><span class="tile" aria-hidden="true"><?= h($cardCategory['icon'] ?: mb_substr($cardCategory['name'], 0, 2)) ?></span><?php endif ?>
    <div><div class="type"><?= h($item['type']) ?> · <a href="/<?= h($item['category']) ?>/"><?= h($cardCategory['name']) ?></a></div>
      <h2><a href="<?= h($item['path']) ?>"><?= h($item['title']) ?></a></h2>
    </div>
  </div>
  <?php if ($item['published'] || $item['updated']): ?><p class="dates">
    <?php if ($item['published']): ?>Published <time datetime="<?= h($item['published']) ?>"><?= h($item['published']) ?></time><?php endif ?>
    <?php if ($item['updated'] && $item['updated'] !== $item['published']): ?>Updated <time datetime="<?= h($item['updated']) ?>"><?= h($item['updated']) ?></time><?php endif ?>
  </p><?php endif ?>
  <?php if ($item['summary']): ?><p><?= h($item['summary']) ?></p><?php endif ?>
  <?php if ($item['highlights']): ?><ul><?php foreach (array_slice($item['highlights'], 0, 6) as $highlight): ?><li><?= h($highlight) ?></li><?php endforeach ?></ul><?php endif ?>
  <?php if ($item['tags']): ?><p class="tags"><?php foreach ($item['tags'] as $tag): ?><a href="/search/?q=<?= h(rawurlencode($tag)) ?>"><?= h($tag) ?></a><?php endforeach ?></p><?php endif ?>
  <?php if ($item['sources']): ?><p class="dates"><?= count($item['sources']) ?> <?= count($item['sources']) === 1 ? 'source' : 'sources' ?></p><?php endif ?>
  <div class="actions"><a class="primary" href="<?= h($item['path']) ?>">Open full publication</a><?php require __DIR__ . '/cta.php'; ?></div>
</article>
