<?php

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Formatting helpers ported 1:1 from the prototype (n0, usd, dBD, dCN ...).
 * Every timestamp in the domain is epoch MILLISECONDS (UTC).
 */
class Fmt
{
    public static function n0($n): string
    {
        return number_format((float) floor((float) $n + 0.5), 0, '.', ',');   // JS Math.round: .5 rounds up
    }

    public static function usd($n): string
    {
        return '$' . self::n0($n);
    }

    public static function usd2($n): string
    {
        return '$' . number_format((float) $n, 2, '.', '');
    }

    public static function usd3($n): string
    {
        return '$' . number_format((float) $n, 3, '.', '');
    }

    public static function pad($n, int $l = 2): string
    {
        return str_pad((string) $n, $l, '0', STR_PAD_LEFT);
    }

    /** Format epoch ms in a timezone (default Asia/Dhaka). */
    public static function tz(?int $ms, string $format, string $tz = 'Asia/Dhaka'): string
    {
        if (!$ms) {
            return '—';
        }
        return (new DateTimeImmutable('@' . intdiv($ms, 1000)))->setTimezone(new DateTimeZone($tz))->format($format);
    }

    public static function dBD(?int $ms): string   // 13 Oct 2026 14:00 BST
    {
        return $ms ? self::tz($ms, 'd M Y H:i') . ' BST' : '—';
    }

    public static function dCN(?int $ms): string   // Tue 13 Oct 2026 16:00 CST
    {
        return $ms ? self::tz($ms, 'D d M Y H:i', 'Asia/Shanghai') . ' CST' : '—';
    }

    public static function dS(?int $ms): string    // 13 Oct 14:00
    {
        return $ms ? self::tz($ms, 'd M H:i') : '—';
    }

    public static function dD(?int $ms): string    // 13 Oct 2026
    {
        return $ms ? self::tz($ms, 'd M Y') : '—';
    }

    public static function isoD(int $ms): string   // 2026-10-13 (UTC)
    {
        return gmdate('Y-m-d', intdiv($ms, 1000));
    }

    public static function bothTz(?int $ms): string
    {
        return self::dCN($ms) . ' · ' . self::tz($ms, 'H:i') . ' BST';
    }

    /** JS-compatible JSON number: integral floats print without ".0". */
    public static function jsNum($v)
    {
        if (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
            return (int) $v;
        }
        return $v;
    }

    /** Prototype hash(): djb2 over the string, 8 hex digits (used for award checksums). */
    public static function hash(string $s): string
    {
        $h = 5381;
        foreach (str_split($s) as $c) {
            $h = (($h << 5) + $h + ord($c)) & 0xFFFFFFFF;
        }
        return str_pad(dechex($h), 8, '0', STR_PAD_LEFT);
    }

    /** Status word -> pill colour class (prototype PC map). */
    public static function pillClass(string $t): string
    {
        static $map = null;
        $map ??= [
            'p-ok' => ['Accepted', 'Pass', 'Submitted', 'Approved', 'Sent', 'Matched', 'Verified', 'Complete', 'Completed', 'LC opened', 'Resolved', 'Active', 'Closed', 'Ready to Dispatch'],
            'p-warn' => ['Open', 'Pending', 'Draft', 'Draft started', 'Scheduled', 'Awaiting', 'Conditional', 'Partial', 'Frozen', 'Awaiting PI', 'PI recorded', 'Medium', 'Unverified'],
            'p-info' => ['Viewed', 'Extended', 'Waived', 'Low', 'Checks done', 'PI matched', 'LC approved'],
            'p-bad' => ['High', 'Rejected', 'Fail', 'Expired', 'Failed', 'Disputed', 'Blocked', 'Mismatch', 'Returned', 'Revoked', 'Delivery Failed', 'Correction queue'],
        ];
        foreach ($map as $cls => $words) {
            if (in_array($t, $words, true)) {
                return $cls;
            }
        }
        return '';
    }
}
