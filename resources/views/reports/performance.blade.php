@extends('layouts.app')
@section('title', 'Savings & Supplier Performance')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('an.perf') }}">Reports</a> / Savings &amp; Performance
@endsection
@section('heading')Savings &amp; Supplier Performance
@endsection
@section('subheading')Only measured data is shown. Baselines and targets are not supplied in the BRD, so no savings or performance percentages are invented.
@endsection
@section('actions')
@endsection
@section('content')
@php
    use App\Domain\Fmt;
    use App\Support\ChartUi;
    $k = $kpi;
    $cols = [
        ['k' => 'name', 't' => 'Supplier', 's' => fn ($r) => $r['name'], 'r' => fn ($r) => '<b>' . e($r['name']) . '</b><div class="mut sm">' . e($r['id']) . '</div>'],
        ['k' => 'inv', 't' => 'RFQs received', 'cls' => 'r'],
        ['k' => 'sub', 't' => 'Quotes submitted', 'cls' => 'r'],
        ['k' => 'rate', 't' => 'Response', 'cls' => 'r', 's' => fn ($r) => $r['inv'] ? $r['sub'] / $r['inv'] : 0, 'r' => fn ($r) => ChartUi::pct($r['sub'], $r['inv'])],
        ['k' => 'sm', 't' => 'Samples', 's' => fn ($r) => $r['pass'], 'r' => fn ($r) => $r['nq'] ? $r['pass'] . ' pass · ' . $r['fail'] . ' fail' : '—'],
        ['k' => 'won', 't' => 'Lines awarded', 'cls' => 'r', 'r' => fn ($r) => $r['won'] ?: '—'],
        ['k' => 'val', 't' => 'Awarded (USD)', 'cls' => 'r', 'r' => fn ($r) => $r['val'] ? Fmt::usd($r['val']) : '—'],
    ];
    $link = ChartUi::canGo('sup/x') ? fn ($r) => route('sup.show', $r['id']) : null;
@endphp
{!! ChartUi::kpiRow([
    ['Negotiated savings', $k['savings'] ? Fmt::usd($k['savings']) : '—', ['sub' => 'material only · FX/qty excluded']],
    ['Awarded value', Fmt::usd($k['awarded']), ['sub' => 'all cases, USD']],
    ['RFQ response rate', ChartUi::pct($k['submitted'], $k['inv']), ['sub' => $k['submitted'] . ' of ' . $k['inv']]],
    ['Sample first-pass', ChartUi::pct($k['passed'], $k['evaluated']), ['sub' => 'passed ÷ evaluated']],
    ['On-time delivery · claims', 'After GRN', ['sub' => 'Import &amp; Warehouse module']],
], 5) !!}
<div style="margin-top:12px">
    <x-dtable id="perf" :cols="$cols" :rows="$rows" :row-link="$link" empty="No suppliers yet" />
</div>
@endsection
