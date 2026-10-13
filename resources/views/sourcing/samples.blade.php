@extends('layouts.app')
@section('title', 'Samples & QA Reports')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">Sourcing / RFQ</a> / Samples &amp; QA
@endsection
@section('heading')Samples &amp; QA Reports
@endsection
@section('subheading')Technical acceptance is recorded independently of price. A failed or pending sample blocks the offer; a conditional pass needs an approved deviation.
@endsection

@section('content')
@php
    use App\Domain\CaseBook;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Support\Ui;

    $wc = \App\Support\WorkingCase::get();
    $canEdit = Perm::can('sample.edit');
    $effect = ['Fail' => 'Blocked from award', 'Conditional' => 'Needs approved deviation', 'Pending' => 'Blocks award until tested', 'Waived' => 'Needs its own authorisation', 'Pass' => 'Cleared'];
    $sp = fn ($r) => $r['q']['spec'] ?? [];
    $dd = fn ($v) => $v ? Fmt::dD(strtotime($v . ' UTC') * 1000) : '';
    $cols = [
        ['k' => 'cid', 't' => 'Case', 's' => fn ($r) => $r['cid'], 'r' => fn ($r) => e($r['cid'])],
        ['k' => 'pid', 't' => 'Material', 's' => fn ($r) => $r['pid'], 'r' => fn ($r) => '<b>' . e($r['pid']) . '</b> <span class="mut sm">' . e(Masters::material($r['pid'])['name']) . '</span>'],
        ['k' => 'sp', 't' => 'Supplier', 's' => fn ($r) => $r['sp']['name'], 'r' => fn ($r) => e($r['sp']['name'])],
        ['k' => 'prod', 't' => 'Supplier product', 's' => fn ($r) => Masters::spName($r['sid'], $r['pid'])],
        ['k' => 'res', 't' => 'Result', 's' => fn ($r) => $r['q']['sample'], 'r' => fn ($r) => Ui::pill($r['q']['sample'])],
        ['k' => 'eff', 't' => 'Effect', 's' => fn ($r) => $r['q']['sample'], 'r' => fn ($r) => '<span class="sm">' . e($effect[$r['q']['sample']] ?? '') . '</span>'],
        ['k' => 'ship', 't' => 'Sample shipment', 's' => fn ($r) => $sp($r)['sampleDate'] ?? '', 'r' => function ($r) use ($sp, $dd) {
            $s = $sp($r);
            if (($s['sampleAvail'] ?? '') === 'No') {
                return '<span class="pill p-warn">Not available</span>';
            }
            return '<span class="sm">' . e($s['sampleQty'] ?? '—') . ' · ' . (!empty($s['sampleDate']) ? $dd($s['sampleDate']) : 'not dispatched') . '<br>' . e($s['courier'] ?? '') . ' ' . e($s['awb'] ?? '') . (!empty($s['expRecv']) ? ' · ETA ' . $dd($s['expRecv']) : '') . '</span>';
        }],
        ['k' => 'cost', 't' => 'Cost / freight', 'nosort' => true, 's' => fn ($r) => ($sp($r)['sampleCost'] ?? '') . ' ' . ($sp($r)['sampleFreight'] ?? ''), 'r' => fn ($r) => '<span class="sm">' . e($sp($r)['sampleCost'] ?? '—') . '<br>freight: ' . e($sp($r)['sampleFreight'] ?? '—') . '</span>'],
        ['k' => 'docs', 't' => 'QA documents', 'nosort' => true, 's' => fn ($r) => '', 'r' => function ($r) use ($sp) {
            $s = $sp($r);
            return implode(' ', array_map(fn ($d) => $d[1] ? '<span class="pill p-ok">' . $d[0] . '</span>' : '<span class="pill">' . $d[0] . ' —</span>', [['TDS', $s['tds'] ?? ''], ['COA', $s['coa'] ?? ''], ['Report', $s['report'] ?? ''], ['SDS', $s['sds'] ?? '']]));
        }],
        ['k' => 'rep', 't' => 'Test report ref', 's' => fn ($r) => $r['q']['report'] ?? '', 'r' => fn ($r) => e($r['q']['report'] ?? '—') ?: '—'],
    ];
    $filters = [
        ['k' => 'res', 'label' => 'Result', 'get' => fn ($r) => $r['q']['sample'], 'opts' => \App\Services\CsService::SAMPLE_RESULTS],
        ['k' => 'cid', 'label' => 'Case', 'get' => fn ($r) => $r['cid'], 'opts' => array_map(fn ($x) => $x->id, CaseBook::all())],
    ];
    $actions = $canEdit ? (fn ($r) => $r['locked']
        ? '<span class="mut sm" title="Comparison is locked while the award package is in approval.">Locked</span>'
        : '<button class="btn sm ' . (Perm::role() === 'qa' ? 'pri' : '') . '" type="button" data-open="smp" data-fill="' . e(json_encode(['cid' => $r['cid'], 'sid' => $r['sid'], 'pid' => $r['pid'], 'res' => $r['q']['sample'], 'rep' => $r['q']['report'] ?? '', 'lbl' => $r['pid'] . ' · ' . $r['sp']['name']])) . '">Record result</button>')
        : null;
@endphp
@if ($wc){!! Ui::flowBar($wc, 'src.samples') !!}@endif
<div style="margin-top:12px"></div>
<x-dtable id="smp" :cols="$cols" :rows="$rows" :filters="$filters" :actions="$actions" empty="No quotations with samples yet" />

@if ($canEdit)
    <x-dialog id="smp" title="Sample result" :action="route('smp.save')" ok="Save result">
        <input type="hidden" name="cid" value="{{ old('cid') }}"><input type="hidden" name="sid" value="{{ old('sid') }}"><input type="hidden" name="pid" value="{{ old('pid') }}">
        <div class="ins"><b data-fill-text="lbl">{{ old('lbl') }}</b></div><input type="hidden" name="lbl" value="{{ old('lbl') }}">
        <div class="fg">
            <x-field name="res" label="Result" type="select" required :options="\App\Services\CsService::SAMPLE_RESULTS" />
            <x-field name="rep" label="Test report reference" />
            <x-field name="note" label="Remarks / deviation" type="textarea" rows="2" full />
        </div>
        <p class="mut sm">Fail blocks the offer from award (the allocation is removed and an exception is raised). Conditional needs an approved deviation. Waived needs its own authorisation.</p>
    </x-dialog>
@endif
@endsection
