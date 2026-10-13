@extends('layouts.app')
@section('title', 'Negotiation History')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('aw.neg') }}">Negotiation &amp; Awards</a> / Negotiation History
@endsection
@section('heading')Negotiation History
@endsection
@section('subheading')Original quotations stay immutable. Each round records the revised commercial term, who agreed it and why.
@endsection
@section('actions')<a class="btn pri" href="{{ route('src.cs') }}">Open comparative statement</a>
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    $chg = fn ($r) => ($r['to'] - $r['from']) / $r['from'];
    $cols = [
        ['k' => 'id', 't' => 'Round', 's' => fn ($r) => $r['id'], 'r' => fn ($r) => '<b>' . e($r['id']) . '</b>'],
        ['k' => 'cid', 't' => 'Case', 's' => fn ($r) => $r['cid']],
        ['k' => 'pid', 't' => 'Material', 's' => fn ($r) => $r['pid'], 'r' => fn ($r) => e($r['pid'] . ' · ' . Masters::material($r['pid'])['name'])],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => Masters::supplier($r['sid'])['name']],
        ['k' => 'from', 't' => 'Original', 'cls' => 'r', 's' => fn ($r) => $r['from'], 'r' => fn ($r) => Fmt::usd3($r['from'])],
        ['k' => 'to', 't' => 'Revised', 'cls' => 'r', 's' => fn ($r) => $r['to'], 'r' => fn ($r) => Fmt::usd3($r['to'])],
        ['k' => 'chg', 't' => 'Change', 'cls' => 'r', 's' => $chg, 'r' => fn ($r) => '<span style="color:' . ($chg($r) <= 0 ? 'var(--ok)' : 'var(--bad)') . ';font-weight:600">' . number_format($chg($r) * 100, 1, '.', '') . '%</span>'],
        ['k' => 'why', 't' => 'Reason', 's' => fn ($r) => $r['reason'], 'r' => fn ($r) => '<span class="sm">' . e($r['reason']) . '</span>'],
        ['k' => 'by', 't' => 'By', 's' => fn ($r) => $r['by'], 'r' => fn ($r) => e($r['by'])],
    ];
@endphp
<x-dtable id="neg" :cols="$cols" :rows="$rows" empty="No negotiation rounds yet" empty-hint="Start one from a supplier column in the comparative statement." />
@endsection
