<nav aria-label="<?= h($navLabel ?? 'Intelligence navigation') ?>" class="dobi-links">
  <a href="/explore/">Explore intelligence</a>
  <?php foreach (array_slice($categories, 0, 4) as $navCategory): ?>
    <a href="/<?= h($navCategory['slug']) ?>/"><?= h($navCategory['name']) ?></a>
  <?php endforeach ?>
  <?php if (count($categories) > 4): ?><a href="/explore/#categories">More categories</a><?php endif ?>
  <a href="/latest/">Latest</a> <a href="/search/">Search</a>
</nav>
