@extends('layouts.app')
@section('title', 'PI & Banking Documents')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('imp.pi') }}">Import &amp; Warehouse</a> / PI &amp; Banking Documents
@endsection
@section('heading')PI &amp; Banking Documents
@endsection
@section('subheading')Collect the supplier PI, compare it with the approved PO on ten match points, then run import checks, finance control and LC opening. Shipment, GRN and payment are out of scope.
@endsection
@section('actions')
    @if (\App\Domain\Perm::can('pi.record'))
        <button class="btn pri" type="button" data-open="pi-pick"><x-icon name="plus" :size="15" /> Record supplier PI</button>
    @endif
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Services\PoService;
    use App\Support\Ui;
    $stages = ['Awaiting PI', 'PI recorded', 'PI matched', 'Checks done', 'LC approved', 'LC opened'];
    $cols = [
        ['k' => 'no', 't' => 'PO', 's' => fn ($r) => $r['no'], 'r' => fn ($r) => '<b>' . e(PoService::full($r)) . '</b>'],
        ['k' => 'c', 't' => 'Case', 's' => fn ($r) => $r['_c']->id, 'r' => fn ($r) => e($r['_c']->id)],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => Masters::supplier($r['sid'])['name'], 'r' => fn ($r) => e(Masters::supplier($r['sid'])['name'])],
        ['k' => 'val', 't' => 'PO value', 'cls' => 'r', 's' => fn ($r) => $r['total'], 'r' => fn ($r) => 'USD ' . Fmt::n0($r['total'])],
        ['k' => 'ack', 't' => 'PO ack', 's' => fn ($r) => $r['ack'], 'r' => fn ($r) => Ui::pill($r['ack'])],
        ['k' => 'pi', 't' => 'PI', 's' => fn ($r) => $r['_c']->s['pis'][$r['no']]['no'] ?? '', 'r' => function ($r) {
            $pi = $r['_c']->s['pis'][$r['no']] ?? null;
            return $pi ? e($pi['no']) . ' <span class="mut sm">rev ' . e($pi['ver']) . '</span>' : '—';
        }],
        ['k' => 'm', 't' => 'Match', 's' => fn ($r) => !empty($r['_c']->s['pis'][$r['no']]['approved']) ? 'Approved' : '', 'r' => function ($r) use ($pis) {
            $pi = $r['_c']->s['pis'][$r['no']] ?? null;
            return $pi ? (!empty($pi['approved']) ? Ui::pill('Approved', 'p-ok') : ($pis->ok($r['_c'], $r, $pi) ? Ui::pill('Matched', 'p-info') : Ui::pill('Mismatch'))) : '—';
        }],
        ['k' => 'lc', 't' => 'LC', 's' => fn ($r) => $r['_c']->lcState($r), 'r' => fn ($r) => Ui::pill($r['_c']->lcState($r))],
    ];
    $filters = [
        ['k' => 'c', 'label' => 'Case', 'get' => fn ($r) => $r['_c']->id, 'opts' => array_map(fn ($x) => $x->id, $cases)],
        ['k' => 'lc', 'label' => 'Stage', 'get' => fn ($r) => $r['_c']->lcState($r), 'opts' => $stages],
    ];
    $accepted = array_values(array_filter($rows, fn ($p) => $p['ack'] === 'Accepted'));
@endphp
@if ($c)
    {!! Ui::flowBar($c, 'imp.pi') !!}
@endif
<x-dtable id="pil" :cols="$cols" :rows="$rows" :filters="$filters" :row-link="fn ($r) => route('pi.show', $r['no'])" empty="No purchase orders yet" />

@if (\App\Domain\Perm::can('pi.record'))
    <x-dialog id="pi-pick" title="Record supplier PI" :action="route('pi.pick')" ok="Continue" data-open-on-new>
        @if ($accepted)
            <div class="fg"><x-field name="no" label="Purchase order" type="select" :options="array_map(fn ($p) => [$p['no'], $p['no'] . ' · ' . Masters::supplier($p['sid'])['name']], $accepted)" full /></div>
        @else
            <p class="mut">No accepted POs yet — PI starts after supplier acceptance.</p>
        @endif
    </x-dialog>
@endif
@endsection
