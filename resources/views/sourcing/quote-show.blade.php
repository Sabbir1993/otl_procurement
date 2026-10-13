@extends('layouts.app')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\QuoteSpec;
    use App\Support\QuoteSheet;
    use App\Support\Ui;
    $sp = Masters::supplier($sid);
    $H = $c->hdrOf($sid);
    $blanks = array_sum(array_map(fn ($pid) => count($c->techBlanks($sid, $pid)), array_keys($c->s['quotes'][$sid] ?? [])));
    $hist = $c->s['quoteHist'][$sid] ?? [];
@endphp
@section('title', $iv['quoRef'] . ' · ' . $sp['name'])
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.quotes') }}">Quotations</a> / {{ $iv['quoRef'] }}
@endsection
@section('heading'){{ $iv['quoRef'] }} · {{ $sp['name'] }}
@endsection
@section('subheading'){{ $c->id }} · {{ $c->s['rfq']['no'] }} · revision {{ $iv['rev'] }} · submitted {{ Fmt::dBD($iv['submittedAt']) }}
@endsection
@section('actions')
    <a class="btn" href="{{ route('portal', ['pCase' => $c->id, 'pSup' => $sid, 'pMode' => 'rfq']) }}">View as supplier</a>
    <button class="btn" type="button" onclick="window.print()">Print</button>
@endsection

@section('content')
{!! Ui::kpiRow([
    ['Payment', e($H['payMethod'] ?? '—'), ['sub' => e(QuoteSpec::cashOut($H + ['creditDays' => '', 'advPct' => '']))]],
    ['Incoterms', e(trim(($H['incoterm'] ?? '') . ' ' . ($H['place'] ?? '')))],
    ['Valid until', !empty($H['validity']) ? e(Fmt::dD(strtotime($H['validity'] . ' 00:00:00 UTC') * 1000)) : '—'],
    ['Bank charges', e($H['bankCh'] ?? '—')],
    ['Blank technical params', $blanks, ['cls' => $blanks ? 'warn' : 'ok']],
]) !!}
@if (preg_match('/except/i', $H['compliance'] ?? ''))
    <div style="margin-top:12px">{!! Ui::alert('warn', 'Supplier declared specification deviations', e($H['exceptions'] ?? '')) !!}</div>
@endif
<div class="card cp" style="margin-top:12px"><h3 style="padding:0 0 10px;border:0">Quotation parameters</h3>{!! QuoteSheet::render($c, $sid) !!}</div>
@if ($hist)
    <div class="card" style="margin-top:12px"><h3>Earlier revisions (immutable)</h3><div class="cp">
        @foreach (array_reverse($hist) as $h)
            <div class="sm">Revision {{ $h['rev'] }} · {{ $h['ref'] }} · submitted {{ Fmt::dBD($h['at']) }} · {{ count($h['quotes']) }} line(s) — superseded</div>
        @endforeach
    </div></div>
@endif
@endsection
