@extends('layouts.app')
@section('title', 'Acceptance Tests')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Acceptance Tests
@endsection
@section('heading', 'Acceptance Tests')
@section('subheading', 'Walk each test, then record the result. These come from the BRD and double as the UAT checklist for development.')
@section('actions')<span class="pill p-ok">{{ count(array_filter($res, fn ($r) => $r === 'Pass')) }}/{{ count($acs) }} passed</span>
@endsection

@section('content')
@php
    $can = \App\Domain\Perm::can('admin.cfg');
    $rows = array_map(fn ($a) => ['id' => $a[0], 'g' => $a[1], 'e' => $a[2], 's' => $a[3], 'go' => $a[4]], $acs);
    $cols = [
        ['k' => 'id', 't' => 'ID', 'r' => fn ($r) => '<b>' . e($r['id']) . '</b>'],
        ['k' => 'g', 't' => 'Given / when'],
        ['k' => 'e', 't' => 'Expected outcome', 'r' => fn ($r) => '<span class="sm">' . e($r['e']) . '</span>'],
        ['k' => 's', 't' => 'Stage'],
        ['k' => 'res', 't' => 'Result', 's' => fn ($r) => $res[$r['id']] ?? '', 'r' => function ($r) use ($res, $can) {
            $v = $res[$r['id']] ?? '';
            if (!$can) { return $v ? \App\Support\Ui::pill($v) : '—'; }
            return '<form method="post" action="' . e(route('ad.test.set', $r['id'])) . '" data-autosubmit style="margin:0">' . csrf_field()
                . '<select name="res" style="min-width:90px"><option value="">—</option><option' . ($v === 'Pass' ? ' selected' : '') . '>Pass</option><option' . ($v === 'Fail' ? ' selected' : '') . '>Fail</option></select></form>';
        }],
    ];
    $actions = fn ($r) => '<a class="btn sm" href="' . e(\App\Support\Ui::url($r['go'])) . '">Try it</a>';
@endphp
<x-dtable id="acs" :cols="$cols" :rows="$rows" :actions="$actions" :per="25" />
@endsection
