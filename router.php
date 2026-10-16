<?php
// Router for PHP's built-in server. Missing files return 404 instead of falling back to index.php.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');

if ($path === '/favicon.ico') {
    $faviconFile = __DIR__ . '/assets/images/favicon.ico';
    if (is_file($faviconFile)) {
        header('Content-Type: image/x-icon');
        header('Cache-Control: public, max-age=3600');
        header('ETag: "' . dechex(filemtime($faviconFile)) . '-' . dechex(filesize($faviconFile)) . '"');
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
        $staticMimeTypes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'ico'   => 'image/x-icon',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'jfif'  => 'image/jpeg',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (isset($staticMimeTypes[$extension])) {
            $etag = '"' . dechex(filemtime($file)) . '-' . dechex(filesize($file)) . '"';
            header('Cache-Control: public, max-age=3600');
            header('ETag: ' . $etag);

            if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
                http_response_code(304);
                return true;
            }

            header('Content-Type: ' . $staticMimeTypes[$extension]);
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