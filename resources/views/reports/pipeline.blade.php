@extends('layouts.app')
@section('title', 'Pipeline & Cycle Time')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('an.perf') }}">Reports</a> / Pipeline &amp; Cycle Time
@endsection
@section('heading')Pipeline &amp; Cycle Time
@endsection
@section('subheading')Measured elapsed time between stages for each case. Targets are not supplied — none are shown.
@endsection
@section('actions')
@endsection
@section('content')
@php
    use App\Support\ChartUi as C;
    use App\Support\Ui;
    $d = fn ($a, $b) => fn ($r) => C::days($r[$a], $r[$b]);
    $dv = fn ($a, $b) => fn ($r) => ($r[$a] && $r[$b]) ? ($r[$b] - $r[$a]) / 86400000 : -1;   // numeric sort key
    $cols = [
        ['k' => 'id', 't' => 'Case', 'r' => fn ($r) => '<b>' . e($r['id']) . '</b>'],
        ['k' => 'stage', 't' => 'Stage', 'r' => fn ($r) => Ui::pill($r['stage'], $r['stage'] === 'Completed' ? 'p-ok' : 'p-acc')],
        ['k' => 'a', 't' => 'Case → booking', 'cls' => 'r', 's' => $dv('created', 'book'), 'r' => $d('created', 'book')],
        ['k' => 'b', 't' => 'Booking → RFQ sent', 'cls' => 'r', 's' => $dv('book', 'sent'), 'r' => $d('book', 'sent')],
        ['k' => 'c', 't' => 'RFQ → last quote', 'cls' => 'r', 's' => $dv('sent', 'lastQ'), 'r' => $d('sent', 'lastQ')],
        ['k' => 'd', 't' => 'Quotes → ED approval', 'cls' => 'r', 's' => $dv('lastQ', 'ed'), 'r' => $d('lastQ', 'ed')],
        ['k' => 'e', 't' => 'Approval → PO sent', 'cls' => 'r', 's' => $dv('ed', 'fp'), 'r' => $d('ed', 'fp')],
        ['k' => 't', 't' => 'Case → PO sent', 'cls' => 'r', 's' => $dv('created', 'fp'), 'r' => fn ($r) => '<b>' . C::days($r['created'], $r['fp']) . '</b>'],
    ];
@endphp
<x-dtable id="pipe" :cols="$cols" :rows="$rows" :row-link="fn ($r) => route('case.show', $r['id'])" empty="No purchase cases yet" />
@endsection
