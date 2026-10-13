@extends('layouts.app')
@section('title', 'Amendments & Disputes')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('po.reg') }}">Purchase Orders</a> / Amendments &amp; Disputes
@endsection
@section('heading')Amendments &amp; Disputes
@endsection
@section('subheading')Any material change after approval is versioned, frozen and re-approved — never overwritten.
@endsection
@section('actions')
    @if (\App\Domain\Perm::can('appr.change') || \App\Domain\Perm::can('cs.negotiate'))
        @if ($c)
            <button class="btn warnb" type="button" data-open="appr-chg">Raise change request</button>
        @else
            <a class="btn warnb" href="{{ route('aw.appr') }}">Raise change request</a>
        @endif
    @endif
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\PoService;
    use App\Support\Ui;
    $open = $exc->where('status', 'Open')->count();
    $dcols = [
        ['k' => 'no', 't' => 'PO', 's' => fn ($r) => $r['no'], 'r' => fn ($r) => '<b>' . e(PoService::full($r)) . '</b>'],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => Masters::supplier($r['sid'])['name'], 'r' => fn ($r) => e(Masters::supplier($r['sid'])['name'])],
        ['k' => 'd', 't' => 'Dispute', 's' => fn ($r) => $r['dispute'] ?? '', 'r' => fn ($r) => '<span class="sm">' . e($r['dispute'] ?? '') . '</span>'],
    ];
    $dact = fn ($r) => Perm::can('exc.resolve') ? '<button class="btn sm warnb" type="button" data-open="po-res" data-fill=\'' . e(json_encode(['no' => $r['no'], 'dispute' => (string) ($r['dispute'] ?? '')]), ENT_QUOTES) . '\'>Resolve…</button>' : '';
    $xcols = [
        ['k' => 'id', 't' => 'ID', 's' => fn ($r) => $r->id, 'r' => fn ($r) => '<b>' . e($r->id) . '</b>'],
        ['k' => 'type', 't' => 'Type', 's' => fn ($r) => $r->type],
        ['k' => 'ref', 't' => 'Reference', 's' => fn ($r) => $r->ref],
        ['k' => 'detail', 't' => 'Detail', 's' => fn ($r) => $r->detail, 'r' => fn ($r) => '<span class="sm">' . e($r->detail) . '</span>'],
        ['k' => 'st', 't' => 'Status', 's' => fn ($r) => $r->status, 'r' => fn ($r) => Ui::pill($r->status)],
    ];
    $xact = fn ($r) => $r->status === 'Open' ? '<a class="btn sm" href="' . e(route('exc')) . '">Resolve</a>' : '';
@endphp
{!! Ui::kpiRow([
    ['Open disputes', count($dis), ['cls' => count($dis) ? 'bad' : 'ok']],
    ['Frozen POs', count($frozen), ['cls' => count($frozen) ? 'warn' : '']],
    ['Related exceptions', $open],
]) !!}
<div style="margin-top:12px">
    <x-dtable id="dsp" :cols="$dcols" :rows="$dis" :actions="$dact" :row-link="fn ($r) => route('po.show', $r['no'])" empty="No disputed POs" :export="false" />
</div>
<div style="margin-top:12px">
    <x-dtable id="dsx" :cols="$xcols" :rows="$exc" :actions="$xact" empty="No related exceptions" />
</div>

@include('purchase-orders._resolve')

@if ($c && (Perm::can('appr.change') || Perm::can('cs.negotiate')))
    <x-dialog id="appr-chg" title="Raise change request" :action="route('appr.change', $c->id)" ok="Freeze & route for re-approval" danger>
        <div class="alert warn" style="margin-bottom:10px"><div class="t">A change to these fields after approval freezes unsent POs and routes the case for re-approval (Marketing → Finance → ED). Nothing is overwritten silently.</div></div>
        <div class="fg">
            <x-field name="f" label="Field to change" type="select" full :options="\App\Services\ApprovalService::CHANGE_FIELDS" />
            <x-field name="r" label="Reason" type="textarea" rows="2" full required />
        </div>
    </x-dialog>
@endif
@endsection
