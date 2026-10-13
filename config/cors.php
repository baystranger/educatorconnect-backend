<?php

// Origins allowed to call the API with credentials. On a single-domain deploy
// (SPA and API both served from public_html) requests are same-origin and CORS
// is not involved; APP_URL is still listed so www/non-www mixes keep working.
$origins = [];
foreach ([env('FRONTEND_URL', 'http://localhost:5173'), env('FRONTEND_URL_ALTERNATE', 'http://127.0.0.1:5173'), env('APP_URL')] as $url) {
    if (! $url || ! ($host = parse_url($url, PHP_URL_HOST))) {
        continue;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
    $port = parse_url($url, PHP_URL_PORT);
    $bare = preg_replace('/^www\./', '', $host);
    foreach (str_contains($bare, '.') && ! filter_var($bare, FILTER_VALIDATE_IP) ? [$bare, "www.{$bare}"] : [$bare] as $candidate) {
        $origins[] = $scheme.'://'.$candidate.($port ? ":{$port}" : '');
    }
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique($origins)),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
