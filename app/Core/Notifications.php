<?php

declare(strict_types=1);

namespace App\Core;

/** Loads app/config/notifications.php once per request — see that file's docblock. */
final class Notifications
{
    /** @var array<int, array{type:string, key:string}>|null */
    private static ?array $catalog = null;

    /** @return array<int, array{type:string, key:string}> */
    public static function all(): array
    {
        return self::$catalog ??= require APP_PATH . '/config/notifications.php';
    }
}
