@extends('layouts.app')
@section('title', 'RFQs')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">Sourcing / RFQ</a> / RFQs
@endsection
@section('subheading')One RFQ per case. Each invited supplier gets its own secure, expiring link; deadlines follow the supplier-country working-day calendar.
@endsection
@section('actions')
    @if ($c && \App\Domain\Perm::can('rfq.create'))
        @if ($c->s['booking']['confirmed'])
            <button class="btn pri" type="button" data-open="rfq-form"><x-icon name="plus" :size="15" /> New RFQ</button>
        @else
            <button class="btn pri" type="button" disabled title="Confirm the booking first — the RFQ is built from the confirmed book qty."><x-icon name="plus" :size="15" /> New RFQ</button>
        @endif
    @endif
@endsection

@section('content')
@php
    use App\Domain\Perm;
    use App\Services\RfqService;
    use App\Support\Ui;
    $cols = [
        ['k' => 'no', 't' => 'RFQ', 's' => fn ($r) => $r->s['rfq']['no'], 'r' => fn ($r) => '<b>' . e($r->s['rfq']['no']) . '</b> <span class="mut sm">V' . e($r->s['rfq']['ver']) . '</span>'],
        ['k' => 'id', 't' => 'Case', 's' => fn ($r) => $r->id, 'r' => fn ($r) => '<a href="' . e(route('case.show', $r->id)) . '">' . e($r->id) . '</a>'],
        ['k' => 'serial', 't' => 'Booking', 's' => fn ($r) => $r->s['booking']['serial'] ?? ''],
        ['k' => 'w', 't' => 'Window', 's' => fn ($r) => !empty($r->s['rfq']['urgent']) ? 'Urgent' : 'Standard', 'r' => fn ($r) => !empty($r->s['rfq']['urgent']) ? Ui::pill('Urgent 2d', 'p-warn') : 'Standard 3d'],
        ['k' => 'sup', 't' => 'Suppliers', 'cls' => 'r', 's' => fn ($r) => count($r->s['drafts'])],
        ['k' => 'sent', 't' => 'Sent', 'cls' => 'r', 's' => fn ($r) => count($r->s['inv'])],
        ['k' => 'sub', 't' => 'Submitted', 'cls' => 'r', 's' => fn ($r) => count(array_filter($r->s['inv'], fn ($i) => !empty($i['submitted'])))],
        ['k' => 'st', 't' => 'Status', 's' => fn ($r) => RfqService::status($r), 'r' => function ($r) {
            $s = RfqService::status($r);
            return Ui::pill($s, $s === 'Closed' ? 'p-ok' : ($s === 'Open' ? 'p-info' : 'p-warn'));
        }],
    ];
@endphp
@if ($c)
    {!! Ui::flowBar($c, 'src.rfq') !!}
    <div style="margin-top:12px"></div>
    @if ($c->s['booking']['confirmed'] && empty($c->s['rfq']['no']))
        {!! Ui::alert('info', 'No RFQ created yet for ' . e($c->id), 'The booking is confirmed. Click “New RFQ” to choose suppliers and create the drafts — the RFQ appears in the list below once created.', \App\Domain\Perm::can('rfq.create') ? '<a class="btn sm pri" href="' . e(route('src.rfq', ['new' => 1])) . '">New RFQ</a>' : '') !!}
        <div style="margin-top:12px"></div>
    @endif
    @if (!$c->s['booking']['confirmed'])
        {!! Ui::alert('warn', 'Booking not confirmed for ' . e($c->id), 'The RFQ is built from the confirmed book quantity.', '<a class="btn sm" href="' . e(route('rq.booking')) . '">Go to booking</a>') !!}
        <div style="margin-top:12px"></div>
    @endif
@endif
<x-dtable id="rfqs" :cols="$cols" :rows="$cases" :row-link="fn ($r) => route('rfq.show', $r->id)" empty="No RFQs yet" empty-hint="Confirm a booking, then create the RFQ." />

@if ($c && \App\Domain\Perm::can('rfq.create') && $c->s['booking']['confirmed'])
    @include('sourcing._rfq-dialog', ['c' => $c])
@endif
@endsection
