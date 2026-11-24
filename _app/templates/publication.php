<dobi-publishing>
<nav aria-label="Publication navigation">
  <a href="/<?= h($category['slug']) ?>/"><?= h($category['name']) ?></a> / <a href="/explore/">Explore intelligence</a>
</nav>
</dobi-publishing>
<!-- DOBI:SPLIT -->
<dobi-publishing>
<?php if ($item['sources']): ?>
<div><strong>Sources</strong><ul><?php foreach ($item['sources'] as $source): ?><li><a href="<?= h($source['url']) ?>"><?= h($source['name']) ?></a></li><?php endforeach ?></ul></div>
<?php endif ?>
<?php if ($related): ?>
<div><strong>Related publications</strong><ul><?php foreach ($related as $other): ?><li><a data-dobi-event="related_publication_click" href="<?= h($other['path']) ?>"><?= h($other['title']) ?></a></li><?php endforeach ?></ul></div>
<?php endif ?>
<div><?php $ctaItem = $item; $ctaCategory = $category; require __DIR__ . '/cta.php'; ?>
<p>The first two minutes are free. We agree any fees before proceeding.</p></div>
<nav aria-label="DOBI footer"><a href="/latest/">Latest publications</a> · <a href="/privacy-policy/">Privacy</a> · <a href="/terms/">Terms</a></nav>
</dobi-publishing>
