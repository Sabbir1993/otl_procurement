@extends('layouts.app')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\PiService;
    use App\Services\PoService;
    use App\Support\Ui;
    $no = $po['no'];
    $sp = Masters::supplier($po['sid']);
    $locked = $po['ack'] !== 'Accepted';
    $docs = PiService::IMPDOCS;
    $done = count(array_filter(array_keys($docs), fn ($i) => !empty($lc['docs'][$i])));
    $m = $pi ? $pis->match($c, $po, $pi) : [];
    $piOk = $pi && !array_filter($m, fn ($x) => !$x['ok']);
    $open = !empty($lc['open']);
    $steps = [
        ['PO accepted', $po['ack'] === 'Accepted' ? 'done' : ($po['ack'] === 'Disputed' ? 'bad' : 'on'), $po['ack']],
        ['PI matched', $pi && !empty($pi['approved']) ? 'done' : ($pi ? 'on' : ($locked ? '' : 'on')), $pi ? (!empty($pi['approved']) ? 'Approved' : ($piOk ? 'Ready to approve' : 'Mismatch')) : 'Awaiting PI'],
        ['Import & banking checks', $lc['docsDone'] ? 'done' : ($pi && !empty($pi['approved']) ? 'on' : ''), "{$done}/" . count($docs)],
        ['LC approval', $lc['appr'] ? 'done' : ($lc['docsDone'] ? 'on' : ''), $lc['appr'] ? $lc['appr']['by'] : 'Finance control'],
        ['LC opened', $open ? 'done' : ($lc['appr'] ? 'on' : ''), $open ? $lc['open']['ref'] : 'Import / Logistics'],
    ];
    $b = $pi ?: $pis->seed($c, $po, ['sys' => '(assigned on save)']);   // form defaults - does not consume a PI number
    $canRecord = Perm::can('pi.record') && !$locked && !$open;
    $bankPending = $pi && !empty($pi['bankChanged']) && !(!empty($pi['bankV']['fin']) && !empty($pi['bankV']['sup']));
@endphp
@section('title', 'PI & LC · ' . $po['no'])
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('imp.pi') }}">PI &amp; Banking</a> / {{ $po['no'] }}
@endsection
@section('heading')PI &amp; LC · {{ PoService::full($po) }}
@endsection
@section('subheading'){{ $sp['name'] }} · {{ $c->id }} · PO USD {{ Fmt::n0($po['total']) }}
@endsection
@section('actions')
    @if (Perm::can('pi.record'))
        <button class="btn {{ $canRecord ? 'pri' : '' }}" type="button" data-open="pi-form" @disabled(!$canRecord)>{{ $pi ? 'Record PI revision' : 'Record supplier PI' }}</button>
    @endif
@endsection

@section('content')
<div class="steps">
    @foreach ($steps as [$t, $s, $d])
        <div class="{{ $s === 'done' ? 'done' : ($s === 'on' ? 'on' : '') }}"><b>{{ $t }}</b><br><small>{{ $d }}</small></div>
    @endforeach
</div>
@if ($locked)
    {!! Ui::alert($po['ack'] === 'Disputed' ? 'bad' : 'warn', $po['ack'] === 'Disputed' ? 'PO disputed' : 'PO not accepted yet', $po['ack'] === 'Disputed' ? 'PI progression is stopped until the dispute is resolved and re-approved.' : 'PI intake opens after supplier acceptance.') !!}
@endif
@if ($pi && !empty($pi['bankChanged']) && empty($pi['approved']))
    {!! Ui::alert('bad', 'Beneficiary bank changed — dual control required', 'Processing is restricted until Finance and a Supervisor have each verified the new bank details independently (' . ((!empty($pi['bankV']['fin']) ? 1 : 0) + (!empty($pi['bankV']['sup']) ? 1 : 0)) . '/2 done).') !!}
@endif

<div class="grid g21" style="margin-top:12px">
<div class="grid" style="gap:12px">
    <div class="card tbl">
        <h3>PO vs PI @if ($pi) {!! !empty($pi['approved']) ? Ui::pill('Approved', 'p-ok') : ($piOk ? Ui::pill('Matched', 'p-info') : Ui::pill('Mismatch')) !!} @endif</h3>
        @if ($pi)
            <div class="cp mut sm">PI {{ $pi['no'] }} · {{ $pi['sys'] ?? '' }} · rev {{ $pi['ver'] }} · {{ Fmt::dD($pi['date']) }}</div>
            <div class="tw"><table><thead><tr><th>Match point</th><th>Result</th><th>Detail</th></tr></thead><tbody>
                @foreach ($m as $x)
                    <tr><td>{{ $x['n'] }}</td><td>{!! Ui::pill($x['ok'] ? 'Matched' : 'Mismatch') !!}</td><td class="sm">{{ $x['d'] }}</td></tr>
                @endforeach
            </tbody></table></div>
            @if (empty($pi['approved']))
                <div class="cp" style="display:flex;gap:8px;flex-wrap:wrap">
                    @if ($piOk)
                        @if (Perm::can('pi.match'))
                            <x-post :action="route('pi.approve')" :params="['no' => $no]" class="btn pri">Approve for import processing</x-post>
                        @endif
                    @elseif (Perm::can('pi.record'))
                        <x-post :action="route('pi.clarify')" :params="['no' => $no]" class="btn warnb">Send clarification / change request</x-post>
                    @endif
                    @if ($bankPending)
                        @if (Perm::can('bank.verify1'))
                            <x-post :action="route('pi.bank')" :params="['no' => $no, 'k' => 'fin']" class="btn" :disabled="!empty($pi['bankV']['fin'])">Verify bank — Finance {{ !empty($pi['bankV']['fin']) ? '✓' : '' }}</x-post>
                        @endif
                        @if (Perm::can('bank.verify2'))
                            <x-post :action="route('pi.bank')" :params="['no' => $no, 'k' => 'sup']" class="btn" :disabled="!empty($pi['bankV']['sup'])">Verify bank — Supervisor {{ !empty($pi['bankV']['sup']) ? '✓' : '' }}</x-post>
                        @endif
                        @unless (Perm::can('bank.verify1') || Perm::can('bank.verify2'))
                            <span class="mut sm">Bank verification: Finance {{ !empty($pi['bankV']['fin']) ? '✓' : '(pending)' }} · Supervisor {{ !empty($pi['bankV']['sup']) ? '✓' : '(pending)' }}</span>
                        @endunless
                    @endif
                </div>
            @endif
        @else
            <div class="empty"><b>No PI recorded</b>Record the supplier’s PI — the form is pre-filled from the PO so you only change what differs.</div>
        @endif
    </div>

    <div class="card"><h3>Import documentation &amp; banking checks <span class="sp"></span><span class="pill">{{ Perm::roleName('imp') }}</span></h3><div class="cp">
        @php $enabled = $pi && !empty($pi['approved']) && !$lc['docsDone'] && Perm::can('imp.docs'); @endphp
        @foreach ($docs as $i => $t)
            <form method="post" action="{{ route('pi.doc') }}" class="chk" style="cursor:pointer" data-autosubmit>
                @csrf <input type="hidden" name="no" value="{{ $no }}"><input type="hidden" name="i" value="{{ $i }}"><input type="hidden" name="checked" value="0">
                <label style="display:flex;gap:10px;align-items:center;cursor:pointer"><input type="checkbox" name="checked" value="1" @checked(!empty($lc['docs'][$i])) @disabled(!$enabled)><span class="sm">{{ $t }}</span></label>
            </form>
        @endforeach
        <div class="alert warn" style="margin-top:10px"><div class="t">Indicative checklist only — Finance / Import must validate the exact documents and Bangladesh Bank / authorized-dealer-bank requirements for the import method (Open Policy Items 10–11). Domestic purchases may bypass LC/TT.</div></div>
        <div style="margin-top:10px">
            @if ($lc['docsDone'])
                <span class="pill p-ok">Checks complete</span>
            @elseif (Perm::can('imp.docs'))
                <x-post :action="route('pi.docsdone')" :params="['no' => $no]" class="btn pri" :disabled="!($pi && !empty($pi['approved']) && $done === count($docs))">Mark checks complete</x-post>
            @else
                <span class="mut sm">Import / Logistics completes the checks.</span>
            @endif
        </div>
    </div></div>
</div>

<div class="grid" style="gap:12px">
    <div class="card"><h3>LC approval</h3><div class="cp">
        @if ($lc['appr'])
            <span class="pill p-ok">Approved by {{ $lc['appr']['by'] }} · {{ Fmt::dS($lc['appr']['at']) }}</span>
        @else
            <p class="mut sm">Finance control. Import cannot bypass it. The specification does not name the LC approver — approver per OIL policy.</p>
            @if (Perm::can('lc.approve'))
                <x-post :action="route('lc.approve')" :params="['no' => $no]" class="btn pri" :disabled="!$lc['docsDone']">Approve LC</x-post>
            @endif
        @endif
    </div></div>
    <div class="card"><h3>LC opening</h3><div class="cp">
        @if ($open)
            {!! Ui::dl([['LC reference', e($lc['open']['ref'])], ['Bank', e($lc['open']['bank'])], ['Amount', 'USD ' . Fmt::n0($lc['open']['amt'])], ['Expiry', Fmt::dD($lc['open']['exp'])]]) !!}
            <div style="margin-top:8px"><span class="pill p-ok">LC opened against validated PI &amp; approved PO</span></div>
        @else
            <p class="mut sm">Opens against the validated PI and approved PO.</p>
            @if (Perm::can('lc.open'))
                <button class="btn pri" type="button" data-open="lc-open" @disabled(!$lc['appr'])>Open LC…</button>
            @endif
            <p class="mut sm">LC record ID format is not defined in the specification — to confirm.</p>
        @endif
    </div></div>
    <div class="ins"><small>Privacy</small>Beneficiary bank details are shown masked as [BENEFICIARY-ACCOUNT-PLACEHOLDER]. Changes need dual control and are audited.</div>
    @if ($events->count())
        <div class="card"><h3>Activity</h3><div class="cp tl">
            @foreach ($events as $a)
                <div><b class="sm">{{ $a->action }}</b><div class="sm">{{ $a->detail }}</div><small>{{ $a->who }} · {{ Fmt::dS($a->ts_ms) }}</small></div>
            @endforeach
        </div></div>
    @endif
</div>
</div>

@if ($canRecord)
    <x-dialog id="pi-form" :title="'Record supplier PI · ' . $no" :action="route('pi.record')" ok="Save PI & compare" wide>
        <input type="hidden" name="no" value="{{ $no }}">
        <div class="fg">
            <x-field name="pino" label="PI reference" :value="$b['no']" required />
            <x-field name="seller" label="Seller on PI" :value="$b['seller']" />
            <x-field name="buyer" label="Buyer on PI" :value="$b['buyer']" />
        </div>
        <div class="tw" style="margin:10px 0"><table><thead><tr><th>Material</th><th>Description on PI</th><th>HS code</th><th>Qty</th><th>Unit price</th></tr></thead><tbody>
            @foreach ($b['lines'] as $i => $l)
                <tr><td>{{ $l['pid'] }}</td>
                    <td><input name="lines[{{ $i }}][desc]" value="{{ old("lines.$i.desc", $l['desc']) }}"></td>
                    <td><input name="lines[{{ $i }}][hs]" value="{{ old("lines.$i.hs", $l['hs'] ?? '') }}" style="width:110px"></td>
                    <td><input name="lines[{{ $i }}][qty]" type="number" step="any" value="{{ old("lines.$i.qty", $l['qty']) }}"></td>
                    <td><input name="lines[{{ $i }}][price]" type="number" step="0.001" value="{{ old("lines.$i.price", $l['price']) }}"></td></tr>
            @endforeach
        </tbody></table></div>
        <div class="fg">
            <x-field name="cur" label="Currency" type="select" :value="$b['cur']" :options="['USD', 'EUR', 'CNY']" />
            <x-field name="inc" label="Incoterms" type="select" :value="$b['inc']" :options="['FOB', 'CIF', 'CFR', 'FCA', 'EXW']" />
            <x-field name="pl" label="Named place" :value="$b['place']" />
            <x-field name="pay" label="Payment terms" :value="$b['payment']" />
            <x-field name="sh" label="Ship by" type="date" :value="Fmt::isoD($b['shipBy'])" />
            <label class="f"><span>Beneficiary bank</span><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="bc" value="1" @checked(old('bc', $b['bankChanged']))> Differs from vendor master</label><small class="mut">Masked: [BENEFICIARY-ACCOUNT-PLACEHOLDER]</small></label>
        </div>
    </x-dialog>
@endif

@if (!$open && Perm::can('lc.open') && $lc['appr'] && $pi)
    <x-dialog id="lc-open" :title="'Open LC · ' . $no" :action="route('lc.open')" ok="Record LC opened">
        <input type="hidden" name="no" value="{{ $no }}">
        <div class="alert warn" style="margin-bottom:10px"><div class="t">Use placeholders in this prototype — never enter real bank or LC identifiers.</div></div>
        <div class="fg">
            <x-field name="b" label="Authorized dealer bank" value="[AD-BANK-NAME]" required />
            <x-field name="r" label="LC reference" value="[LC-REFERENCE]" required />
            <x-field name="a" label="Amount (USD, from validated PI)" :value="Fmt::n0(PiService::piTotal($pi))" readonly />
            <x-field name="e" label="LC expiry" type="date" value="2027-01-15" required />
        </div>
    </x-dialog>
@endif

@if (request('record') && $canRecord)
    <script>(function(){var d=document.getElementById('dlg-pi-form');if(d)d.hidden=false;})();</script>
@endif
@endsection
