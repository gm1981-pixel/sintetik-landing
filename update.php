<?php
/**
 * Обновление сайта sintetikmedia.ru из GitHub-репозитория gm1981-pixel/sintetik-landing.
 *
 * Запуск вручную:   https://sintetikmedia.ru/update.php?key=СЕКРЕТ
 *   &dry=1    — только показать, что изменится, ничего не записывая
 *   &force=1  — выгрузить заново, даже если этот коммит уже стоит
 * Запуск из GitHub: webhook на push (Content type: application/json, Secret = тот же СЕКРЕТ).
 *
 * Настройки и секрет — в update_config.php рядом с этим файлом (в репозиторий НЕ кладётся,
 * образец — update_config.example.php). Без него скрипт не работает.
 *
 * Что делает:
 *  1. Узнаёт последний коммит ветки через API GitHub.
 *  2. Скачивает архив этого коммита и раскладывает файлы в папку сайта.
 *     Перезаписываются только изменившиеся файлы; старая версия каждого сохраняется в .deploy/backups/.
 *  3. Удаляет с сервера только те файлы, которые раньше выгружал сам и которых больше нет в репозитории.
 *     Файлы, созданные на сервере вручную, не трогает никогда.
 *  4. Запоминает выгруженный коммит и список файлов в .deploy/manifest.json.
 *
 * Требования: PHP 7.2+, расширение zip (ZipArchive), cURL или allow_url_fopen.
 */

declare(strict_types=1);
@set_time_limit(300);
ignore_user_abort(true); // webhook GitHub ждёт ответа 10 с — выгрузка не должна обрываться
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$ROOT = __DIR__;
$DEPLOY_DIR = $ROOT . '/.deploy';

// ── Настройки ─────────────────────────────────────────────
$configFile = $ROOT . '/update_config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit("Нет update_config.php. Скопируйте update_config.example.php в update_config.php и задайте секрет.\n");
}
$cfg = array_merge([
    'secret'        => '',
    'repo'          => 'gm1981-pixel/sintetik-landing',
    'branch'        => 'main',
    'github_token'  => '',   // нужен, только если репозиторий станет приватным
    'keep_backups'  => 5,
], (array) require $configFile);

if (strlen((string) $cfg['secret']) < 24) {
    http_response_code(503);
    exit("В update_config.php не задан секрет (не короче 24 символов).\n");
}

// Никогда не выкладываются на сайт из репозитория
$EXCLUDE_EXACT = [
    '.gitignore', '.gitattributes', 'HANDOVER.md', 'README.md',
    'update_config.php', 'update_config.example.php',
];
$EXCLUDE_PREFIX = ['.git/', '.github/', '.deploy/', '.claude/', 'audit/'];
$EXCLUDE_EXT    = ['zip'];

// ── Проверка доступа: ключ в запросе или подпись webhook GitHub ──
$isWebhook = isset($_SERVER['HTTP_X_GITHUB_EVENT']);
if ($isWebhook) {
    $payload = (string) file_get_contents('php://input');
    $sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected = 'sha256=' . hash_hmac('sha256', $payload, (string) $cfg['secret']);
    if ($sig === '' || !hash_equals($expected, $sig)) {
        http_response_code(403);
        exit("Неверная подпись webhook.\n");
    }
    $event = $_SERVER['HTTP_X_GITHUB_EVENT'];
    if ($event === 'ping') {
        exit("pong\n");
    }
    $data = json_decode($payload, true);
    if ($event !== 'push' || !is_array($data) || ($data['ref'] ?? '') !== 'refs/heads/' . $cfg['branch']) {
        exit("Пропущено: событие не относится к ветке {$cfg['branch']}.\n");
    }
    $dry = false;
    $force = false;
} else {
    $key = (string) ($_REQUEST['key'] ?? '');
    if ($key === '' || !hash_equals((string) $cfg['secret'], $key)) {
        http_response_code(403);
        exit("Доступ запрещён.\n");
    }
    $dry = !empty($_REQUEST['dry']);
    $force = !empty($_REQUEST['force']);
}

// ── Служебная папка .deploy (закрыта от доступа из браузера) ──
if (!is_dir($DEPLOY_DIR) && !mkdir($DEPLOY_DIR, 0755, true)) {
    fail('Не удалось создать папку .deploy — проверьте права на запись в корень сайта.');
}
if (!is_file($DEPLOY_DIR . '/.htaccess')) {
    file_put_contents($DEPLOY_DIR . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

// Последней строкой вывода — какой коммит сейчас на сайте (как в update.php Кэби):
// видно, что приехало, а при ошибке — на чём сайт остался.
register_shutdown_function(function () use ($DEPLOY_DIR) {
    $m = @json_decode((string) @file_get_contents($DEPLOY_DIR . '/manifest.json'), true);
    echo "\nDeployed commit: ", (is_array($m) && !empty($m['sha'])
        ? substr($m['sha'], 0, 7) . ' ' . ($m['time'] ?? '') . ' ' . ($m['message'] ?? '')
        : 'не определён (сайт ещё не выгружался этим скриптом)'), "\n";
});

// Один запуск за раз
$lock = fopen($DEPLOY_DIR . '/lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    http_response_code(409);
    exit("Обновление уже выполняется.\n");
}

$manifestFile = $DEPLOY_DIR . '/manifest.json';
$manifest = is_file($manifestFile) ? (json_decode((string) file_get_contents($manifestFile), true) ?: []) : [];
$prevFiles = isset($manifest['files']) && is_array($manifest['files']) ? array_filter($manifest['files'], 'is_string') : [];

say('Репозиторий: ' . $cfg['repo'] . ', ветка ' . $cfg['branch'] . ($dry ? ' — ПРОБНЫЙ ЗАПУСК, файлы не меняются' : ''));

// ── 1. Последний коммит ветки ─────────────────────────────
$api = 'https://api.github.com/repos/' . $cfg['repo'];
$commitJson = http_get($api . '/commits/' . rawurlencode((string) $cfg['branch']), $cfg, 'application/vnd.github+json');
$commit = json_decode($commitJson, true);
if (!is_array($commit) || empty($commit['sha'])) {
    fail('GitHub не вернул коммит ветки. Ответ: ' . substr($commitJson, 0, 300));
}
$sha = (string) $commit['sha'];
$message = (string) strtok((string) ($commit['commit']['message'] ?? ''), "\n");
say('Коммит: ' . substr($sha, 0, 7) . ' — ' . $message);

if (!$force && ($manifest['sha'] ?? '') === $sha) {
    say('Этот коммит уже выгружен ' . ($manifest['time'] ?? '') . '. Обновлять нечего (для повторной выгрузки добавьте &force=1).');
    exit;
}

// ── 2. Архив коммита ──────────────────────────────────────
if (!class_exists('ZipArchive')) {
    fail('На хостинге нет расширения PHP zip (ZipArchive).');
}
$zipPath = $DEPLOY_DIR . '/download.zip';
file_put_contents($zipPath, http_get($api . '/zipball/' . $sha, $cfg, 'application/vnd.github+json'));
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fail('Скачанный архив не открывается.');
}

// Разбор архива: GitHub кладёт всё в папку «владелец-репозиторий-sha/», её отрезаем
$newFiles = [];   // относительный путь => содержимое
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    $slash = strpos($name, '/');
    if ($slash === false) continue;
    $rel = substr($name, $slash + 1);
    if ($rel === '' || substr($rel, -1) === '/') continue;           // папка
    if (!safe_path($rel)) fail('Небезопасный путь в архиве: ' . $rel);
    if (excluded($rel, $EXCLUDE_EXACT, $EXCLUDE_PREFIX, $EXCLUDE_EXT)) continue;
    if ($rel === 'update_config.php') continue;
    $content = $zip->getFromIndex($i);
    if ($content === false) fail('Не удалось прочитать из архива: ' . $rel);
    $newFiles[$rel] = $content;
}
$zip->close();
@unlink($zipPath);

if (!isset($newFiles['index.html'])) {
    fail('В архиве нет index.html — выгрузка остановлена, чтобы не сломать сайт.');
}

// ── 3. План изменений ─────────────────────────────────────
$toWrite = [];
foreach ($newFiles as $rel => $content) {
    $target = $ROOT . '/' . $rel;
    if (!is_file($target)) {
        $toWrite[$rel] = 'новый';
    } elseif (md5_file($target) !== md5($content)) {
        $toWrite[$rel] = 'изменён';
    }
}
$toDelete = [];
foreach ($prevFiles as $rel) {
    if (!isset($newFiles[$rel]) && safe_path($rel) && !excluded($rel, $EXCLUDE_EXACT, $EXCLUDE_PREFIX, $EXCLUDE_EXT)
        && $rel !== 'update.php' && is_file($ROOT . '/' . $rel)) {
        $toDelete[] = $rel;
    }
}

say('Файлов в репозитории: ' . count($newFiles) . ', к записи: ' . count($toWrite) . ', к удалению: ' . count($toDelete));
foreach ($toWrite as $rel => $kind) say("  {$kind}: {$rel}");
foreach ($toDelete as $rel) say("  удалить: {$rel}");

if ($dry) {
    say('Пробный запуск завершён, ничего не изменено.');
    exit;
}

// ── 4. Запись с резервной копией ──────────────────────────
$stamp = date('Ymd-His');
$backupDir = $DEPLOY_DIR . '/backups/' . $stamp;
$backup = function (string $rel) use ($ROOT, $backupDir) {
    $src = $ROOT . '/' . $rel;
    if (!is_file($src)) return;
    $dst = $backupDir . '/' . $rel;
    if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0755, true);
    if (!copy($src, $dst)) fail('Не удалось сохранить резервную копию: ' . $rel);
};

foreach ($toWrite as $rel => $kind) {
    $target = $ROOT . '/' . $rel;
    $backup($rel);
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
        fail('Не удалось создать папку для ' . $rel);
    }
    $tmp = $target . '.deploy-tmp';
    if (file_put_contents($tmp, $newFiles[$rel]) === false || !rename($tmp, $target)) {
        @unlink($tmp);
        fail('Не удалось записать ' . $rel . ' — проверьте права на запись.');
    }
}
foreach ($toDelete as $rel) {
    $backup($rel);
    @unlink($ROOT . '/' . $rel);
    remove_empty_dirs(dirname($ROOT . '/' . $rel), $ROOT);
}

// ── 5. Манифест и чистка старых копий ─────────────────────
file_put_contents($manifestFile, json_encode([
    'sha'     => $sha,
    'message' => $message,
    'branch'  => $cfg['branch'],
    'time'    => date('c'),
    'backup'  => (count($toWrite) || count($toDelete)) ? $stamp : null,
    'files'   => array_keys($newFiles),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$backups = glob($DEPLOY_DIR . '/backups/*', GLOB_ONLYDIR) ?: [];
sort($backups);
while (count($backups) > max(1, (int) $cfg['keep_backups'])) {
    remove_tree(array_shift($backups));
}

say('Готово: сайт обновлён до ' . substr($sha, 0, 7) . '.' . ((count($toWrite) || count($toDelete)) ? ' Прежние версии файлов — в .deploy/backups/' . $stamp : ''));

// ── Вспомогательные функции ───────────────────────────────
function say(string $line): void
{
    echo $line, "\n";
    @flush();
}

function fail(string $why): void
{
    http_response_code(500);
    say('ОШИБКА: ' . $why);
    exit;
}

function http_get(string $url, array $cfg, string $accept): string
{
    $headers = [
        'User-Agent: sintetikmedia-update',
        'Accept: ' . $accept,
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if (!empty($cfg['github_token'])) {
        $headers[] = 'Authorization: Bearer ' . $cfg['github_token'];
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) fail('Запрос к GitHub не выполнен: ' . $err);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 120,
            'follow_location' => 1, 'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) fail('Запрос к GitHub не выполнен: нет cURL, а allow_url_fopen выключен или сеть недоступна.');
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $code = (int) $m[1];
        }
    }
    if ($code !== 200) {
        fail("GitHub ответил кодом {$code} на {$url}. " . substr((string) $body, 0, 200));
    }
    return (string) $body;
}

function safe_path(string $rel): bool
{
    if ($rel === '' || $rel[0] === '/' || strpos($rel, '\\') !== false || strpos($rel, "\0") !== false) return false;
    foreach (explode('/', $rel) as $part) {
        if ($part === '' || $part === '.' || $part === '..') return false;
    }
    return true;
}

function excluded(string $rel, array $exact, array $prefixes, array $exts): bool
{
    if (in_array($rel, $exact, true)) return true;
    foreach ($prefixes as $p) {
        if (strpos($rel, $p) === 0) return true;
    }
    return in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), $exts, true);
}

function remove_empty_dirs(string $dir, string $root): void
{
    while ($dir !== $root && strpos($dir, $root) === 0 && is_dir($dir) && count((array) @scandir($dir)) === 2) {
        @rmdir($dir);
        $dir = dirname($dir);
    }
}

function remove_tree(string $dir): void
{
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}
