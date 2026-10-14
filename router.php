<?php
// Router for PHP's built-in server. Missing files return 404 instead of falling back to index.php.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

if ($path === '/favicon.ico') {
    $faviconFile = __DIR__ . '/assets/images/favicon.ico';
    if (is_file($faviconFile)) {
        header('Content-Type: image/x-icon');
        readfile($faviconFile);
        return true;
    }
}

$file = realpath(__DIR__ . $path);

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

if ($file !== false && strpos($file, __DIR__) === 0) {
    if (is_file($file)) {
        // php -S does not read .htaccess, so serve .jfif as JPEG here.
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'jfif') {
            header('Content-Type: image/jpeg');
            readfile($file);
            return true;
        }
        return false; // serve static file or run the PHP script
    }
    if (is_dir($file) && is_file($file . '/index.php')) {
        require $file . '/index.php';
        return true;
    }
}

http_response_code(404);
echo '404 Not Found: ' . htmlspecialchars($path);