@php
    use App\Domain\CaseBook;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Domain\QuoteSpec;
    use App\Support\Ui;

    $p = Masters::material($pid);
    $rq = $c->reqQty($pid);
    $bs = $c->bids($pid);
    $x = $c->csOf($pid);
    $stat = $c->csStat($pid);
    $alloc = $c->allocSum($pid);
    $canSel = Perm::can('cs.select');
    $canNeg = Perm::can('cs.negotiate');
@endphp
<div class="cp" style="border-bottom:1px solid var(--line)">
    <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
        <div><h3 style="padding:0;border:0">{{ $p['id'] }} · {{ $p['name'] }}</h3><div class="mut sm">{{ $p['meta'] }} · {{ $p['comp'] }}</div></div>
        <span style="flex:1"></span>
        <div style="text-align:right"><small class="mut">Required quantity</small><div class="b" style="font-size:20px">{{ Fmt::n0($rq) }} {{ $p['uom'] }}</div></div>
    </div>
    <div style="display:flex;gap:12px;align-items:center;margin-top:8px">
        <div class="bar" style="flex:1"><i style="width:{{ min(100, $alloc / max(1, $rq) * 100) }}%"></i></div>
        <span class="sm b">{{ Fmt::n0($alloc) }} / {{ Fmt::n0($rq) }} awarded</span>{!! Ui::pill($stat) !!}
    </div>
</div>

@if (!$bs)
    <div class="cp">
        @php
            $drafted = collect($c->s['inv'])->contains(fn ($i) => in_array($pid, $i['pids'], true) && empty($i['submitted']));
            $acts = '<a class="btn sm" href="' . e(route('src.track')) . '">Link admin</a><a class="btn sm" href="' . e(route('src.rfq')) . '">RFQs</a>';
            if (!empty($x['excl'])) {
                $acts .= '<span class="pill">Excluded: ' . e($x['exclWhy']) . '</span>';
            }
        @endphp
        {!! Ui::alert('warn', 'No valid quotation', ($drafted ? 'Invited supplier(s) saved a draft or did not submit.' : 'No supplier was invited (calendar not approved or no active mapping).') . ' Extend the deadline, fix the calendar, or mark the item for re-tender.', $acts) !!}
        @if (!$locked && $canSel)
            <div style="margin-top:8px">
                @if (!empty($x['excl']))
                    <x-post :action="route('cs.include', $c->id)" :params="['pid' => $pid]">Re-include</x-post>
                @else
                    <button class="btn sm warnb" type="button" data-open="cs-excl" data-fill='@json(['pid' => $pid])'>Mark for re-tender…</button>
                @endif
            </div>
        @endif
    </div>
@else
@php
    $sc = $c->scored($pid);
    $ok = array_values(array_filter($bs, fn ($b) => $c->elig($b, $pid)['ok']));
    $minL = $ok ? min(array_map(fn ($b) => CaseBook::landed($b['q']), $ok)) : null;
    $minD = $ok ? min(array_map(fn ($b) => $b['q']['days'], $ok)) : null;
    $minP = $ok ? min(array_map(fn ($b) => $b['q']['price'], $ok)) : null;
    $maxQ = $ok ? max(array_map(fn ($b) => $b['q']['q'], $ok)) : null;
    $maxI = $ok ? max(array_map(fn ($b) => $sc[$b['sid']] ?? 0, $ok)) : null;
    $isOk = fn ($b) => $c->elig($b, $pid)['ok'];
    $rankOf = fn ($b) => 1 + count(array_filter($bs, fn ($y) => $y['q']['q'] > $b['q']['q']));
    $spec = fn ($b) => $b['q']['spec'] ?? [];
    $rows = [
        ['Supplier product', fn ($b) => '<b>' . e(Masters::spName($b['sid'], $pid)) . '</b>'],
        ["Unit price (USD/{$p['uom']})", fn ($b) => Fmt::usd3($b['q']['price']) . ($b['q']['orig'] != $b['q']['price'] ? '<div class="mut sm"><s>' . Fmt::usd3($b['q']['orig']) . '</s> negotiated</div>' : ''), fn ($b) => $isOk($b) && $b['q']['price'] == $minP ? 'best' : ''],
        ['Material amount', fn ($b) => Fmt::usd($b['q']['price'] * $b['q']['qty'])],
        ['List price · tiers · discount', fn ($b) => Fmt::usd3($b['q']['listPrice'] ?? $b['q']['price'])
            . (!empty($b['q']['tiers']) ? '<div class="mut sm">' . implode(' · ', array_map(fn ($t) => '≥' . Fmt::n0($t['min']) . ': ' . Fmt::usd3($t['price']), $b['q']['tiers'])) . '</div>' : '')
            . (!empty($b['q']['disc']) ? '<div class="mut sm">− ' . e($b['q']['disc']) . '% discount</div>' : '')],
        ['Freight <span class="rb">est.</span>', fn ($b) => Fmt::usd($b['q']['freight'])],
        ['Insurance <span class="rb">est.</span>', fn ($b) => Fmt::usd($b['q']['ins'])],
        ['Duties &amp; taxes <span class="rb">est.</span>', fn ($b) => Fmt::usd($b['q']['duty'])],
        ['Bank charges <span class="rb">est.</span>', fn ($b) => Fmt::usd($b['q']['bank'])],
        ['Bank charges borne by', fn ($b) => '<span class="sm">' . e($c->hdrOf($b['sid'])['bankCh'] ?? '—') . '</span>'],
        ['<b>Total landed cost</b>', fn ($b) => '<b>' . Fmt::usd(CaseBook::landed($b['q'])) . '</b>', fn ($b) => $isOk($b) && CaseBook::landed($b['q']) == $minL ? 'best' : ''],
        ["MOQ ({$p['uom']})", fn ($b) => Fmt::n0($b['q']['moq']), fn ($b) => $b['q']['moq'] > $rq ? 'worst' : ''],
        ['Delivery (days)', fn ($b) => e($b['q']['days']), fn ($b) => $isOk($b) && $b['q']['days'] == $minD ? 'best' : ''],
        ['Production + dispatch lead', fn ($b) => '<span class="sm">' . e($spec($b)['prodDays'] ?? '?') . ' + ' . e($spec($b)['shipDays'] ?? '?') . ' days' . (!empty($spec($b)['rts']) ? '<br>ready ' . Fmt::dD(strtotime($spec($b)['rts'] . ' UTC') * 1000) : '') . '</span>'],
        ['Partial · transshipment', fn ($b) => '<span class="sm">' . e($c->hdrOf($b['sid'])['partial'] ?? '—') . ' · ' . e($c->hdrOf($b['sid'])['transship'] ?? '—') . '</span>'],
        ['Stock / capacity', fn ($b) => '<span class="sm">' . e($spec($b)['stock'] ?? '—') . '</span>'],
        ['Sample result', fn ($b) => Ui::pill($b['q']['sample'])],
        ['Quality rank', fn ($b) => '#' . $rankOf($b) . ' <span class="mut">(' . e($b['q']['q']) . ')</span>', fn ($b) => $isOk($b) && $b['q']['q'] == $maxQ ? 'best' : ''],
        ['Index score <span class="rb">illustrative</span>', fn ($b) => isset($sc[$b['sid']]) ? number_format($sc[$b['sid']], 1, '.', '') : '—', fn ($b) => isset($sc[$b['sid']]) && $sc[$b['sid']] == $maxI && $isOk($b) ? 'best' : ''],
        ['Incoterms · place', fn ($b) => e($b['q']['incoterm']) . ' ' . (($b['q']['place'] ?? '') !== '' ? e($b['q']['place']) : '⚠ missing')],
        ['Payment terms', fn ($b) => '<span class="sm">' . e($b['q']['payment']) . '</span>'],
        ['Advance exposure', function ($b) use ($c) { $a = QuoteSpec::advExposure($b['q'], $c->hdrOf($b['sid'])); return $a ? '<b>' . Fmt::usd($a) . '</b>' : '<span class="mut">none</span>'; }],
        ['Cash-out timing', fn ($b) => '<span class="sm">' . e(QuoteSpec::cashOut($c->hdrOf($b['sid']))) . '</span>'],
        ['Quality documents', function ($b) use ($spec, $p) {
            $sp = $spec($b);
            $docs = [['TDS', $sp['tds'] ?? ''], ['COA', $sp['coa'] ?? ''], ['SDS', $sp['sds'] ?? '']];
            return implode(' ', array_map(fn ($d) => $d[1] ? '<span class="pill p-ok">' . $d[0] . '</span>' : '<span class="pill p-bad">' . $d[0] . ' missing</span>', array_filter($docs, fn ($d) => $d[0] !== 'SDS' || $p['type'] !== 'fabric')));
        }],
        ['Technical parameters', function ($b) use ($c, $pid) { $n = count($c->techBlanks($b['sid'], $pid)); return $n ? '<span class="pill p-warn">' . $n . ' blank</span> <a class="sm" href="' . e(request()->fullUrlWithQuery(['tab' => 'tech'])) . '">see</a>' : '<span class="pill p-ok">complete</span>'; }],
        ['Declared deviation', function ($b) use ($c, $pid) { $d = $c->devFor($b['sid'], $pid); return $d ? '<span class="sm" style="color:var(--warn)">⚠ ' . e($d) . '</span>' : '<span class="mut">none</span>'; }],
        ['Claims & warranty', fn ($b) => '<span class="sm">' . e($c->hdrOf($b['sid'])['claims'] ?? '—') . '</span>'],
        ['Quote valid until', fn ($b) => Fmt::dD($b['q']['validity'])],
    ];
    $nj = $c->needsJust($pid);
@endphp
<div class="tw"><table class="cs">
    <thead><tr><th>Particulars</th>@foreach ($bs as $b)<th><div>Supplier {{ $b['s']['tag'] }}</div><div class="mut" style="text-transform:none;letter-spacing:0;font-weight:500">{{ $b['s']['name'] }}</div></th>@endforeach</tr></thead>
    <tbody>
        @foreach ($rows as $row)
            <tr><td>{!! $row[0] !!}</td>@foreach ($bs as $b)<td class="{{ isset($row[2]) ? $row[2]($b) : '' }}">{!! $row[1]($b) !!}</td>@endforeach</tr>
        @endforeach
        <tr><td>Award</td>
            @foreach ($bs as $b)
                @php
                    $e = $c->elig($b, $pid);
                    $a = $x['alloc'][$b['sid']] ?? 0;
                    $fill = ['pid' => $pid, 'sid' => $b['sid'], 'mode' => ($a && $a < $rq) ? 'partial' : 'full', 'qty' => ($a && $a < $rq) ? $a : '', 'waiver' => $x['waiver'][$b['sid']] ?? '', 'just' => $x['just'] ?? '',
                        'lbl' => $p['id'] . ' · ' . $b['s']['name'], 'offer' => Fmt::usd3($b['q']['price']) . '/' . $p['uom'] . ' · MOQ ' . Fmt::n0($b['q']['moq']) . ' · landed ' . Fmt::usd(CaseBook::landed($b['q'])) . ' for ' . Fmt::n0($rq) . ' ' . $p['uom'], 'warn' => $e['warn'] ?? ''];
                    $nfill = ['pid' => $pid, 'sid' => $b['sid'], 'lbl' => $p['id'] . ' · ' . $b['s']['name'], 'cur' => Fmt::usd3($b['q']['price']), 'np' => $b['q']['price']];
                @endphp
                <td class="{{ $a ? 'sel' : '' }}" style="min-width:190px">
                    @if ($e['ok'])
                        <div style="display:flex;flex-direction:column;gap:6px">
                            @if ($canSel)
                                <button class="btn sm {{ $a ? 'pri' : '' }}" type="button" @disabled($locked) data-open="cs-alloc" data-fill="{{ json_encode($fill) }}">{{ $a ? ($a == $rq ? '✓ Awarded · full qty' : '✓ ' . Fmt::n0($a) . ' ' . $p['uom'] . ' awarded') : 'Award…' }}</button>
                            @elseif ($a)
                                <span class="pill p-ok">{{ $a == $rq ? '✓ Awarded · full qty' : '✓ ' . Fmt::n0($a) . ' ' . $p['uom'] . ' awarded' }}</span>
                            @endif
                            @if (!empty($e['warn']))<span class="sm" style="color:var(--warn)">⚠ {{ $e['warn'] }}</span>@endif
                            @if ($a && array_key_exists($b['sid'], $x['waiver']))<span class="pill p-warn">MOQ waiver</span>@endif
                            @if ($canNeg)<button class="btn sm gh" type="button" @disabled($locked) data-open="cs-neg" data-fill="{{ json_encode($nfill) }}">Negotiate…</button>@endif
                            @if ($a && $canSel && !$locked)<x-post :action="route('cs.clear', $c->id)" :params="['pid' => $pid, 'sid' => $b['sid']]" class="btn sm gh" confirm="Clear this allocation?">Clear allocation</x-post>@endif
                        </div>
                    @else
                        {!! Ui::pill($e['why'], 'p-bad') !!}
                    @endif
                </td>
            @endforeach
        </tr>
    </tbody>
</table></div>
<div class="cp">
    <div class="grid g2">
        <form method="post" action="{{ route('cs.justify', $c->id) }}">@csrf<input type="hidden" name="pid" value="{{ $pid }}">
            <label class="f"><span>Award rationale / justification{{ $nj ? ' (required)' : '' }}</span>
                <textarea name="just" rows="3" @disabled($locked || !$canSel) style="{{ $nj && !trim($x['just'] ?? '') ? 'border-color:var(--bad)' : '' }}" placeholder="Preferred supplier, why, and the alternative…">{{ $x['just'] ?? '' }}</textarea>
                <small class="mut">{{ $nj ?: 'Optional when the lowest-landed-cost supplier is selected in full.' }}</small></label>
            @if (!$locked && $canSel)<button class="btn sm" type="submit">Save justification</button>@endif
        </form>
        @php
            $finEdit = Perm::can('fin.remark') && in_array($A['status'], ['fin', 'ed'], true);
        @endphp
        <div>
            @if ($finEdit)
                <form method="post" action="{{ route('cs.fin', $c->id) }}">@csrf<input type="hidden" name="pid" value="{{ $pid }}">
                    <label class="f"><span>Finance remarks (product-wise)</span><textarea name="fin" rows="3" placeholder="Finance remarks until final bulk validation…">{{ $x['fin'] ?? '' }}</textarea>
                        <small class="mut">Finance can add remarks while the package is at Finance / ED stage.</small></label>
                    <button class="btn sm" type="submit">Save remark</button>
                </form>
            @else
                <label class="f"><span>Finance remarks (product-wise)</span>
                    <div class="ins" style="margin:0">{!! !empty($x['fin']) ? e($x['fin']) : '<span class="mut">No finance remarks.</span>' !!}</div>
                    <small class="mut">Finance can add remarks while the package is at Finance / ED stage.</small></label>
            @endif
        </div>
    </div>
    @if (!empty($x['neg']))
        <div class="sm" style="margin-top:8px"><b>Negotiation history</b> — {{ implode(' · ', array_map(fn ($n) => $n['id'] . ': ' . Masters::supplier($n['sid'])['tag'] . ' ' . Fmt::usd3($n['from']) . ' → ' . Fmt::usd3($n['to']) . ' (' . $n['reason'] . ')', $x['neg'])) }}</div>
    @endif
</div>
@endif
