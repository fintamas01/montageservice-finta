<?php

/*
 * Router für den lokalen PHP-Testserver (bildet .htaccess nach):
 *
 *   npm run build
 *   php -S localhost:8080 -t dist router.php
 */

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$dist = __DIR__ . '/dist';

if (str_contains($path, '..')) {
    http_response_code(400);
    exit;
}

// Echte Dateien (Assets, PHP-Endpunkt) direkt ausliefern
if ($path !== '/' && is_file($dist . $path)) {
    return false;
}

// /anfrage -> /anfrage.html
$htmlFile = $dist . rtrim($path, '/') . '.html';
if ($path !== '/' && is_file($htmlFile)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($htmlFile);
    exit;
}

if ($path === '/') {
    return false;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
readfile($dist . '/404.html');
