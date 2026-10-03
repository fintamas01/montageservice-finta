<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$file = __DIR__ . '/dist' . $path;

if ($path !== '/' && file_exists($file)) {
    return false;
}

if ($path !== '/' && is_dir($file) && file_exists($file . '/index.html')) {
    return false;
}

if ($path !== '/' && !file_exists($file)) {
    http_response_code(404);
    readfile(__DIR__ . '/dist/404.html');
    exit;
}

return false;