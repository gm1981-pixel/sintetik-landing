<?php
/**
 * A/B-тест главной страницы.
 *
 *   A — index.html   (прежний лендинг)
 *   B — index-b.html (новый лендинг)
 *
 * Как делится трафик:
 *  - посетитель без cookie получает вариант случайно, 50/50;
 *  - выбор запоминается в cookie sm_ab на 30 дней, чтобы человек при
 *    следующем заходе видел ту же страницу и статистика не смешивалась;
 *  - ?v=a и ?v=b принудительно открывают нужный вариант и перезаписывают cookie
 *    (для проверки и для ссылок в рекламе);
 *  - поисковым роботам всегда отдаётся вариант A, чтобы в индекс попадала
 *    одна версия страницы.
 *
 * Вариант, который увидел посетитель, уходит в Яндекс Метрику параметром
 * визита ab=a|b: он задан атрибутом data-ab у <body>, отправляет js/cookie.js.
 * В отчёте «Параметры визитов» сравниваются цели max и telegram по вариантам.
 *
 * Чтобы остановить тест: оставить в $VARIANTS только нужный файл,
 * либо удалить index.php и .htaccess — тогда снова работает index.html.
 */

$VARIANTS = ['a' => 'index.html', 'b' => 'index-b.html'];
$COOKIE = 'sm_ab';
$DAYS = 30;

$variant = null;

// 1. Явное указание в адресе
if (isset($_GET['v']) && isset($VARIANTS[$_GET['v']])) {
    $variant = $_GET['v'];
    setcookie($COOKIE, $variant, time() + $DAYS * 86400, '/');
}

// 2. Роботам — всегда A
if ($variant === null) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('~bot|crawler|spider|yandex|google|bing|mail\.ru|ahrefs|semrush|pingdom|uptime~i', $ua)) {
        $variant = 'a';
    }
}

// 3. Прежний выбор посетителя
if ($variant === null && isset($_COOKIE[$COOKIE]) && isset($VARIANTS[$_COOKIE[$COOKIE]])) {
    $variant = $_COOKIE[$COOKIE];
}

// 4. Новый посетитель — случайно, 50/50
if ($variant === null) {
    $variant = (random_int(0, 1) === 0) ? 'a' : 'b';
    setcookie($COOKIE, $variant, time() + $DAYS * 86400, '/');
}

$file = __DIR__ . '/' . $VARIANTS[$variant];
if (!is_file($file)) {                       // подстраховка: файла нет — отдаём A
    $variant = 'a';
    $file = __DIR__ . '/' . $VARIANTS['a'];
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, must-revalidate'); // иначе кэш отдаст один вариант обоим
header('Vary: Cookie');
header('X-AB-Variant: ' . $variant);          // видно в инструментах разработчика

readfile($file);
