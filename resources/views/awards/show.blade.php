@extends('layouts.app')
@php
    use App\Domain\CaseBook;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\ApprovalService;
    use App\Support\Ui;

    $A = $c->s['appr'];
    $has = $A['status'] !== 'none';
    $label = ['mkt' => 'Marketing ACK', 'fin' => 'Finance APP', 'ed' => 'ED final APP', 'approved' => 'Approved', 'returned' => 'Returned', 'rejected' => 'Rejected', 'none' => '—'];
@endphp
@section('title', $has ? "{$A['id']} · v{$A['version']}" : 'Award not found')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('aw.appr') }}">Approval Queue</a> / {{ $has ? $A['id'] : $c->id }}
@endsection
@section('heading'){{ $has ? "{$A['id']} · v{$A['version']}" : 'Award not found' }}
@endsection
@if ($has)
    @section('subheading'){{ $c->id }} · {{ $c->name }} · checksum {{ $A['chk'] }}
    @endsection
    @section('actions'){!! Ui::pill($label[$A['status']], $A['status'] === 'approved' ? 'p-ok' : (in_array($A['status'], ['returned', 'rejected']) ? 'p-bad' : 'p-warn')) !!}
    @endsection
@endif

@section('content')
@if (!$has)
    {!! Ui::empty('No award package for this case') !!}
@else
@php
    $aw = $A['snap'] ?? [];
    $sum = fn ($rows, $k) => array_sum(array_column($rows, $k));
    $tot = $sum($aw, 'amt');
    $est = $sum($aw, 'est');
    $stale = $A['status'] !== 'approved' && $A['chk'] !== $c->awardChk();
    $sav = $c->savings();
    $by = fn ($k) => $A[$k] ?? null;
    $stg = [
        ['Procurement recommendation', $A['rec'] ? 'done' : 'on', $A['rec'] ? $A['rec']['by'] . ' · ' . Fmt::dS($A['rec']['at']) : ''],
    ];
    foreach (['mkt' => 'Need · qty · sample', 'fin' => 'Budget · FX · exposure', 'ed' => 'Bulk validation'] as $k => $hint) {
        $d = $by($k);
        $stg[] = [CaseBook::APPR_STEPS[$k], $d ? ($d['d'] === 'ok' ? 'done' : 'bad') : ($A['status'] === $k ? 'on' : ''), $d ? $d['by'] . ' · ' . Fmt::dS($d['at']) : $hint];
    }
    $flags = [];
    foreach ($c->s['booking']['lines'] as $l) {
        $x = $c->csOf($l['pid']);
        if (!empty($x['excl'])) { $flags[] = "{$l['pid']} excluded — {$x['exclWhy']}"; }
        foreach (array_keys($x['waiver']) as $s) { $flags[] = "{$l['pid']}: MOQ waiver for " . Masters::supplier($s)['name']; }
        foreach (array_keys($x['alloc']) as $s) {
            if ($c->s['quotes'][$s][$l['pid']]['sample'] === 'Conditional') { $flags[] = "{$l['pid']}: conditional sample pass — deviation must be approved"; }
            if ($dv = $c->devFor($s, $l['pid'])) { $flags[] = "{$l['pid']}: supplier-declared deviation — {$dv}"; }
            if ($bl = $c->techBlanks($s, $l['pid'])) { $flags[] = "{$l['pid']}: " . count($bl) . ' blank technical parameter(s) — ' . implode(', ', $bl); }
        }
        $lo = $c->lowestLanded($l['pid']);
        $k = array_keys($x['alloc']);
        if ($k && $lo && $k[0] !== $lo) { $flags[] = "{$l['pid']}: not lowest landed cost — " . (($x['just'] ?? '') ?: 'no rationale'); }
    }
    $steps = [
        'mkt' => ['Marketing ACK', 'Acknowledge', 'Return for clarification'],
        'fin' => ['Finance approval', 'Approve', 'Return'],
        'ed' => ['ED final approval', 'Bulk validate & approve', 'Return'],
    ];
    $st = $A['status'];
    $inChain = isset($steps[$st]);
    $mine = $inChain && Perm::can('appr.' . $st);
@endphp
<div class="steps">
    @foreach ($stg as [$t, $s, $d])
        <div class="{{ $s === 'done' ? 'done' : ($s === 'on' ? 'on' : '') }}"><b>{{ $t }}</b><br><small>{{ $d }}</small></div>
    @endforeach
</div>
@if ($stale){!! Ui::alert('bad', 'Decision pack out of date', 'The CS changed after submission. Resubmit.') !!}@endif
{!! Ui::kpiRow([
    ['Material value', Fmt::usd($tot), ['sub' => 'USD']],
    ['Est. landed', Fmt::usd($est)],
    ['Suppliers / lines', count(array_unique(array_column($aw, 'sid'))) . ' / ' . count($aw)],
    ['Negotiated savings', $sav ? Fmt::usd($sav) : '—'],
    ['Review flags', count($flags), ['cls' => $flags ? 'warn' : 'ok']],
]) !!}
<div class="grid g21" style="margin-top:12px">
    <div class="grid" style="gap:12px">
        <div class="card tbl"><h3>Award lines</h3><div class="tw"><table>
            <thead><tr><th>Material</th><th>Supplier</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th><th class="r">Est. landed</th><th>Finance remark</th></tr></thead>
            <tbody>
            @foreach ($aw as $x)
                @php $pm = Masters::material($x['pid']); @endphp
                <tr><td><b>{{ $x['pid'] }}</b><div class="mut sm">{{ $pm['name'] }}</div></td><td>{{ Masters::supplier($x['sid'])['name'] }}</td><td class="r">{{ Fmt::n0($x['qty']) }} {{ $pm['uom'] }}</td><td class="r">{{ Fmt::usd3($x['price']) }}</td><td class="r"><b>{{ Fmt::usd($x['amt']) }}</b></td><td class="r">{{ Fmt::usd($x['est']) }}</td><td class="sm">{!! ($f = $c->csOf($x['pid'])['fin'] ?? '') ? e($f) : '<span class="mut">—</span>' !!}</td></tr>
            @endforeach
            </tbody></table></div></div>
        @if ($flags)
            <div class="card"><h3>Review flags</h3><div class="cp">@foreach ($flags as $f)<div class="chk"><span>⚠ {{ $f }}</span></div>@endforeach</div></div>
        @endif
        <div class="card"><h3>Decision history</h3><div class="cp tl">
            @foreach (array_reverse($A['hist']) as $h)
                <div><b class="sm">{{ $h['a'] }}</b><small>{{ $h['by'] }} · {{ Fmt::dS($h['ts']) }}{!! !empty($h['r']) ? ' · “' . e($h['r']) . '”' : '' !!}</small></div>
            @endforeach
        </div></div>
    </div>
    <div class="grid" style="gap:12px">
        @if ($st === 'approved')
            <div class="card cp">
                {!! Ui::alert('ok', 'Approved & locked', 'Supplier, quantity, rates, currency, Incoterms, payment and shipment are frozen. POs were generated automatically.') !!}
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn pri" href="{{ route('po.reg') }}">Open purchase orders</a>
                    @if (Perm::can('appr.change') || Perm::can('cs.negotiate'))<button class="btn warnb" type="button" data-open="appr-chg">Raise change request</button>@endif
                </div>
            </div>
        @elseif (in_array($st, ['returned', 'rejected']))
            <div class="card cp">
                {!! Ui::alert('bad', 'Package ' . $st, 'Procurement revises the CS (negotiation) and resubmits as a new version — approvals restart at Marketing.') !!}
                <div style="margin-top:10px"><form method="post" action="{{ route('case.work', $c->id) }}" style="display:inline">@csrf<button class="btn pri" type="submit">Make working case &amp; revise</button></form>
                    <a class="btn" href="{{ route('src.cs') }}">Open comparative statement</a></div>
            </div>
        @elseif ($inChain)
            @php $m = $steps[$st]; @endphp
            <div class="card"><h3>{{ $m[0] }}</h3><div class="cp">
                {!! Ui::alert($mine ? 'info' : 'warn', $mine ? 'It is your decision' : 'Waiting for another role', $mine ? 'Remarks are mandatory when returning or rejecting.' : 'Waiting for ' . e(Perm::roleName($st)) . '. Switch role (avatar menu) to act.') !!}
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
                    <button class="btn pri" type="button" data-open="appr-ok" @disabled(!$mine)>{{ $m[1] }}…</button>
                    <button class="btn warnb" type="button" data-open="appr-ret" @disabled(!$mine)>{{ $m[2] }}…</button>
                    @if ($st !== 'mkt')<button class="btn bad" type="button" data-open="appr-rej" @disabled(!$mine)>Reject…</button>@endif
                </div>
                <p class="mut sm" style="margin-top:10px">{{ $st === 'fin' ? 'Finance reviews budget, cash requirement, FX exposure, freight/duty assumptions, bank charges, payment terms and LC/advance exposure. Add product-wise remarks on the CS.' : ($st === 'ed' ? 'Bulk validation approves the full package, locks it and triggers PO generation.' : 'Marketing confirms fit to requested materials, quantities, sample outcome and delivery. This is not the final financial approval.') }}</p>
            </div></div>
        @endif
    </div>
</div>

@if ($inChain && $mine)
    <x-dialog id="appr-ok" :title="$steps[$st][0] . ' — ' . ($st === 'ed' ? 'Bulk validate & approve' : 'Approve')" :action="route('appr.decide', $c->id)" :ok="$st === 'ed' ? 'Bulk validate & approve' : 'Approve'">
        <input type="hidden" name="d" value="ok">
        @if ($st === 'ed')<div class="alert warn" style="margin-bottom:10px"><div class="t">Approval locks supplier, quantity, rates, currency, Incoterms, payment and shipment commitments, then POs are generated automatically.</div></div>@endif
        <div class="fg"><x-field name="r" label="Remarks (optional)" type="textarea" rows="3" full /></div>
    </x-dialog>
    <x-dialog id="appr-ret" :title="$steps[$st][0] . ' — Return for revision'" :action="route('appr.decide', $c->id)" ok="Return for revision" danger>
        <input type="hidden" name="d" value="ret">
        <div class="fg"><x-field name="r" label="Remarks (required)" type="textarea" rows="3" full required /></div>
    </x-dialog>
    @if ($st !== 'mkt')
        <x-dialog id="appr-rej" :title="$steps[$st][0] . ' — Reject'" :action="route('appr.decide', $c->id)" ok="Reject" danger>
            <input type="hidden" name="d" value="rej">
            <div class="fg"><x-field name="r" label="Remarks (required)" type="textarea" rows="3" full required /></div>
        </x-dialog>
    @endif
@endif
@if ($st === 'approved' && (Perm::can('appr.change') || Perm::can('cs.negotiate')))
    <x-dialog id="appr-chg" title="Raise change request" :action="route('appr.change', $c->id)" ok="Freeze & route for re-approval" danger>
        <div class="alert warn" style="margin-bottom:10px"><div class="t">A change to these fields after approval freezes unsent POs and routes the case for re-approval (Marketing → Finance → ED). Nothing is overwritten silently.</div></div>
        <div class="fg">
            <x-field name="f" label="Field to change" type="select" full :options="ApprovalService::CHANGE_FIELDS" />
            <x-field name="r" label="Reason" type="textarea" rows="2" full required />
        </div>
    </x-dialog>
@endif
@endif
@endsection
