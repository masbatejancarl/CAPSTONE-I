<?php
// Kung static file (CSS, JS, Images, etc.), direktang i-serve ito nang walang redirect
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // I-serve agad ang static file
}

// Kung PHP page o directory, i-load ang target file
if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
} elseif (file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    require __DIR__ . $path;
} else {
    // Fallback sa index/login kung walang nahanap
    http_response_code(404);
    echo "404 Not Found";
}