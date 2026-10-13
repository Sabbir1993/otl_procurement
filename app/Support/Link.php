<?php

namespace App\Support;

// Absolute links that go OUT of the app (e-mails, exports): always built from APP_URL in .env, never from the current request host.
class Link
{
    public static function to(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    /** Absolute link to a named route, using APP_URL as the base. */
    public static function route(string $name, mixed $params = []): string
    {
        return self::to(route($name, $params, false));
    }
}
