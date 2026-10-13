@extends('layouts.app')
@section('title', 'Purchase Cases')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / Purchase Cases
@endsection
@section('subheading')One case = one monthly import booking, tracked end to end. Open a case to see its full flow.
@endsection
@section('actions')
    @if (\App\Domain\Perm::can('case.new'))
        <button class="btn pri" type="button" data-open="case-new"><x-icon name="plus" :size="15" /> New purchase case</button>
    @endif
@endsection

@section('content')
@php
    use App\Domain\CaseBook;
    use App\Domain\Fmt;
    use App\Support\Ui;
    $cols = [
        ['k' => 'id', 't' => 'Case', 's' => fn ($c) => $c->id, 'r' => fn ($c) => '<b>' . e($c->id) . '</b>'],
        ['k' => 'name', 't' => 'Name', 's' => fn ($c) => $c->name],
        ['k' => 'month', 't' => 'Month', 's' => fn ($c) => $c->month],
        ['k' => 'status', 't' => 'Stage', 's' => fn ($c) => $c->caseStatus(), 'r' => fn ($c) => Ui::pill($c->caseStatus(), $c->caseStatus() === 'Completed' ? 'p-ok' : 'p-acc')],
        ['k' => 'serial', 't' => 'Booking', 's' => fn ($c) => $c->s['booking']['serial'] ?? '', 'r' => fn ($c) => e($c->s['booking']['serial'] ?? '—')],
        ['k' => 'mats', 't' => 'Materials', 'cls' => 'r', 's' => fn ($c) => count($c->s['booking']['lines']), 'r' => fn ($c) => count($c->s['booking']['lines']) ?: '—'],
        ['k' => 'inv', 't' => 'Suppliers invited', 'cls' => 'r', 's' => fn ($c) => count($c->s['inv']), 'r' => fn ($c) => count($c->s['inv']) ?: '—'],
        ['k' => 'val', 't' => 'Awarded (USD)', 'cls' => 'r', 's' => fn ($c) => array_sum(array_column($c->lineAwards(), 'amt')), 'r' => fn ($c) => ($v = array_sum(array_column($c->lineAwards(), 'amt'))) ? Fmt::usd($v) : '—'],
        ['k' => 'next', 't' => 'Next step', 's' => fn ($c) => $c->nextAction()['t'], 'r' => fn ($c) => '<span class="sm">' . e($c->nextAction()['t']) . '</span>'],
    ];
    $filters = [['k' => 'status', 'label' => 'Stage', 'get' => fn ($c) => $c->caseStatus(), 'opts' => array_merge(array_column(CaseBook::FLOW, 0), ['Completed'])]];
@endphp

<x-dtable id="cases" :cols="$cols" :rows="$cases" :filters="$filters" :row-link="fn ($c) => route('case.show', $c->id)" empty="No purchase cases yet" />

@if (\App\Domain\Perm::can('case.new'))
    <x-dialog id="case-new" title="New purchase case" :action="route('cases.store')" ok="Create case" data-open-on-new>
        <div class="fg">
            <x-field name="name" label="Case name" required full />
            <x-field name="month" label="Booking month" type="select" value="Dec 2026" :options="['Oct 2026', 'Nov 2026', 'Dec 2026', 'Jan 2027', 'Feb 2027']" />
            <x-field name="notes" label="Notes" />
        </div>
        <p class="mut sm">A case holds the requisition, RFQ, quotations, comparison, approval, POs, PI and LC for one monthly booking.</p>
    </x-dialog>
@endif
@endsection
