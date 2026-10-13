@extends('layouts.app')
@section('title', 'Approval Queue')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('aw.neg') }}">Negotiation &amp; Awards</a> / Approval Queue
@endsection
@section('heading')Approval Queue
@endsection
@section('subheading')Marketing ACK → Finance APP → ED final APP. One named reviewer per step, no self-approval, no skipped step.
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Perm;
    use App\Support\Ui;

    $wc = \App\Support\WorkingCase::get();
    $label = ['mkt' => 'Marketing ACK', 'fin' => 'Finance APP', 'ed' => 'ED final APP', 'approved' => 'Approved', 'returned' => 'Returned', 'rejected' => 'Rejected', 'none' => '—'];
    $val = fn ($c) => array_sum(array_column($c->s['appr']['snap'] ?? [], 'amt'));
    $cols = [
        ['k' => 'id', 't' => 'Package', 's' => fn ($c) => $c->s['appr']['id'], 'r' => fn ($c) => '<b>' . e($c->s['appr']['id']) . '</b> <span class="mut sm">v' . e($c->s['appr']['version']) . '</span>'],
        ['k' => 'c', 't' => 'Case', 's' => fn ($c) => $c->id],
        ['k' => 'val', 't' => 'Material value', 'cls' => 'r', 's' => $val, 'r' => fn ($c) => Fmt::usd($val($c))],
        ['k' => 'ln', 't' => 'Lines', 'cls' => 'r', 's' => fn ($c) => count($c->s['appr']['snap'] ?? [])],
        ['k' => 'st', 't' => 'Stage', 's' => fn ($c) => $c->s['appr']['status'], 'r' => fn ($c) => Ui::pill($label[$c->s['appr']['status']], $c->s['appr']['status'] === 'approved' ? 'p-ok' : (in_array($c->s['appr']['status'], ['returned', 'rejected']) ? 'p-bad' : 'p-warn'))],
        ['k' => 'wait', 't' => 'Waiting for', 's' => fn ($c) => $c->s['appr']['status'], 'r' => fn ($c) => e(in_array($c->s['appr']['status'], ['mkt', 'fin', 'ed']) ? Perm::roleName($c->s['appr']['status']) : '—')],
        ['k' => 'by', 't' => 'Submitted', 's' => fn ($c) => $c->s['appr']['rec']['by'] ?? '', 'r' => fn ($c) => e($c->s['appr']['rec']['by'] ?? '') . '<div class="mut sm">' . Fmt::dS($c->s['appr']['rec']['at'] ?? null) . '</div>'],
    ];
    $filters = [['k' => 'st', 'label' => 'Stage', 'get' => fn ($c) => $c->s['appr']['status'], 'opts' => ['mkt', 'fin', 'ed', 'approved', 'returned', 'rejected']]];
@endphp
@if ($wc){!! Ui::flowBar($wc, 'aw.appr') !!}@endif
<div style="margin-top:12px"></div>
<x-dtable id="apq" :cols="$cols" :rows="$cases" :filters="$filters" :row-link="fn ($c) => route('award.show', $c->id)" empty="No award packages yet" empty-hint="Submit the final recommendation from a completed comparative statement." />
@endsection
