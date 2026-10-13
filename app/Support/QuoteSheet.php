<?php

namespace App\Support;

use App\Domain\CaseBook;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\QuoteSpec;

/** Complete read-only parameter sheet of a submitted quotation (prototype quoteSheet): used by the supplier receipt and the internal quotation page. Returns trusted HTML. */
class QuoteSheet
{
    public static function render(CaseBook $c, string $sid, ?array $hdr = null, ?array $quotes = null): string
    {
        $H = $hdr ?? $c->hdrOf($sid);
        $Q = $quotes ?? ($c->s['quotes'][$sid] ?? []);
        $rf = $c->s['rfq']['reqFields'] ?? [];
        $F0 = ['h' => $H, 'l' => []];
        $fields = QuoteSpec::fields();

        $show = function (array $f, $v, ?string $pid, array $F) use ($rf): string {
            $req = QuoteSpec::fReq($rf, $f, $pid ?: null, $F);
            if ($f['t'] === 'tiers') {
                return $v ? e(implode(' · ', array_map(fn ($t) => '≥ ' . Fmt::n0($t['min']) . ': ' . Fmt::usd3($t['price']), $v))) : '<span class="mut">—</span>';
            }
            if ($f['t'] === 'file') {
                return $v ? '📎 ' . e($v) : ($req ? '<span class="pill p-bad">missing</span>' : '<span class="mut">—</span>');
            }
            if (!QuoteSpec::hasVal($v)) {
                return $req ? '<span class="pill p-bad">blank</span>' : '<span class="mut">—</span>';
            }
            if ($f['k'] === 'listPrice') {
                return e(Fmt::usd3($v));
            }
            if ($f['k'] === 'disc') {
                return e($v . '%');
            }
            return e(is_array($v) ? json_encode($v) : $v);
        };
        $group = function (array $list, callable $get, ?string $pid, array $F) use ($show): string {
            $secs = [];
            foreach ($list as $f) {
                $secs[$f['sec']][] = $f;
            }
            $h = '';
            foreach ($secs as $name => $fs) {
                $h .= '<div class="sec"><h4>' . e($name) . '</h4>' . Ui::dl(array_map(fn ($f) => [e($f['l']), $show($f, $get($f), $pid, $F)], $fs)) . '</div>';
            }
            return $h;
        };

        $hf = array_values(array_filter($fields, fn ($f) => $f['s'] === 'h'));
        $out = '<div class="grid g2" style="align-items:start"><div>'
            . $group(array_filter($hf, fn ($f) => in_array($f['tab'], ['general', 'commercial'], true)), fn ($f) => $H[$f['k']] ?? null, null, $F0)
            . '</div><div>'
            . $group(array_filter($hf, fn ($f) => in_array($f['tab'], ['delivery', 'review'], true)), fn ($f) => $H[$f['k']] ?? null, null, $F0)
            . '</div></div>';

        foreach ($Q as $pid => $x) {
            $V = ($x['spec'] ?? []) + ['listPrice' => $x['listPrice'] ?? '', 'tiers' => $x['tiers'] ?? [], 'disc' => $x['disc'] ?? '', 'moq' => $x['moq'] ?? ''];
            $F = ['h' => $H, 'l' => [$pid => $V]];
            $m = Masters::material($pid);
            $lf = array_filter($fields, fn ($f) => $f['s'] === 'l' && QuoteSpec::fApplies($f, $pid));
            $get = fn ($f) => $V[$f['k']] ?? null;
            $out .= '<div class="card cp mcard" style="margin-top:12px"><div class="mh"><b>' . e($pid . ' · ' . $m['name']) . '</b><span class="mut sm">effective unit price '
                . e(Fmt::usd3($x['price'])) . ' at ' . e(Fmt::n0($x['qty']) . ' ' . $m['uom']) . ' · landed ' . e(Fmt::usd(CaseBook::landed($x))) . ' · ' . Ui::pill($x['sample'] ?? 'Pending') . '</span></div>'
                . '<div class="grid g2" style="align-items:start"><div>'
                . $group(array_filter($lf, fn ($f) => in_array($f['tab'], ['commercial', 'technical'], true)), $get, $pid, $F)
                . '</div><div>'
                . $group(array_filter($lf, fn ($f) => in_array($f['tab'], ['delivery', 'sample'], true)), $get, $pid, $F)
                . '</div></div></div>';
        }
        return $out;
    }
}
