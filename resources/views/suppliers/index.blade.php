@extends('layouts.app')
@section('title', 'Supplier Directory')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('sup.dir') }}">Suppliers</a> / Directory
@endsection
@section('heading', 'Supplier Directory')
@section('subheading', 'Controlled supplier master. A contact change flagged in the portal stays “Unverified” until Finance / Procurement confirms it by an independent callback.')
@section('actions')
    @if (\App\Domain\Perm::can('sup.edit'))
        <button class="btn pri" type="button" data-open="sup-form" data-fill='{"id":"","name":"","cc":"CN","cal":"CN","contact":"","desig":"","address":"","email":"","phone":"[SUPPLIER-PHONE]","status":"Active"}'><x-icon name="plus" :size="15" /> New supplier</button>
    @endif
@endsection

@section('content')
@php
    use App\Domain\Masters;
    use App\Support\Ui;
    $cals = Masters::calendars();
    $active = fn ($s) => count(array_filter($products, fn ($p) => $p['sid'] === $s['id'] && $p['active']));
    $cols = [
        ['k' => 'id', 't' => 'ID', 'r' => fn ($r) => '<b>' . e($r['id']) . '</b>'],
        ['k' => 'name', 't' => 'Supplier', 'r' => fn ($r) => '<b>' . e($r['name']) . '</b>'],
        ['k' => 'cc', 't' => 'Country'],
        ['k' => 'cal', 't' => 'Calendar', 'r' => fn ($r) => e($r['cal']) . (!empty($cals[$r['cal']]['approved']) ? '' : ' <span class="pill p-bad">unapproved</span>')],
        ['k' => 'contact', 't' => 'Contact', 'r' => fn ($r) => e($r['contact']) . '<div class="mut sm">' . e(\App\Services\SupplierService::maskEmail($r['email'])) . '</div>'],
        ['k' => 'ver', 't' => 'Contact verified', 's' => fn ($r) => $r['emailVerified'] ? 'Verified' : 'Unverified', 'r' => fn ($r) => Ui::pill($r['emailVerified'] ? 'Verified' : 'Unverified')],
        ['k' => 'mats', 't' => 'Materials', 'cls' => 'r', 's' => fn ($r) => $active($r)],
        ['k' => 'status', 't' => 'Status', 'r' => fn ($r) => Ui::pill($r['status'])],
    ];
    $filters = [
        ['k' => 'cc', 'label' => 'Country', 'get' => fn ($r) => $r['cc'], 'opts' => fn () => array_values(array_unique(array_column($sups, 'cc')))],
        ['k' => 'ver', 'label' => 'Verification', 'get' => fn ($r) => $r['emailVerified'] ? 'Verified' : 'Unverified', 'opts' => ['Verified', 'Unverified']],
    ];
@endphp
<x-dtable id="sups" :cols="$cols" :rows="$sups" :filters="$filters" :row-link="fn ($r) => route('sup.show', $r['id'])" empty="No suppliers yet" />

@if (\App\Domain\Perm::can('sup.edit'))
    @include('suppliers._form')
@endif
@endsection
