<?php
$cta = $ctaItem['cta'] ?? [];
$label = ($cta['label'] ?? '') ?: (($ctaCategory['cta']['label'] ?? '') ?: 'Request assistance');
$service = ($cta['service'] ?? '') ?: (($ctaCategory['cta']['service'] ?? '') ?: $label);
$categoryName = $ctaCategory['name'] ?? 'DOBI intelligence';
$url = $config['origin'] . ($ctaItem['path'] ?? ('/' . ($ctaCategory['slug'] ?? 'explore') . '/'));
$message = "Hi DOBI, I'd like help with " . $service . ".\nCategory: " . $categoryName .
    (isset($ctaItem['title']) ? "\nPublication: " . $ctaItem['title'] : '') . "\n" . $url;
?>
<a class="dobi-action" data-dobi-event="commercial_enquiry" data-dobi-category="<?= h($ctaCategory['slug'] ?? '') ?>" data-dobi-publication="<?= h($ctaItem['path'] ?? '') ?>" href="https://wa.me/94712309681?text=<?= h(rawurlencode($message)) ?>"><?= h($label) ?></a>
