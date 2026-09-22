<?php
/**
 * Прокси к «Клиентской базе» для предзаполнения формы.
 * Обходит CORS: запрос к clientbase выполняется server-to-server.
 *
 * Использование: GET cb_proxy.php?hash=XXXXXX
 * Возвращает JSON-ответ questionare.php как есть.
 */

header('Content-Type: application/json; charset=utf-8');

// Разрешаем запрос только со своего домена (защита от чужого использования прокси)
$allowed_origin = 'https://sintetikmedia.ru';
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if ($_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
        header('Access-Control-Allow-Origin: ' . $allowed_origin);
    }
}

$CLIENTBASE_URL = 'https://sintetik.clientbase.ru/questionare.php';
$CLIENTBASE_ID  = 60;

// Берём хэш из запроса
$hash = isset($_GET['hash']) ? trim($_GET['hash']) : '';

// Собираем URL к clientbase
$target = $CLIENTBASE_URL . '?clientbase_id=' . $CLIENTBASE_ID . '&ajax_call=1';
if ($hash !== '') {
    $target .= '&hash=' . urlencode($hash);
}

// Запрос server-to-server через cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'SintetikMedia-FormProxy/1.0');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Не удалось связаться с сервером базы: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}

// Отдаём ответ clientbase как есть (это уже JSON)
http_response_code($http_code ?: 200);
echo $response;
