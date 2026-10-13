@extends('layouts.app')
@section('title', 'Requisition Board')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('rq.board') }}">Requisitions</a> / Requisition Board
@endsection
@section('heading')Requisition Board
@endsection
@section('subheading')Consolidated demand by buyer × customer × material. Accept or reject each line (rejections need a reason), then validate and freeze to open the booking.
@endsection
@section('actions')@if ($c)<span class="pill p-acc">{{ $c->id }} · {{ $c->month }}</span>@endif
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Perm;
    use App\Support\Ui;
@endphp
@if (!$c)
    {!! Ui::empty('No purchase case yet', 'Create a purchase case first.') !!}
@else
@php
    $D = $svc->lines($c);   // all demand access goes through RequisitionService
    $st = $svc->stats($c);
    $acc = $st['accepted']; $rej = $st['rejected']; $pen = $st['pending'];
    $frozen = (bool) $c->s['frozen'];
    $canEdit = !$frozen && Perm::can('demand.edit');
    $canFreeze = !$frozen && Perm::can('demand.freeze');
    $stLabel = fn ($d) => $d['status'] === 'accepted' ? 'Accepted' : ($d['status'] === 'rejected' ? 'Rejected' : 'Pending');
    $post = fn ($url, $label, $cls = 'btn sm', $method = 'post', $confirm = null) => '<form method="post" action="' . e($url) . '" style="display:inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . ($method !== 'post' ? method_field($method) : '') . '<button type="submit" class="' . $cls . '">' . $label . '</button></form>';
    $cols = [];
    if ($canEdit) {
        $cols[] = ['k' => 'sel', 't' => '<input type="checkbox" title="Select all on this page" onclick="var c=this.checked;document.getElementsByName(&quot;ids[]&quot;).forEach(function(x){x.checked=c})">', 'nosort' => true,
            's' => fn ($r) => '', 'r' => fn ($r) => '<input type="checkbox" name="ids[]" value="' . e($r['id']) . '" form="bulk-form">'];
    }
    $cols = array_merge($cols, [
        ['k' => 'id', 't' => 'Line', 'r' => fn ($r) => '<b>' . e($r['id']) . '</b>'],
        ['k' => 'pid', 't' => 'Material', 's' => fn ($r) => $r['pid'] . $mats[$r['pid']]['name'], 'r' => fn ($r) => '<b>' . e($r['pid']) . '</b> <span class="mut sm">' . e($mats[$r['pid']]['name'] ?? '') . '</span>'],
        ['k' => 'buyer', 't' => 'Buyer', 's' => fn ($r) => $r['buyer'] . $r['customer'], 'r' => fn ($r) => e($r['buyer']) . '<div class="mut sm">' . e($r['customer']) . '</div>'],
        ['k' => 'mkt', 't' => 'Marketing', 's' => fn ($r) => $r['mkt']],
        ['k' => 'qty', 't' => 'Demand', 'cls' => 'r', 's' => fn ($r) => $r['qty'], 'r' => fn ($r) => Fmt::n0($r['qty']) . ' ' . e($mats[$r['pid']]['uom'] ?? '')],
        ['k' => 'status', 't' => 'Status', 's' => fn ($r) => $r['status'], 'r' => fn ($r) => Ui::pill($stLabel($r)) . (!empty($r['reason']) ? '<div class="mut sm">' . e($r['reason']) . '</div>' : '')],
    ]);
    $filters = [
        ['k' => 'status', 'label' => 'Status', 'get' => fn ($r) => $stLabel($r), 'opts' => ['Pending', 'Accepted', 'Rejected']],
        ['k' => 'pid', 'label' => 'Material', 'get' => fn ($r) => $r['pid'], 'opts' => array_values(array_unique(array_column($D, 'pid')))],
    ];
    $actions = null;
    // x-icon tags inside plain strings are not compiled: render through Blade::render
    $ico = fn ($n) => \Illuminate\Support\Facades\Blade::render('<x-icon name="' . $n . '" :size="14" />');
    if ($canEdit) {
        $actions = fn ($r) =>
            ($r['status'] === 'pending' ? $post(route('rq.line.accept', $r['id']), 'Accept', 'btn sm pri') : $post(route('rq.line.revert', $r['id']), 'Revert'))
            . ' <button type="button" class="btn sm gh" title="Edit" data-open="rq-line" data-fill="' . e(json_encode(['id' => $r['id'], 'pid' => $r['pid'], 'buyer' => $r['buyer'], 'customer' => $r['customer'], 'qty' => $r['qty'], 'need' => $r['need'] ?? '', 'mkt' => $r['mkt'], 'remark' => $r['remark'] ?? ''])) . '">' . $ico('edit') . '</button>'
            . $post(route('rq.line.delete', $r['id']), $ico('trash'), 'btn sm gh', 'delete', 'Delete this requisition line?');
    }
    $cons = array_values(array_filter($mats, fn ($p) => collect($D)->contains('pid', $p['id'])));
@endphp

{!! Ui::flowBar($c, 'rq.board') !!}
@if ($frozen)
    {!! Ui::alert('info', 'Requisition frozen', "{$acc} line(s) accepted, {$rej} rejected. Accepted demand moved to the Import Booking Report.", '<a class="btn sm pri" href="' . e(route('rq.booking')) . '">Open booking</a>') !!}
@elseif ($pen)
    {!! Ui::alert('warn', "{$pen} line(s) still pending", 'Accept or reject every line before freezing. Marketing can see rejection reasons and the line can be reverted.') !!}
@else
    {!! Ui::alert('info', 'All lines decided', 'Validate and freeze to hand over to the Import Booking Report.') !!}
@endif
<div style="margin-top:12px"><div class="grid g5">
    {!! Ui::kpi('Lines', count($D)) !!}
    {!! Ui::kpi('Accepted', $acc, ['cls' => 'ok']) !!}
    {!! Ui::kpi('Rejected', $rej, ['cls' => $rej ? 'warn' : '']) !!}
    {!! Ui::kpi('Pending', $pen, ['cls' => $pen ? 'warn' : 'ok']) !!}
    {!! Ui::kpi('Accepted qty (all UOM)', Fmt::n0($st['acceptedQty'])) !!}
</div></div>

@if ($canEdit || $canFreeze)
    <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
        <form id="bulk-form" method="post" style="display:none">@csrf</form>
        @if ($canEdit)
            <button class="btn sm" type="submit" form="bulk-form" formaction="{{ route('rq.bulk.accept') }}">Accept selected</button>
            <button class="btn sm warnb" type="button" data-open="rq-reject">Reject selected…</button>
            <button class="btn sm pri" type="button" data-open="rq-line" data-fill='{"id":"","pid":"{{ array_key_first($mats) }}","buyer":"","customer":"","qty":"","need":"","mkt":"{{ $mkts[0] }}","remark":""}'><x-icon name="plus" :size="13" /> Add line</button>
        @endif
        @if ($canFreeze)
            <x-post :action="route('rq.freeze')" class="btn sm" confirm="Validate and freeze requisition? Frozen demand cannot be edited. Accepted quantities move to the Import Booking Report with Est. delivery of Marketing requirement = 100%.">Validate &amp; freeze</x-post>
        @endif
    </div>
@endif

<div class="grid g21" style="margin-top:12px"><div>
    <x-dtable id="rqb" :cols="$cols" :rows="$D" :filters="$filters" :actions="$canEdit ? $actions : null" empty="No requisition lines" empty-hint="Add a line, or wait for submitted PRs from the ERP." />
</div>
<div class="card"><h3>Consolidated demand</h3><div class="cp">
    @forelse ($cons as $p)
        @php($tot = $svc->demandQty($c, $p['id']))
        <div class="hrow" style="grid-template-columns:1fr 80px"><span title="{{ $p['name'] }}"><b>{{ $p['id'] }}</b> {{ $p['name'] }}</span><span class="r">{{ Fmt::n0($svc->demandQty($c, $p['id'], 'accepted')) }} / {{ Fmt::n0($tot) }} {{ $p['uom'] }}</span></div>
        <div class="bar"><i style="width:{{ $tot ? round($svc->demandQty($c, $p['id'], 'accepted') / $tot * 100) : 0 }}%"></i></div>
    @empty
        <div class="empty">No demand</div>
    @endforelse
    <p class="mut sm" style="margin-top:10px">Bar = accepted share of total demand.</p>
</div></div></div>

@if ($canEdit)
    <x-dialog id="rq-line" title="Requisition line" :action="route('rq.line.save')" ok="Save line" data-open-on-new>
        <input type="hidden" name="id" value="{{ old('id') }}">
        <div class="fg">
            <x-field name="pid" label="Material" type="select" :options="collect($mats)->mapWithKeys(fn ($p) => [$p['id'] => $p['id'] . ' · ' . $p['name']])->all()" value="{{ array_key_first($mats) }}" required full />
            <x-field name="buyer" label="Buyer" required list="dl-buyer" />
            <x-field name="customer" label="Customer (garment factory)" required list="dl-cust" />
            <x-field name="qty" label="Quantity" type="number" min="1" step="any" required />
            <x-field name="need" label="Required by" type="date" />
            <x-field name="mkt" label="Marketing person" type="select" :options="$mkts" />
            <x-field name="remark" label="Remark" full />
        </div>
        <datalist id="dl-buyer">@foreach ($buyers as $b)<option>{{ $b }}</option>@endforeach</datalist>
        <datalist id="dl-cust">@foreach ($custs as $b)<option>{{ $b }}</option>@endforeach</datalist>
    </x-dialog>

    <x-dialog id="rq-reject" title="Reject selected line(s)">
        <input type="hidden" name="_dialog" value="rq-reject" form="bulk-form">
        <div class="fg">
            <x-field name="reason" label="Reason of non-acceptance" type="select" :options="$reasons" required full form="bulk-form" />
            <x-field name="note" label="Note (optional)" full form="bulk-form" />
        </div>
        <p class="mut sm">Select the lines in the table first. Marketing can see the reason. The line can be reverted later.</p>
        <x-slot:foot>
            <button type="button" class="btn" data-close>Cancel</button>
            <button class="btn bad" type="submit" form="bulk-form" formaction="{{ route('rq.bulk.reject') }}">Reject lines</button>
        </x-slot:foot>
    </x-dialog>
@endif
@endif
@endsection
