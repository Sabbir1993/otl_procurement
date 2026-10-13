<?php

namespace App\Support;

use App\Domain\Fmt;
use App\Domain\Perm;

/** Module A rendering helpers (prototype barRows/dd + role-aware drill-down links). All outputs are escaped HTML. */
class ChartUi
{
    /** Horizontal bar rows ("Spend by supplier"). $rows = [[label, value], ...]; $fmt formats the value. */
    public static function barRows(array $rows, callable $fmt): string
    {
        $mx = max(1, ...array_map(fn ($r) => $r[1], $rows) ?: [1]);
        return implode('', array_map(fn ($r) => '<div class="hrow"><span>' . e($r[0]) . '</span><div class="bar"><i style="width:' . round($r[1] / $mx * 100) . '%"></i></div><span class="r">' . $fmt($r[1]) . '</span></div>', $rows));
    }

    /** Elapsed days between two epoch-ms values, "3.2 d" or "—" (prototype dd()). */
    public static function days(?int $a, ?int $b): string
    {
        return $a && $b ? number_format(($b - $a) / 86400000, 1, '.', '') . ' d' : '—';
    }

    public static function pct(int|float $num, int|float $den): string
    {
        return $den ? (int) floor($num / $den * 100 + 0.5) . '%' : '—';
    }

    /** Menu group key of a page route name, or null when it is not a sidebar page. */
    public static function groupOf(string $route): ?string
    {
        foreach (config('menu') as $g) {
            foreach ($g['items'] as $i) {
                if ($i['route'] === $route || in_array($route, $i['also'] ?? [], true)) {
                    return $g['key'];
                }
            }
        }
        return null;
    }

    /** May the current role open this page route (ROLE_NAV)? Unknown routes are allowed. */
    public static function canGo(string $link): bool
    {
        $route = explode('/', $link, 2)[0];
        $route = ['case' => 'case.show', 'rfq' => 'rfq.show', 'quote' => 'quote.show', 'po' => 'po.show', 'pi' => 'pi.show', 'sup' => 'sup.show', 'award' => 'award.show'][$route] ?? $link;
        $g = self::groupOf($route);
        if (!$g) {
            return true;
        }
        $page = $route;
        foreach (config('menu') as $grp) {
            foreach ($grp['items'] as $i) {
                if ($i['route'] === $route || in_array($route, $i['also'] ?? [], true)) {
                    $page = $i['route'];
                }
            }
        }
        return Perm::navAllows($g, $page);
    }

    /** Drill-down URL with the x-dtable query convention: ?{tid}[f][key]=v&{tid}[q]=text. */
    public static function drill(string $link, ?string $tid = null, array $f = [], string $q = ''): string
    {
        $url = Ui::url($link);
        if ($tid && ($f || $q !== '')) {
            $url .= '?' . http_build_query([$tid => array_filter(['f' => $f, 'q' => $q])]);
        }
        return $url;
    }

    /** KPI card; becomes a drill-down link only when the role may open the target page. */
    public static function kpi(string $label, $val, array $o = []): string
    {
        if (!empty($o['go'])) {
            if (self::canGo($o['go'])) {
                $o['href'] = self::drill($o['go'], $o['tid'] ?? null, $o['f'] ?? [], $o['q'] ?? '');
            }
        }
        return Ui::kpi($label, $val, $o);
    }

    public static function kpiRow(array $items, int $cols = 4): string
    {
        return '<div class="grid g' . min(6, max(2, $cols)) . '">' . implode('', array_map(fn ($i) => self::kpi($i[0], $i[1], $i[2] ?? []), $items)) . '</div>';
    }

    public static function sevPill(string $sev): string
    {
        return '<span class="pill ' . match ($sev) { 'bad' => 'p-bad', 'warn' => 'p-warn', 'ok' => 'p-ok', default => 'p-info' } . '">' . match ($sev) { 'bad' => 'Alert', 'warn' => 'Warning', 'ok' => 'Done', default => 'Info' } . '</span>';
    }

    public static function usd($n): string
    {
        return Fmt::usd($n);
    }
}
