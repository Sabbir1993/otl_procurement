<?php

namespace App\Domain;

class Clock
{
    public const DAY = 86400000;

    /** Current time as epoch milliseconds (UTC). Tests may call Clock::freeze(). */
    private static ?int $frozen = null;

    public static function ms(): int
    {
        return self::$frozen ?? (int) floor(microtime(true) * 1000);
    }

    public static function freeze(?int $ms): void
    {
        self::$frozen = $ms;
    }
}
