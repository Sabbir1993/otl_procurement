@extends('layouts.app')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\PoService;
    use App\Support\Ui;
    $sp = Masters::supplier($po['sid']);
    $A = $c->s['appr'];
    $chk = $svc->validate($c, $po);
    $st = $svc->status($c, $po);
    $full = PoService::full($po);
    $ok = count(array_filter($chk, fn ($x) => $x['ok']));
    $tabs = ['doc' => 'PO document', 'chk' => 'Dispatch checks', 'mail' => 'Email', 'tl' => 'Timeline'];
    $iv = $c->invOf($po['sid']);
    $m = fn ($v) => e($v);
    $miss = fn ($v) => $v !== '' && $v !== null ? e($v) : '<span style="color:#e11d48">⚠ missing</span>';
    $editable = empty($po['sent']) && empty($po['frozen']);
@endphp
@section('title', $full . ' · ' . $sp['name'])
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('po.reg') }}">PO Register</a> / {{ $po['no'] }}
@endsection
@section('heading'){{ $full }} · {{ $sp['name'] }}
@endsection
@section('subheading'){{ $c->id }} · USD {{ Fmt::n0($po['total']) }} · {{ count($po['lines']) }} line(s)
@endsection
@section('actions')
    @if ($editable && Perm::can('po.edit'))
        <button class="btn" type="button" data-open="po-edit"><x-icon name="edit" :size="14" /> Edit details</button>
    @endif
    @if (empty($po['sent']) && $st === 'Ready to Dispatch' && Perm::can('po.dispatch'))
        <x-post :action="route('po.dispatch1')" :params="['no' => $po['no']]" class="btn pri" confirm="Dispatch {{ $full }} to {{ $sp['name'] }} only?"><x-icon name="send" :size="14" /> Dispatch to supplier</x-post>
    @endif
    @if (($po['status'] ?? '') === 'Delivery Failed' && Perm::can('po.dispatch'))
        <x-post :action="route('po.retry')" :params="['no' => $po['no']]" class="btn pri">Retry send</x-post>
    @endif
    @if (!empty($po['sent']) && $po['ack'] === 'Awaiting' && Perm::can('rfq.remind'))
        <x-post :action="route('po.remind')" :params="['no' => $po['no']]" class="btn">Send reminder</x-post>
    @endif
    <a class="btn" href="{{ route('portal', ['pCase' => $c->id, 'pSup' => $po['sid'], 'pMode' => 'po']) }}">View as supplier</a>
    <button class="btn" type="button" onclick="window.print()">Print</button>
@endsection

@section('content')
{!! Ui::flowBar($c, 'po.reg') !!}
{!! Ui::kpiRow([
    ['Dispatch', e($st), ['cls' => $st === 'Sent' ? 'ok' : ($st === 'Correction queue' ? 'bad' : 'warn')]],
    ['Acknowledgment', e($po['ack'])],
    ['Checks passed', "{$ok}/5", ['cls' => $ok === 5 ? 'ok' : 'bad']],
    ['LC / PI', e($c->lcState($po))],
]) !!}
@if ($st === 'Correction queue')
    {!! Ui::alert('bad', 'In the correction queue', 'Open “Dispatch checks” to fix what failed.') !!}
@endif
@if (!empty($po['frozen']))
    {!! Ui::alert('warn', 'PO frozen', 'A change accepted after a supplier dispute needs an approved revision. After re-approval this PO is re-issued as ' . e($po['no']) . '-R' . (($po['rev'] ?? 1) + 1) . '.') !!}
@endif
@if (($po['rev'] ?? 1) > 1)
    {!! Ui::alert('info', 'Revision R' . $po['rev'], 'Supersedes ' . implode(', ', array_map(fn ($h) => 'R' . $h['rev'] . ' (USD ' . Fmt::n0($h['total']) . ')', $po['prev'] ?? [])) . ' under the same PO number.') !!}
@endif
<div style="margin-top:12px"></div>
<x-tabs :tabs="$tabs" :active="$tab" />

@if ($tab === 'doc')
<div class="podoc">
    <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap"><div style="flex:1"><h2>PURCHASE ORDER</h2><div class="mut">{{ $full }} · {{ Fmt::dD($po['created']) }}</div></div><span class="wm">APPROVED · {{ $A['id'] }} v{{ $po['ver'] }}</span></div>
    <div class="grid g2" style="margin-top:14px">
        <div><b>Buyer</b><br>Osman Interlinings Limited (OIL)<br>{{ $po['billTo'] }}<br><span class="mut">Ship-to:</span> {{ $po['shipTo'] }}</div>
        <div><b>Supplier</b><br>{{ $sp['name'] }}<br>{{ $sp['contact'] ?? '' }} · {{ $sp['email'] ?? '' }}<br>{{ $sp['id'] }}</div>
    </div>
    <table><thead><tr><th>Material</th><th>Supplier product</th><th>HS code</th><th>Qty / UOM</th><th>Unit price (USD)</th><th>Amount (USD)</th></tr></thead><tbody>
    @foreach ($po['lines'] as $l)
        @php $mat = Masters::material($l['pid']); @endphp
        <tr><td>{{ $l['pid'] }} · {{ $mat['name'] }}<br><small>{{ $mat['comp'] }}</small></td><td>{{ $l['name'] }}</td><td>{{ $l['hs'] ?: '—' }}</td><td>{{ Fmt::n0($l['qty']) }} {{ $mat['uom'] }}</td><td>{{ number_format($l['price'], 3, '.', '') }}</td><td>{{ Fmt::n0($l['amt']) }}</td></tr>
    @endforeach
    <tr><th colspan="5" style="text-align:right">Total</th><th>USD {{ Fmt::n0($po['total']) }}</th></tr></tbody></table>
    <div class="grid g2">
        <div><b>Incoterms 2020</b><br>{{ $po['inc'] }} {!! $po['place'] ? e($po['place']) : '<span style="color:#e11d48">⚠ named place missing</span>' !!}</div>
        <div><b>Payment terms</b><br>{{ $po['payment'] }}</div>
        <div><b>Shipment</b><br>By {{ Fmt::dD($po['ship']) }} · port of loading {{ $po['place'] }}</div>
        <div><b>Quantity tolerance</b><br>{!! $miss($po['tol']) !!}</div>
        <div><b>Origin · partial · transshipment</b><br>{{ $po['origin'] ?: '—' }} · {{ $po['partial'] ?: '—' }} · {{ $po['transship'] ?: '—' }}</div>
        <div><b>Port of discharge</b><br>{{ $po['dest'] ?: '—' }}</div>
        <div style="grid-column:1/-1"><b>Claims / replacement provisions</b><br>{!! $miss($po['claims']) !!}</div>
        <div><b>Packing / marking</b><br>{{ $po['packing'] }}</div>
        <div><b>Required documents</b><br>{{ $po['docs'] }}</div>
        <div><b>References</b><br>RFQ {{ $c->s['rfq']['no'] }} · Award {{ $A['id'] }} · Quote {{ $iv['quoRef'] ?? '—' }}</div>
        <div><b>Notes</b><br>{{ $po['notes'] ?: '—' }}</div>
    </div>
    <p class="mut sm" style="margin-top:12px">Claims / replacement and standard clauses: [PO-TERMS-ANNEX — Open Policy Item 11]. Digitally approved · checksum {{ $po['chk'] }} · the supplier may only <b>Accept</b> or <b>Dispute</b> — never modify.</p>
</div>
@elseif ($tab === 'chk')
<div class="card"><h3>Automatic dispatch conditions <span class="sp"></span>{!! Ui::pill($st) !!}</h3><div class="cp">
    @foreach ($chk as $x)
        <div class="chk"><span>{!! $x['ok'] ? '<span class="pill p-ok">✓ pass</span>' : '<span class="pill p-bad">✗ fail</span>' !!} <b>{{ $x['k'] }}</b>@unless ($x['ok'])<br><small class="mut">{{ $x['why'] }}</small>@endunless</span>
            @unless ($x['ok'])
                @if (str_starts_with($x['k'], 'Supplier email'))
                    <a class="btn sm pri" href="{{ route('sup.show', $po['sid']) }}">Verify contact…</a>
                @endif
                @if (str_starts_with($x['k'], 'No unresolved') && $svc->dev($c, $po) && Perm::can('po.correct'))
                    <x-post :action="route('po.devok')" :params="['no' => $po['no']]" class="btn sm pri">Record acceptance</x-post>
                @endif
                @if (str_starts_with($x['k'], 'Required') && $editable && Perm::can('po.edit'))
                    <button class="btn sm pri" type="button" data-open="po-edit">Edit PO details</button>
                @endif
            @endunless
        </div>
    @endforeach
    <p class="mut sm" style="margin-top:10px">All five must pass. Any failure sends the PO to the correction queue instead of the supplier. A different legal PO is never regenerated under the same identifier without a revision.</p>
</div></div>
@elseif ($tab === 'mail')
@php $pv = $svc->preview($c, $po); @endphp
<div class="mail"><div class="hd">To: {{ $sp['contact'] ?? '' }} &lt;{{ $sp['email'] ?? '' }}&gt;<br>From: {{ $c->s['sender'] }}<br>Attachment: {{ $full }}.pdf (approved snapshot)<br>Subject: {{ $pv['subject'] }}</div>{{ $pv['body'] }}</div>
@if ($mails->count())
    <div class="card" style="margin-top:12px"><h3>Archived messages</h3><div class="cp">
        @foreach ($mails as $mm)
            <div class="chk"><span><b class="sm">{{ $mm->id }}</b> · template {{ $mm->code }} · {{ $mm->status }}<br><small class="mut">{{ $mm->to }} · {{ Fmt::dS($mm->ts_ms) }}</small></span></div>
        @endforeach
    </div></div>
@endif
@else
<div class="card"><div class="cp tl">
    @forelse ($events as $a)
        <div><b class="sm">{{ $a->action }}</b><div class="sm">{{ $a->detail }}</div><small>{{ $a->who }} · {{ Fmt::dS($a->ts_ms) }}</small></div>
    @empty
        <div class="empty">No events.</div>
    @endforelse
</div></div>
@endif

@if ($editable && Perm::can('po.edit'))
    <x-dialog id="po-edit" :title="'Edit PO details · ' . $po['no']" :action="route('po.update')" ok="Save PO details">
        <input type="hidden" name="no" value="{{ $po['no'] }}">
        <div class="alert info" style="margin-bottom:10px"><div class="t">Commercial fields (supplier, price, quantity, Incoterms, payment, delivery) are locked by the approval. Only operational PO fields can be edited here.</div></div>
        <div class="fg">
            <x-field name="billTo" label="Bill-to address" :value="$po['billTo']" full />
            <x-field name="shipTo" label="Ship-to / delivery address" :value="$po['shipTo']" full />
            <x-field name="tol" label="Quantity tolerance" :value="$po['tol']" />
            <x-field name="packing" label="Packing / marking" :value="$po['packing']" />
            <x-field name="docs" label="Required trade documents" :value="$po['docs']" full />
            <x-field name="notes" label="Notes to supplier" type="textarea" :value="$po['notes']" :rows="2" full />
        </div>
    </x-dialog>
@endif
@endsection
