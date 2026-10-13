<?php

namespace App\Domain;

/**
 * Supplier-country working-day engine (prototype calcDeadline / isWork / prevWork).
 * Deadlines follow the supplier country's APPROVED calendar - never a global Mon-Fri.
 * All values are epoch milliseconds; "midnight" values are UTC midnights of the local date.
 */
class Deadline
{
    public static function isWork(array $cal, int $dayMs): bool
    {
        $s = Fmt::isoD($dayMs);
        $w = (int) gmdate('w', intdiv($dayMs, 1000));   // 0 = Sunday
        if (in_array($s, $cal['makeup'], true)) {
            return true;
        }
        if (in_array($s, $cal['holidays'], true)) {
            return false;
        }
        return $w !== 0 && $w !== 6;
    }

    /** Local midnight (as UTC-midnight ms of the local calendar date) for instant $ts. */
    public static function localMid(array $cal, int $ts): int
    {
        $l = intdiv($ts + $cal['off'] * 3600000, Clock::DAY) * Clock::DAY;
        return $l;
    }

    /** @return array{ts:int}|array{err:string} deadline = closing hour of the $n-th working day after the issue day */
    public static function calc(string $key, int $issueTs, int $n): array
    {
        $cal = Masters::calendar($key);
        if (!$cal || !$cal['approved']) {
            return ['err' => "Working-day calendar “{$key}” is not approved — deadline can't be computed."];
        }
        $d = self::localMid($cal, $issueTs);
        $year = (int) gmdate('Y', intdiv($d, 1000));
        if (!in_array($year, $cal['years'], true)) {
            return ['err' => "Holiday data for {$year} not verified for {$key}."];
        }
        $c = 0;
        $g = 0;
        while ($c < $n) {
            $d += Clock::DAY;
            $y = (int) gmdate('Y', intdiv($d, 1000));
            if (!in_array($y, $cal['years'], true)) {
                return ['err' => "Holiday data for {$y} not verified for {$key} — publication blocked."];
            }
            if (self::isWork($cal, $d)) {
                $c++;
            }
            if (++$g > 60) {
                return ['err' => 'Calendar range error'];
            }
        }
        return ['ts' => $d + $cal['close'] * 3600000 - $cal['off'] * 3600000];
    }

    /** 09:00 local on the previous working day (used for "reminder" timing). */
    public static function prevWork(string $key, int $ts): int
    {
        $cal = Masters::calendar($key);
        $d = self::localMid($cal, $ts);
        $g = 0;
        do {
            $d -= Clock::DAY;
        } while (!self::isWork($cal, $d) && ++$g < 30);
        return $d + 9 * 3600000 - $cal['off'] * 3600000;
    }
}
