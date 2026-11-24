<?php
return [
    'origin' => 'https://24x7dobi.lk',
    'cache' => dirname(__DIR__, 2) . '/var',
    'refresh' => 60,
    'depth' => 2,
    'maxBytes' => 4 * 1024 * 1024,
    'perPage' => 12,
    'columns' => 2,
    'ga' => '',
    'redirects' => [],
    'publicationSources' => [
        'script-src' => [
            'https://cdn.tailwindcss.com/',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            'https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js',
        ],
        'style-src' => ['https://fonts.googleapis.com/css2'],
        'font-src' => [
            'https://fonts.gstatic.com/s/inter/',
            'https://fonts.gstatic.com/s/jetbrainsmono/',
        ],
    ],
];
