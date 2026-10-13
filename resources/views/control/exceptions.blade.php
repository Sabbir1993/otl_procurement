@extends('layouts.app')
@section('title', 'Exceptions')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('exc') }}">Control</a> / Exceptions
@endsection
@section('heading')Exceptions
@endsection
@section('subheading')Every exception is handled the same way every time, with a named owner.
@endsection
@section('actions')
@endsection
@section('content')
@php
    use App\Domain\Perm;
    use App\Models\ExceptionRecord;
    use App\Services\ControlService;
    use App\Support\ChartUi;
    use App\Support\Ui;
    $all = ExceptionRecord::orderByDesc('at_ms')->orderByDesc('id')->get();
    $open = $all->where('status', 'Open');
    $canRes = Perm::can('exc.resolve');
    $cols = [
        ['k' => 'id', 't' => 'ID', 'r' => fn ($r) => '<b>' . e($r->id) . '</b>'],
        ['k' => 'type', 't' => 'Type', 'r' => fn ($r) => '<b>' . e($r->type) . '</b>'],
        ['k' => 'ref', 't' => 'Reference'],
        ['k' => 'case_id', 't' => 'Case', 'r' => fn ($r) => $r->case_id ? Ui::link('case/' . $r->case_id, e($r->case_id)) : '—'],
        ['k' => 'detail', 't' => 'Detail', 'r' => fn ($r) => '<span class="sm">' . e($r->detail) . '</span>' . ($r->res ? '<div class="mut sm">Resolution: ' . e($r->res) . '</div>' : '')],
        ['k' => 'owner', 't' => 'Owner', 'r' => fn ($r) => '<span class="sm">' . e($r->owner) . '</span>'],
        ['k' => 'sev', 't' => 'Severity', 'r' => fn ($r) => Ui::pill($r->sev)],
        ['k' => 'status', 't' => 'Status', 'r' => fn ($r) => Ui::pill($r->status)],
    ];
    $filters = [
        ['k' => 'st', 'label' => 'Status', 'get' => fn ($r) => $r->status, 'opts' => ['Open', 'Resolved']],
        ['k' => 'sev', 'label' => 'Severity', 'get' => fn ($r) => $r->sev, 'opts' => ['High', 'Medium', 'Low']],
        ['k' => 'type', 'label' => 'Type', 'get' => fn ($r) => $r->type, 'opts' => $all->pluck('type')->unique()->sort()->values()->all()],
    ];
    $actions = fn ($r) => ($r->status === 'Open' && $canRes)
        ? '<button type="button" class="btn sm" data-open="exc-res" data-fill=\'' . e(json_encode(['id' => $r->id, 'owner' => $r->owner, 'status' => 'Resolved', 'res' => '', 'type' => $r->type, 'detail' => $r->detail])) . '\'>Resolve</button>'
        : '';
    $owners = ControlService::owners();
    foreach ($all->pluck('owner')->unique() as $o) { if ($o && !in_array($o, $owners, true)) { $owners[] = $o; } }
    sort($owners);
@endphp
{!! ChartUi::kpiRow([
    ['Open', $open->count(), ['cls' => $open->count() ? 'bad' : 'ok', 'go' => 'exc', 'tid' => 'exc', 'f' => ['st' => 'Open']]],
    ['High severity', $open->where('sev', 'High')->count(), ['cls' => 'warn', 'go' => 'exc', 'tid' => 'exc', 'f' => ['st' => 'Open', 'sev' => 'High']]],
    ['Resolved', $all->where('status', 'Resolved')->count(), ['cls' => 'ok', 'go' => 'exc', 'tid' => 'exc', 'f' => ['st' => 'Resolved']]],
], 3) !!}
<div style="margin-top:12px">
    <x-dtable id="exc" :cols="$cols" :rows="$all->all()" :filters="$filters" :actions="$actions" empty="No exceptions — good." />
</div>

@if ($canRes)
    <x-dialog id="exc-res" title="Resolve / update exception" :action="route('exc.save')" ok="Save">
        <input type="hidden" name="id" value="{{ old('id') }}">
        <div class="alert warn" style="margin-bottom:10px"><div class="t"><b data-fill-text="type"></b><span data-fill-text="detail"></span></div></div>
        <div class="fg">
            <x-field name="owner" label="Owner" type="select" :options="$owners" />
            <x-field name="status" label="Status" type="select" :options="['Open', 'Resolved']" value="Resolved" />
            <x-field name="res" label="Resolution / evidence" type="textarea" full hint="Required to mark the exception Resolved." />
        </div>
    </x-dialog>
@endif
@endsection
