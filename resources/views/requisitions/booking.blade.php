@extends('layouts.app')
@section('title', 'Confirmed Book Qty')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('rq.board') }}">Requisitions</a> / Confirmed Book Qty
@endsection
@section('heading')Confirmed Book Qty
@endsection
@section('subheading')Import Booking Report → verify and edit quantities → attach the signed document (mandatory) → confirm. The serial is assigned in ascending order.
@endsection
@section('actions')
@if ($c)
    <span class="pill p-acc">{{ $c->id }}</span>
    @if ($c->s['frozen'])
        <a class="btn" href="{{ route('rq.booking.report') }}"><x-icon name="dl" :size="14" /> Download report</a>
        @if (!$c->s['booking']['confirmed'] && \App\Domain\Perm::can('booking.edit'))
            <form method="post" action="{{ route('rq.bk.csv') }}" enctype="multipart/form-data" style="display:inline">@csrf
                <label class="btn"><x-icon name="up" :size="14" /> Upload CSV<input type="file" name="csv" accept=".csv" style="display:none" onchange="this.form.submit()"></label>
            </form>
        @endif
    @endif
@endif
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Perm;
    use App\Support\Ui;
@endphp
@if (!$c)
    {!! Ui::empty('No purchase case yet', 'Create a purchase case first.') !!}
@elseif (!$c->s['frozen'])
    {!! Ui::flowBar($c, 'rq.booking') !!}
    {!! Ui::empty('Requisition not frozen yet', 'Validate and freeze the requisition board first.<div style="margin-top:10px"><a class="btn pri" href="' . e(route('rq.board')) . '">Open requisition board</a></div>') !!}
@else
@php
    $b = $c->s['booking'];
    $done = (bool) $b['confirmed'];
    $canEdit = !$done && Perm::can('booking.edit');
    $canConfirm = !$done && Perm::can('booking.confirm');
    $miss = array_filter($mats, fn ($p) => !collect($b['lines'])->contains('pid', $p['id']));
    $cols = [
        ['k' => 'pid', 't' => 'Material', 's' => fn ($r) => $r['pid'] . ($mats[$r['pid']]['name'] ?? ''), 'r' => fn ($r) => '<b>' . e($r['pid']) . '</b> <span class="mut sm">' . e($mats[$r['pid']]['name'] ?? '') . '</span>'],
        ['k' => 'demand', 't' => 'Accepted demand', 'cls' => 'r', 's' => fn ($r) => $r['demand'], 'r' => fn ($r) => Fmt::n0($r['demand']) . ' ' . e($mats[$r['pid']]['uom'] ?? '')],
        ['k' => 'pct', 't' => 'Est. delivery of MKT req.', 'cls' => 'r', 's' => fn ($r) => $r['pct'], 'r' => fn ($r) => $r['pct'] . '%'],
        ['k' => 'qty', 't' => 'Book qty', 'cls' => 'r', 's' => fn ($r) => $r['qty'], 'r' => fn ($r) => '<b>' . Fmt::n0($r['qty']) . '</b> ' . e($mats[$r['pid']]['uom'] ?? '')],
    ];
    $ico = fn ($n) => \Illuminate\Support\Facades\Blade::render('<x-icon name="' . $n . '" :size="14" />');
    $actions = $canEdit ? fn ($r) =>
        '<button type="button" class="btn sm" data-open="bk-edit" data-fill="' . e(json_encode(['pid' => $r['pid'], 'pct' => $r['pct'], 'qty' => $r['qty'], 'demand' => Fmt::n0($r['demand']) . ' ' . ($mats[$r['pid']]['uom'] ?? '')])) . '">' . $ico('edit') . ' Edit</button> '
        . '<form method="post" action="' . e(route('rq.bk.delete', $r['pid'])) . '" style="display:inline">' . csrf_field() . method_field('delete') . '<button type="submit" class="btn sm gh" title="Remove">' . $ico('trash') . '</button></form>'
        : null;
@endphp
{!! Ui::flowBar($c, 'rq.booking') !!}
@if ($done)
    {!! Ui::alert('info', e($b['serial']) . ' confirmed', 'Signed document <b>' . e($b['doc']) . '</b> (' . e($b['ref'] ?? '') . ') attached · confirmed ' . Fmt::dS($b['at'] ?? null) . ' by ' . e($b['by'] ?? '') . '.', '<a class="btn sm pri" href="' . e(route('src.rfq', ['new' => 1])) . '">Create RFQ</a>') !!}
@else
    {!! Ui::alert('warn', 'Not confirmed yet', 'Edit book quantities if needed, then confirm with the signed document. Upload a CSV (<code>product_id,book_qty</code>) to override in bulk.') !!}
@endif
<div class="grid g21" style="margin-top:12px"><div>
    @if ($canEdit && $miss)
        <div style="margin-bottom:8px"><button type="button" class="btn sm" data-open="bk-add"><x-icon name="plus" :size="13" /> Add material</button></div>
    @endif
    <x-dtable id="bkl" :cols="$cols" :rows="$b['lines']" :actions="$actions" empty="No booking lines" />
</div>
<div class="card"><h3>Confirmation</h3><div class="cp">
    {!! Ui::dl([
        ['Serial', e($b['serial'] ?? $next) . ($b['serial'] ? '' : ' <span class="mut sm">(on confirm)</span>')],
        ['Lines', count($b['lines'])],
        ['Signed document', !empty($b['doc']) ? e($b['doc']) : '<span class="pill p-warn">Required</span>'],
        ['Status', $done ? Ui::pill('Confirmed', 'p-ok') : Ui::pill('Draft', 'p-warn')],
    ]) !!}
    @if ($canConfirm)
        <div style="margin-top:12px"><button type="button" class="btn pri" style="width:100%;justify-content:center" data-open="bk-confirm">Confirm booking…</button></div>
    @endif
</div></div></div>

@if ($canEdit)
    <x-dialog id="bk-edit" title="Edit book qty" :action="route('rq.bk.save')" ok="Save">
        <input type="hidden" name="pid" value="{{ old('pid') }}">
        <div class="fg">
            <x-field name="pct" label="Est. delivery of Marketing requirement (%)" type="number" min="0" step="any" />
            <x-field name="qty" label="Book qty" type="number" min="1" step="any" required />
        </div>
        <p class="mut sm">Accepted demand: <span data-fill-text="demand">—</span>. Changing % recalculates the qty; you can then override it.</p>
    </x-dialog>
    @if ($miss)
        <x-dialog id="bk-add" title="Add material to booking" :action="route('rq.bk.add')" ok="Add">
            <div class="fg">
                <x-field name="pid" label="Material" type="select" :options="collect($miss)->mapWithKeys(fn ($p) => [$p['id'] => $p['id'] . ' · ' . $p['name']])->all()" required full />
                <x-field name="qty" label="Book qty" type="number" min="1" step="any" required />
            </div>
        </x-dialog>
    @endif
@endif
@if ($canConfirm)
    <x-dialog id="bk-confirm" title="Confirm import booking">
        <form method="post" action="{{ route('rq.bk.confirm') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_dialog" value="bk-confirm">
            <div class="fg">
                <label class="f full"><span>Signed document (PDF / JPG / PNG) *</span><input type="file" name="doc" accept=".pdf,.jpg,.jpeg,.png" required></label>
                <x-field name="ref" label="Document reference" required />
                <x-field name="by" label="Signed by" :value="Perm::user()" required />
                <x-field name="date" label="Signed on" type="date" :value="now()->format('Y-m-d')" required />
            </div>
            @if ($errors->any())<div class="ferr" style="color:var(--bad);margin-top:8px">{{ $errors->first() }}</div>@endif
            <p class="mut sm">The signed document is mandatory and is stored privately (never public). Production must virus-scan uploads. Confirming locks the booking and assigns the next serial.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button type="button" class="btn" data-close>Cancel</button><button class="btn pri" type="submit">Confirm booking</button></div>
        </form>
    </x-dialog>
@endif
@endif
@endsection
