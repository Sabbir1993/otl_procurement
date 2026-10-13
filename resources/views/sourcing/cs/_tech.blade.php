@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\QuoteSpec;
    use App\Support\Ui;

    $p = Masters::material($pid);
    $bs = $c->bids($pid);
    $rq = $c->reqQty($pid);
@endphp
@if (!$bs)
    <div class="cp">{!! Ui::alert('warn', 'No valid quotation', 'Nothing to compare yet.') !!}</div>
@else
@php
    $fl = array_values(array_filter(QuoteSpec::fields(), fn ($f) => $f['s'] === 'l' && $f['tab'] === 'technical' && QuoteSpec::fApplies($f, $pid)));
    $rf = $c->s['rfq']['reqFields'] ?? [];
    $val = function ($b, $f) use ($c, $pid, $rf) {
        $F = ['h' => $c->hdrOf($b['sid']), 'l' => [$pid => $b['q']['spec'] ?? []]];
        $v = ($b['q']['spec'] ?? [])[$f['k']] ?? null;
        if (QuoteSpec::hasVal($v)) {
            return e(is_array($v) ? implode(', ', $v) : $v);
        }
        return QuoteSpec::fReq($rf, $f, $pid, $F) ? '<span class="pill p-bad">blank</span>' : '<span class="mut">—</span>';
    };
    $secs = [];
    foreach ($fl as $f) {
        $secs[$f['sec']][] = $f;
    }
    $span = count($bs) + 1;
    $hd = 'background:var(--bg);color:var(--mut);font-size:11px;text-transform:uppercase;letter-spacing:.5px';
@endphp
<div class="cp" style="border-bottom:1px solid var(--line)"><h3 style="padding:0;border:0">{{ $p['id'] }} · {{ $p['name'] }}</h3>
    <div class="mut sm">Technical compliance — blanks are highlighted; supplier-declared deviations and sample outcome shown below. Required for {{ Fmt::n0($rq) }} {{ $p['uom'] }}.</div></div>
<div class="tw"><table class="cs">
    <thead><tr><th>Parameter</th>@foreach ($bs as $b)<th><div>Supplier {{ $b['s']['tag'] }}</div><div class="mut" style="text-transform:none;letter-spacing:0;font-weight:500">{{ $b['s']['name'] }}</div></th>@endforeach</tr></thead>
    <tbody>
        @foreach ($secs as $name => $fields)
            <tr><td colspan="{{ $span }}" style="{{ $hd }}">{{ $name }}</td></tr>
            @foreach ($fields as $f)
                <tr><td>{{ $f['l'] }}</td>@foreach ($bs as $b)<td>{!! $val($b, $f) !!}</td>@endforeach</tr>
            @endforeach
        @endforeach
        <tr><td colspan="{{ $span }}" style="{{ $hd }}">Compliance</td></tr>
        <tr><td>Sample result</td>@foreach ($bs as $b)<td>{!! Ui::pill($b['q']['sample']) !!}</td>@endforeach</tr>
        <tr><td>Declared deviation</td>@foreach ($bs as $b)@php $d = $c->devFor($b['sid'], $pid); @endphp<td>{!! $d ? '<span class="sm" style="color:var(--warn)">⚠ ' . e($d) . '</span>' : '<span class="mut">none</span>' !!}</td>@endforeach</tr>
        <tr><td>Blank required parameters</td>@foreach ($bs as $b)@php $n = $c->techBlanks($b['sid'], $pid); @endphp<td>{!! $n ? '<span class="pill p-warn">' . count($n) . '</span> <span class="sm mut">' . e(implode(', ', $n)) . '</span>' : '<span class="pill p-ok">none</span>' !!}</td>@endforeach</tr>
    </tbody>
</table></div>
@endif
