<?php

// Simple config loader. In production replace by vlucas/phpdotenv or similar.

$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'garde_app',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'url' => getenv('APP_URL') ?: 'http://localhost',
    ],
    'brevo' => [
        'api_key' => getenv('BREVO_API_KEY') ?: '',
    ],
];

return $config;
