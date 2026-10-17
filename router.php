<?php

// роутер для встроенного сервера, только для разработки и тестов
// php -S 127.0.0.1:8080 -t public router.php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// всё, что начинается с /api, отдаём в public/index.php, как и Apache в docker/vhost.conf
if ($path === '/api' || str_starts_with($path, '/api/')) {
    require __DIR__ . '/public/index.php';
    return true;
}

$public = realpath(__DIR__ . '/public');

// статику из public отдаёт сам сервер
$file = realpath($public . rawurldecode($path));
if ($file && is_file($file) && str_starts_with($file, $public . DIRECTORY_SEPARATOR)) {
    return false;
}

// главная и swagger - это index.html в своих папках
switch ($path) {
    case '/':
        readfile($public . '/index.html');
        return true;

    case '/docs':
        header('Location: /docs/', true, 301);
        return true;

    case '/docs/':
        readfile($public . '/docs/index.html');
        return true;
}

http_response_code(404);
echo 'Not found';
