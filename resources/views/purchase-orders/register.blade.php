@extends('layouts.app')
@section('title', 'PO Register')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('po.reg') }}">Purchase Orders</a> / PO Register
@endsection
@section('heading')PO Register
@endsection
@section('subheading')POs are generated automatically from the approved fields and emailed only when all five dispatch conditions are true. Failed checks go to the correction queue — never to the supplier.
@endsection
@section('actions')
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\PoService;
    use App\Support\Ui;
    $can = Perm::can('po.dispatch');
    $mine = $c ? $c->s['pos'] : [];
    $bad = array_values(array_filter($mine, fn ($p) => empty($p['sent']) && empty($p['frozen']) && $svc->failing($c, $p)));
    $cols = [];
    if ($can) {
        $cols[] = ['k' => 'sel', 't' => '', 'nosort' => true, 's' => fn ($r) => '', 'r' => fn ($r) => '<input type="checkbox" form="po-bulk" name="nos[]" value="' . e($r['no']) . '" ' . (empty($r['sent']) ? '' : 'disabled') . '>'];
    }
    $cols = array_merge($cols, [
        ['k' => 'no', 't' => 'PO', 's' => fn ($r) => $r['no'], 'r' => fn ($r) => '<b>' . e(PoService::full($r)) . '</b>'],
        ['k' => 'c', 't' => 'Case', 's' => fn ($r) => $r['_c']->id, 'r' => fn ($r) => e($r['_c']->id)],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => Masters::supplier($r['sid'])['name'], 'r' => fn ($r) => e(Masters::supplier($r['sid'])['name'])],
        ['k' => 'val', 't' => 'Value', 'cls' => 'r', 's' => fn ($r) => $r['total'], 'r' => fn ($r) => 'USD ' . Fmt::n0($r['total'])],
        ['k' => 'ln', 't' => 'Lines', 'cls' => 'r', 's' => fn ($r) => count($r['lines']), 'r' => fn ($r) => count($r['lines'])],
        ['k' => 'chk', 't' => 'Checks', 's' => fn ($r) => count($svc->failing($r['_c'], $r)), 'r' => function ($r) use ($svc) {
            $v = $svc->validate($r['_c'], $r);
            $tip = e(implode("\n", array_map(fn ($x) => ($x['ok'] ? '✓ ' : '✗ ') . $x['k'], $v)));
            return '<span title="' . $tip . '">' . implode(' ', array_map(fn ($x) => '<span class="pill ' . ($x['ok'] ? 'p-ok' : 'p-bad') . '" style="padding:0 6px">' . ($x['ok'] ? '✓' : '✗') . '</span>', $v)) . '</span>';
        }],
        ['k' => 'st', 't' => 'Dispatch', 's' => fn ($r) => $svc->status($r['_c'], $r), 'r' => fn ($r) => Ui::pill($svc->status($r['_c'], $r))],
        ['k' => 'ack', 't' => 'Acknowledgment', 's' => fn ($r) => $r['ack'], 'r' => fn ($r) => Ui::pill($r['ack'])],
    ]);
    $filters = [
        ['k' => 'c', 'label' => 'Case', 'get' => fn ($r) => $r['_c']->id, 'opts' => array_map(fn ($x) => $x->id, $cases)],
        ['k' => 'st', 'label' => 'Dispatch', 'get' => fn ($r) => $svc->status($r['_c'], $r), 'opts' => ['Ready to Dispatch', 'Correction queue', 'Scheduled', 'Sent', 'Delivery Failed', 'Frozen']],
    ];
    $tools = '';
    if ($can) {
        $tools = ($c ? '<button class="btn sm" type="submit" form="po-gen">Generate draft emails (' . e($c->id) . ')</button>' : '')
            . '<button class="btn sm pri" type="submit" form="po-bulk" formaction="' . e(route('po.dispatch')) . '">Dispatch selected</button>'
            . '<input type="datetime-local" name="at" form="po-bulk" value="' . e(gmdate('Y-m-d', time() + 86400)) . 'T10:00" title="Send at (Bangladesh time)" style="width:auto">'
            . '<button class="btn sm" type="submit" form="po-bulk" formaction="' . e(route('po.schedule')) . '">Schedule</button>'
            . '<button class="btn sm" type="submit" form="po-run">Run due schedule</button>';
    }
@endphp

@if ($c)
    {!! Ui::flowBar($c, 'po.reg') !!}
    @if ($c->s['appr']['status'] !== 'approved')
        {!! Ui::alert('warn', 'No approved award in the working case', 'POs are generated automatically after ED final approval.', '<a class="btn sm" href="' . e(route('aw.appr')) . '">Open approvals</a>') !!}
    @endif
@endif
@if ($bad)
    {!! Ui::alert('bad', count($bad) . ' PO(s) in the correction queue', implode('<br>', array_map(fn ($p) => '<b>' . e($p['no']) . '</b>: ' . e(implode('; ', array_column($svc->failing($c, $p), 'why'))), $bad)), '<span class="mut sm">Open the PO to fix the item.</span>') !!}
@endif

<div style="margin-top:12px">
    <x-dtable id="por" :cols="$cols" :rows="$rows" :filters="$filters" :row-link="fn ($r) => route('po.show', $r['no'])" :tools="$tools" empty="No purchase orders yet" empty-hint="Approve an award package to generate POs." />
</div>

@if ($can)
    {{-- Forms the toolbar buttons / row checkboxes attach to via the form="" attribute --}}
    <form id="po-bulk" method="post" action="{{ route('po.dispatch') }}">@csrf</form>
    <form id="po-gen" method="post" action="{{ route('po.gen') }}">@csrf @if ($c)<input type="hidden" name="case" value="{{ $c->id }}">@endif</form>
    <form id="po-run" method="post" action="{{ route('po.run') }}">@csrf</form>
@endif
@endsection
