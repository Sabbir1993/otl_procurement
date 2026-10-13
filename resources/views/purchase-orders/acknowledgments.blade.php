@extends('layouts.app')
@section('title', 'Supplier Acknowledgments')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('po.reg') }}">Purchase Orders</a> / Acknowledgments
@endsection
@section('heading')Supplier Acknowledgments
@endsection
@section('subheading')The supplier can only Accept or Dispute — never modify the PO. A dispute stops PI progression until resolved and re-approved where material.
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
    $cols = [
        ['k' => 'no', 't' => 'PO', 's' => fn ($r) => $r['no'], 'r' => fn ($r) => '<b>' . e(PoService::full($r)) . '</b>'],
        ['k' => 'c', 't' => 'Case', 's' => fn ($r) => $r['_c']->id, 'r' => fn ($r) => e($r['_c']->id)],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => Masters::supplier($r['sid'])['name'], 'r' => fn ($r) => e(Masters::supplier($r['sid'])['name'])],
        ['k' => 'val', 't' => 'Value', 'cls' => 'r', 's' => fn ($r) => $r['total'], 'r' => fn ($r) => 'USD ' . Fmt::n0($r['total'])],
        ['k' => 'sent', 't' => 'Sent', 's' => fn ($r) => $r['sentAt'], 'r' => fn ($r) => e(Fmt::dS($r['sentAt']))],
        ['k' => 'ack', 't' => 'Acknowledgment', 's' => fn ($r) => $r['ack'], 'r' => fn ($r) => Ui::pill($r['ack']) . (!empty($r['dispute']) ? '<div class="mut sm">“' . e($r['dispute']) . '”</div>' : '')],
        ['k' => 'sla', 't' => 'SLA', 'nosort' => true, 's' => fn ($r) => '', 'r' => fn ($r) => '<span class="mut sm">[TBD — Policy item 8]</span>'],
    ];
    $filters = [['k' => 'ack', 'label' => 'Acknowledgment', 'get' => fn ($r) => $r['ack'], 'opts' => ['Awaiting', 'Accepted', 'Disputed']]];
    $actions = function ($r) {
        $h = '';
        if ($r['ack'] === 'Awaiting' && Perm::can('rfq.remind')) {
            $h .= '<form method="post" action="' . e(route('po.remind')) . '" style="display:inline">' . csrf_field() . '<input type="hidden" name="no" value="' . e($r['no']) . '"><button class="btn sm" type="submit">Remind</button></form> ';
        }
        if ($r['ack'] === 'Disputed' && Perm::can('exc.resolve')) {
            $h .= '<button class="btn sm warnb" type="button" data-open="po-res" data-fill=\'' . e(json_encode(['no' => $r['no'], 'dispute' => (string) ($r['dispute'] ?? '')]), ENT_QUOTES) . '\'>Resolve…</button> ';
        }
        return $h . '<a class="btn sm gh" href="' . e(route('portal', ['pCase' => $r['_c']->id, 'pSup' => $r['sid'], 'pMode' => 'po'])) . '">Portal</a>';
    };
@endphp
<x-dtable id="ack" :cols="$cols" :rows="$rows" :filters="$filters" :row-link="fn ($r) => route('po.show', $r['no'])" :actions="$actions" empty="No POs sent yet" empty-hint="Dispatch POs from the PO register." />

@include('purchase-orders._resolve')
@endsection
