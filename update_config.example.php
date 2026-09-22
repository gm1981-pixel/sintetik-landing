<?php
/**
 * Образец настроек для update.php.
 * На сервере скопируйте этот файл в update_config.php (рядом с update.php) и впишите свой секрет.
 * update_config.php в репозиторий не добавляется (см. .gitignore) и при обновлении не перезаписывается.
 */
return [
    // Секрет для запуска: https://sintetikmedia.ru/update.php?key=СЕКРЕТ
    // и для подписи webhook GitHub. Не короче 24 символов, например 40 случайных букв и цифр.
    'secret'       => '',

    'repo'         => 'gm1981-pixel/sintetik-landing',
    'branch'       => 'main',

    // Токен GitHub (fine-grained, доступ Contents: Read-only) — только если репозиторий станет приватным.
    'github_token' => '',

    // Сколько резервных копий прошлых версий хранить в .deploy/backups
    'keep_backups' => 5,
];
