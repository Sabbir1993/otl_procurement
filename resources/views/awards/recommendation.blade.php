@extends('layouts.app')
@section('title', 'Final Recommendation')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('aw.neg') }}">Negotiation &amp; Awards</a> / Final Recommendation
@endsection
@section('heading')Final Recommendation
@endsection
@section('subheading')A preferred supplier with written rationale and an alternative, for every product. This package goes to Marketing, Finance and ED.
@endsection
@if ($c)
    @section('actions')<span class="pill p-acc">{{ $c->id }}</span>
    @endsection
@endif

@section('content')
@php
    use App\Domain\CaseBook;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Support\Ui;
@endphp
@if (!$c)
    {!! Ui::empty('No working case', 'Create or select a purchase case first.') !!}
@elseif (empty($c->s['inv']))
    {!! Ui::flowBar($c, 'aw.rec') !!}
    {!! Ui::empty('Nothing to recommend yet', 'Complete the comparative statement first.<br><a class="btn pri" href="' . e(route('src.cs')) . '">Open comparative statement</a>') !!}
@else
@php
    $cnt = $c->csCounts();
    $A = $c->s['appr'];
    $aw = $c->lineAwards();
    $sav = $c->savings();
    $sum = fn ($rows, $k) => array_sum(array_column($rows, $k));
    $canSubmit = $cnt['pend'] === 0 && empty($c->s['csLocked']) && $A['status'] !== 'approved';
@endphp
{!! Ui::flowBar($c, 'aw.rec') !!}
{!! Ui::kpiRow([
    ['Material value', Fmt::usd($sum($aw, 'amt')), ['sub' => 'USD']],
    ['Est. landed', Fmt::usd($sum($aw, 'est'))],
    ['Suppliers', count(array_unique(array_column($aw, 'sid')))],
    ['Negotiated savings', $sav ? Fmt::usd($sav) : '—', ['sub' => 'material only']],
    ['Pending products', $cnt['pend'], ['cls' => $cnt['pend'] ? 'warn' : 'ok']],
]) !!}
<div class="card tbl" style="margin-top:12px"><div class="tw"><table>
    <thead><tr><th>Material</th><th>Recommended supplier</th><th class="r">Est. landed</th><th>Alternative</th><th>Rationale</th><th>Flags</th></tr></thead>
    <tbody>
    @foreach ($c->s['booking']['lines'] as $l)
        @php
            $pm = Masters::material($l['pid']);
            $x = $c->csOf($l['pid']);
            $ids = array_keys($x['alloc']);
        @endphp
        @if (!empty($x['excl']))
            <tr><td><b>{{ $l['pid'] }}</b><div class="mut sm">{{ $pm['name'] }}</div></td><td>{!! Ui::pill('Excluded') !!}</td><td>—</td><td>—</td><td class="sm">{{ $x['exclWhy'] }}</td><td>—</td></tr>
        @elseif (!$ids)
            <tr><td><b>{{ $l['pid'] }}</b><div class="mut sm">{{ $pm['name'] }}</div></td><td>{!! Ui::pill('Pending') !!}</td><td>—</td><td>—</td><td class="mut sm">Decide in CS</td><td>—</td></tr>
        @else
            @php
                $est = array_sum(array_map(fn ($s) => CaseBook::landed($c->s['quotes'][$s][$l['pid']]) * $x['alloc'][$s] / $c->s['quotes'][$s][$l['pid']]['qty'], $ids));
                $alt = $c->altFor($l['pid']);
                $fl = [];
                if (!empty($x['waiver'])) { $fl[] = 'MOQ waiver'; }
                if (array_filter($ids, fn ($s) => $c->s['quotes'][$s][$l['pid']]['sample'] === 'Conditional')) { $fl[] = 'Conditional sample'; }
                if (array_filter($ids, fn ($s) => $c->devFor($s, $l['pid']))) { $fl[] = 'Declared deviation'; }
                $lo = $c->lowestLanded($l['pid']);
                if ($lo && $ids[0] !== $lo) { $fl[] = 'Not lowest landed'; }
            @endphp
            <tr><td><b>{{ $l['pid'] }}</b><div class="mut sm">{{ $pm['name'] }}</div></td>
                <td>@foreach ($ids as $s)<div><b>{{ Masters::supplier($s)['name'] }}</b><div class="mut sm">{{ Fmt::n0($x['alloc'][$s]) }} {{ $pm['uom'] }} @ {{ Fmt::usd3($c->s['quotes'][$s][$l['pid']]['price']) }}</div></div>@endforeach</td>
                <td class="r">{{ Fmt::usd($est) }}</td>
                <td>@if ($alt)<span class="sm">{{ $alt['s']['name'] }}</span><div class="mut sm">{{ Fmt::usd(CaseBook::landed($alt['q'])) }} landed</div>@else<span class="mut sm">None eligible</span>@endif</td>
                <td class="sm">{{ trim($x['just'] ?? '') ?: 'Lowest landed cost' }}</td>
                <td>{!! implode(' ', array_map(fn ($f) => Ui::pill($f, 'p-warn'), $fl)) ?: '—' !!}</td></tr>
        @endif
    @endforeach
    </tbody></table></div></div>

<div class="card cp" style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="flex:1"><b>{{ $A['status'] === 'none' ? 'Not submitted' : "{$A['id']} v{$A['version']} · {$A['status']}" }}</b>
        <div class="mut sm">{{ $canSubmit ? 'Submit for sequential approval: Marketing ACK → Finance APP → ED final APP. The comparative statement locks as an immutable decision pack.' : (!empty($c->s['csLocked']) ? 'Package is in approval.' : 'Complete every product in the comparative statement first.') }}</div></div>
    @if (!empty($c->s['csLocked']) || $A['status'] === 'approved')
        <a class="btn" href="{{ route('aw.appr') }}">Open approvals</a>
    @elseif (Perm::can('cs.submit'))
        <button class="btn pri" type="button" data-open="aw-submit" @disabled(!$canSubmit)>Submit for approval…</button>
    @endif
</div>

@if (Perm::can('cs.submit') && $canSubmit)
    <x-dialog id="aw-submit" title="Submit award recommendation" :action="route('aw.submit', $c->id)" ok="Submit for approval">
        <div class="fg"><x-field name="s" label="Procurement summary / rationale" type="textarea" rows="3" full required :value="$A['summary'] ?? ''" /></div>
        <div class="alert info" style="margin-top:10px"><div class="t">The CS locks as an immutable decision pack (version + checksum). You cannot self-approve — Marketing, Finance and ED act in sequence.</div></div>
    </x-dialog>
@endif
@endif
@endsection
