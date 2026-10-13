@extends('layouts.app')
@section('title', 'LC Register')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('imp.pi') }}">Import &amp; Warehouse</a> / LC Register
@endsection
@section('heading')LC Register
@endsection
@section('subheading')Letters of credit opened against validated PIs and approved POs. The specification does not define an LC record ID — to confirm.
@endsection
@section('actions')
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Services\PoService;
    use App\Support\Ui;
    $open = fn ($r) => $r['_c']->s['lc'][$r['no']]['open'] ?? null;
    $cols = [
        ['k' => 'no', 't' => 'PO', 's' => fn ($r) => $r['no'], 'r' => fn ($r) => '<b>' . e(PoService::full($r)) . '</b>'],
        ['k' => 'c', 't' => 'Case', 's' => fn ($r) => $r['_c']->id, 'r' => fn ($r) => e($r['_c']->id)],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => Masters::supplier($r['sid'])['name'], 'r' => fn ($r) => e(Masters::supplier($r['sid'])['name'])],
        ['k' => 'st', 't' => 'Stage', 's' => fn ($r) => $r['_c']->lcState($r), 'r' => fn ($r) => Ui::pill($r['_c']->lcState($r))],
        ['k' => 'ref', 't' => 'LC reference', 's' => fn ($r) => $open($r)['ref'] ?? '', 'r' => fn ($r) => ($o = $open($r)) ? e($o['ref']) : '—'],
        ['k' => 'amt', 't' => 'Amount (USD)', 'cls' => 'r', 's' => fn ($r) => $open($r)['amt'] ?? 0, 'r' => fn ($r) => ($o = $open($r)) ? Fmt::n0($o['amt']) : '—'],
        ['k' => 'exp', 't' => 'Expiry', 's' => fn ($r) => $open($r)['exp'] ?? 0, 'r' => fn ($r) => ($o = $open($r)) ? e(Fmt::dD($o['exp'])) : '—'],
    ];
    $filters = [['k' => 'st', 'label' => 'Stage', 'get' => fn ($r) => $r['_c']->lcState($r), 'opts' => ['Awaiting PI', 'PI recorded', 'PI matched', 'Checks done', 'LC approved', 'LC opened']]];
@endphp
<x-dtable id="lcr" :cols="$cols" :rows="$rows" :filters="$filters" :row-link="fn ($r) => route('pi.show', $r['no'])" empty="No purchase orders yet" />
@endsection
