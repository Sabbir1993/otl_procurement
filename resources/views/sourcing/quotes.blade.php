@extends('layouts.app')
@section('title', 'Submitted Quotations')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">Sourcing / RFQ</a> / Submitted Quotations
@endsection
@section('subheading')Immutable quotation revisions received through secure links. Only submitted revisions are evaluated; drafts are not.
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Support\Ui;
    $sups = Masters::suppliers();
    $cols = [
        ['k' => 'ref', 't' => 'Reference', 's' => fn ($r) => $r['i']['quoRef'], 'r' => fn ($r) => '<b>' . e($r['i']['quoRef']) . '</b>'],
        ['k' => 'c', 't' => 'Case', 's' => fn ($r) => $r['c']->id],
        ['k' => 's', 't' => 'Supplier', 's' => fn ($r) => $sups[$r['i']['sid']]['name'] ?? $r['i']['sid']],
        ['k' => 't', 't' => 'Submitted', 's' => fn ($r) => $r['i']['submittedAt'], 'r' => fn ($r) => e(Fmt::dS($r['i']['submittedAt']))],
        ['k' => 'rev', 't' => 'Revision', 'cls' => 'r', 's' => fn ($r) => $r['i']['rev']],
        ['k' => 'ln', 't' => 'Lines', 'cls' => 'r', 's' => fn ($r) => count($r['c']->s['quotes'][$r['i']['sid']] ?? [])],
        ['k' => 'ver', 't' => 'Contact', 's' => fn ($r) => !empty($sups[$r['i']['sid']]['emailVerified']) ? 'Verified' : 'Unverified', 'r' => fn ($r) => Ui::pill(!empty($sups[$r['i']['sid']]['emailVerified']) ? 'Verified' : 'Unverified')],
    ];
    $filters = [['k' => 'c', 'label' => 'Case', 'get' => fn ($r) => $r['c']->id, 'opts' => array_map(fn ($x) => $x->id, $cases)]];
    $draftOnly = $c ? array_values(array_filter($c->s['inv'], fn ($i) => empty($i['submitted']) && ($i['state'] ?? '') === 'Draft started')) : [];
@endphp
@if ($c){!! Ui::flowBar($c, 'src.quotes') !!}@endif
<x-dtable id="quo" :cols="$cols" :rows="$rows" :filters="$filters" :row-link="fn ($r) => route('quote.show', $r['id'])" empty="No submitted quotations yet" />
@if ($draftOnly)
    <div style="margin-top:12px">{!! Ui::alert('warn', 'Draft-only suppliers', e(implode(', ', array_map(fn ($i) => $sups[$i['sid']]['name'] ?? $i['sid'], $draftOnly))) . ' saved a draft only. Drafts are not evaluated.') !!}</div>
@endif
@endsection
