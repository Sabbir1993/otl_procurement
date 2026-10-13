<?php

namespace App\Support;

use App\Domain\Fmt;

/**
 * Small HTML helpers for Blade views (ported from the prototype's pill(), alertBox(), kpi(), lk(), go links).
 * Everything returns an HTML string that is already escaped - print it with {!! !!}.
 */
class Ui
{
    public static function e($s): string
    {
        return e((string) ($s ?? ''));
    }

    /** Prototype link key ("src.cs" | "case/IC-2026-10" | "quote/IC-2026-10|SUP-00001") -> URL. */
    public static function url(string $link): string
    {
        $detail = ['case' => 'case.show', 'rfq' => 'rfq.show', 'quote' => 'quote.show', 'po' => 'po.show', 'pi' => 'pi.show', 'sup' => 'sup.show', 'award' => 'award.show'];
        [$k, $arg] = array_pad(explode('/', $link, 2), 2, null);
        if ($arg !== null && isset($detail[$k])) {
            return route($detail[$k], $arg);
        }
        return route($link);
    }

    public static function link(string $link, string $text, string $class = ''): string
    {
        return '<a href="' . self::e(self::url($link)) . '"' . ($class ? ' class="' . self::e($class) . '"' : '') . '>' . $text . '</a>';
    }

    public static function pill($t, ?string $cls = null): string
    {
        return '<span class="pill ' . self::e($cls ?? Fmt::pillClass((string) $t)) . '">' . self::e($t) . '</span>';
    }

    public static function mut($s): string
    {
        return '<span class="mut">' . self::e($s) . '</span>';
    }

    /** cls: info | warn | bad | ok. $body is trusted HTML. */
    public static function alert(string $cls, string $title, string $body = '', string $acts = ''): string
    {
        return '<div class="alert ' . $cls . '"><div class="t"><b>' . $title . '</b>' . $body . ($acts ? '<div class="a">' . $acts . '</div>' : '') . '</div></div>';
    }

    public static function empty(string $title, string $sub = ''): string
    {
        return '<div class="empty"><b>' . $title . '</b>' . $sub . '</div>';
    }

    /** KPI card. o: cls (warn|ok|bad), sub, href (makes it a link). */
    public static function kpi(string $label, $val, array $o = []): string
    {
        $inner = '<small>' . $label . '</small><b>' . $val . '</b>' . (isset($o['sub']) ? '<i>' . $o['sub'] . '</i>' : '');
        $cls = 'card kpi ' . ($o['cls'] ?? '');
        if (!empty($o['href'])) {
            return '<a class="' . $cls . '" href="' . self::e($o['href']) . '" style="display:block;color:inherit;text-decoration:none">' . $inner . '</a>';
        }
        return '<div class="' . $cls . '">' . $inner . '</div>';
    }

    public static function kpiRow(array $items): string
    {
        return '<div class="grid g4">' . implode('', array_map(fn ($i) => self::kpi($i[0], $i[1], $i[2] ?? []), $items)) . '</div>';
    }

    /** Description list from [[label, valueHtml], ...]. */
    public static function dl(array $rows): string
    {
        return '<dl class="dl">' . implode('', array_map(fn ($r) => '<dt>' . $r[0] . '</dt><dd>' . (($r[1] === null || $r[1] === '') ? '—' : $r[1]) . '</dd>', $rows)) . '</dl>';
    }

    /** Case flow bar (prototype flowBar). $here = route key of the current page. */
    public static function flowBar(\App\Domain\CaseBook $c, ?string $here = null): string
    {
        $h = '<div class="steps flow">';
        foreach ($c->flow() as $i => $x) {
            // div (not <a>): the .steps CSS styles `.steps div`; [data-href] makes it clickable (app.js)
            $h .= '<div class="' . ($x['st'] === 'done' ? 'done' : ($x['st'] === 'on' ? 'on' : '')) . ' ' . ($x['go'] === $here ? 'here' : '') . '" data-href="' . self::e(self::url($x['go'])) . '" title="' . self::e($x['n']) . '">' . ($x['st'] === 'done' ? '✓ ' : '') . ($i + 1) . '. ' . self::e($x['n']) . '</div>';
        }
        return $h . '</div>';
    }
}
