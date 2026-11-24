<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?></title>
<?= $metadata ?>
<link rel="stylesheet" href="/assets/publishing.css">
<link rel="alternate" type="application/rss+xml" title="24×7 DOBI Publications" href="/feed.xml">
<?php foreach ($schema as $entity): ?><script type="application/ld+json"><?= jsonData($entity) ?></script><?php endforeach ?>
<?= $analytics ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="hazard"><div class="wrap plate"><a class="brand" href="/">24×7 DOBI</a><?php require __DIR__ . '/nav.php' ?></div></header>
<main id="main" class="wrap"><?= $content ?></main>
<footer class="hazard"><div class="wrap plate">
  <?php $navLabel = 'Footer intelligence navigation'; require __DIR__ . '/nav.php'; ?>
  <p><a href="/privacy-policy/">Privacy</a> · <a href="/terms/">Terms</a> · <a href="https://wa.me/94712309681">WhatsApp</a> · <a href="https://t.me/ceylonsapien">Telegram</a> · <a href="/feed.xml">RSS</a></p>
</div></footer>
</body>
</html>
