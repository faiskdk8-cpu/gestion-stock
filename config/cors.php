<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'https://gestion-stock-front-seven.vercel.app',
        'https://gestion-stock-front-q76jpmy7l-yaouba.vercel.app',
    ],
    'allowed_origins_patterns' => ['https://.*\.vercel\.app'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
    'allowed_headers' => ['*'],
];