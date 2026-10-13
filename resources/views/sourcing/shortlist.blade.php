@extends('layouts.app')
@section('title', 'Supplier Shortlist')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">Sourcing / RFQ</a> / Supplier Shortlist
@endsection
@section('subheading')Which supplier product can quote for which material. Only Active mappings are invited when an RFQ is created.
@endsection
@section('actions')
    @if (\App\Domain\Perm::can('sp.edit'))
        <button class="btn pri" type="button" data-open="sp-form" data-fill='{"id":"","pid":"P-1001","sid":"SUP-00001","name":"","active":"Active"}'><x-icon name="plus" :size="15" /> Add mapping</button>
    @endif
@endsection

@section('content')
@php
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Support\Ui;
    $can = Perm::can('sp.edit');
    $mats = Masters::materials();
    $sups = Masters::suppliers();
    $cols = [
        ['k' => 'pid', 't' => 'Material', 's' => fn ($r) => $r['pid'] . ' ' . ($mats[$r['pid']]['name'] ?? ''), 'r' => fn ($r) => '<b>' . e($r['pid']) . '</b> <span class="mut sm">' . e($mats[$r['pid']]['name'] ?? '') . '</span>'],
        ['k' => 'sup', 't' => 'Supplier', 's' => fn ($r) => $sups[$r['sid']]['name'] ?? $r['sid'], 'r' => fn ($r) => '<a href="' . e(route('sup.show', $r['sid'])) . '">' . e($sups[$r['sid']]['name'] ?? $r['sid']) . '</a>'],
        ['k' => 'name', 't' => 'Supplier product name'],
        ['k' => 'cal', 't' => 'Calendar', 's' => fn ($r) => $sups[$r['sid']]['cal'] ?? ''],
        ['k' => 'status', 't' => 'Status', 's' => fn ($r) => $r['active'] ? 'Active' : 'Inactive', 'r' => fn ($r) => Ui::pill($r['active'] ? 'Active' : 'Inactive')],
    ];
    $filters = [
        ['k' => 'pid', 'label' => 'Material', 'get' => fn ($r) => $r['pid'], 'opts' => array_keys($mats)],
        ['k' => 'sid', 'label' => 'Supplier', 'get' => fn ($r) => $sups[$r['sid']]['name'] ?? $r['sid'], 'opts' => array_column($sups, 'name')],
        ['k' => 'status', 'label' => 'Status', 'get' => fn ($r) => $r['active'] ? 'Active' : 'Inactive', 'opts' => ['Active', 'Inactive']],
    ];
    $post = fn ($url, $label, $cls, $confirm = null, $title = null) => '<form method="post" action="' . e($url) . '" style="display:inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<button type="submit" class="' . $cls . '"' . ($title ? ' title="' . e($title) . '"' : '') . '>' . $label . '</button></form>';
    $actions = $can ? function ($r) use ($post) {
        $fill = e(json_encode(['id' => $r['id'], 'pid' => $r['pid'], 'sid' => $r['sid'], 'name' => $r['name'], 'active' => $r['active'] ? 'Active' : 'Inactive']));
        return $post(route('sp.toggle', $r['id']), $r['active'] ? 'Deactivate' : 'Activate', 'btn sm')
            . ' <button type="button" class="btn sm gh" title="Edit" data-open="sp-form" data-fill="' . $fill . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4"/></svg></button>'
            . $post(route('sp.delete', $r['id']), \Illuminate\Support\Facades\Blade::render('<x-icon name="trash" :size="14" />'), 'btn sm gh', 'Remove this supplier-material mapping?', 'Remove');
    } : null;
@endphp

@if ($single)
    {!! Ui::alert('warn', 'Single-source materials in the working case', e(implode(' · ', array_map(fn ($p) => $p . ' ' . ($mats[$p]['name'] ?? ''), $single))), '<span class="mut sm">Single-source purchases need documented justification and approval.</span>') !!}
    <div style="margin-top:12px"></div>
@endif

<x-dtable id="sp" :cols="$cols" :rows="$rows" :filters="$filters" :actions="$actions" empty="No supplier-material mappings" />

@if ($can)
    <x-dialog id="sp-form" title="Supplier-material mapping" :action="route('sp.save')" ok="Save">
        <input type="hidden" name="id" value="{{ old('id') }}">
        <div class="fg">
            <x-field name="pid" label="Material" type="select" :options="array_map(fn ($m) => [$m['id'], $m['id'] . ' · ' . $m['name']], array_values($mats))" required full />
            <x-field name="sid" label="Supplier" type="select" :options="array_map(fn ($s) => [$s['id'], $s['name']], array_values($sups))" required full />
            <x-field name="name" label="Supplier product name" required />
            <x-field name="active" label="Status" type="select" :options="['Active', 'Inactive']" />
        </div>
    </x-dialog>
@endif
@endsection
