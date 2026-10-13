@extends('layouts.app')
@php
    use App\Domain\CaseBook;
    use App\Domain\Perm;
    use App\Support\Ui;
@endphp
@section('title', 'Comparative Statement')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">Sourcing / RFQ</a> / Comparative Statement
@endsection
@section('heading')Comparative Statement
@endsection
@section('subheading')Normalised on landed cost — the lowest unit price is not automatically the cheapest offer. Estimates are shown separately from supplier-confirmed figures.
@endsection
@if ($c && $ready)
    @php
        $cnt = $c->csCounts();
    @endphp
    @section('actions')<span class="pill p-acc">{{ $c->id }}</span><span class="pill p-ok">Decided {{ $cnt['done'] }}</span><span class="pill p-warn">Pending {{ $cnt['pend'] }}</span>
    @endsection
@endif

@section('content')
@if (!$c)
    {!! Ui::empty('No working case', 'Create or select a purchase case first.') !!}
@elseif (!$ready)
    {!! Ui::flowBar($c, 'src.cs') !!}
    {!! Ui::empty('No quotations to compare', 'Confirm the booking, send RFQs and receive supplier quotations first.<br><a class="btn pri" href="' . e(route(empty($c->s['inv']) ? 'src.rfq' : 'src.track')) . '">Go to ' . (empty($c->s['inv']) ? 'RFQs' : 'link admin') . '</a>') !!}
@else
@php
    $A = $c->s['appr'];
    $locked = !empty($c->s['csLocked']);
    $idx = array_search($pid, $pids, true);
    $nav = fn ($p) => request()->fullUrlWithQuery(['pid' => $p, 'preview' => null]);
    $tabs = ['product' => 'Product CS', 'tech' => 'Technical compliance', 'bulk' => 'Bulk CS report'];
@endphp
{!! Ui::flowBar($c, 'src.cs') !!}
@if ($locked)
    {!! Ui::alert('info', 'Comparison locked', 'Award package ' . e($A['id']) . ' v' . e($A['version']) . ' is in approval (' . e($A['status']) . '). Changes after approval need a change request.') !!}
@endif
@if (!$c->rfqClosed())
    @php
        $nSub = count(array_filter($c->s['inv'], fn ($i) => !empty($i['submitted'])));
        $nInv = count($c->s['inv']);
        $closeBtn = \App\Domain\Perm::can('rfq.close') && $nInv
            ? '<form method="post" action="' . e(route('rfq.close', $c->id)) . '" style="display:inline" data-confirm="Close the RFQ now? ' . ($nInv - $nSub) . ' supplier link(s) without a submitted quotation will be revoked and the suppliers notified. Only the ' . $nSub . ' submitted quotation(s) will be evaluated.">' . csrf_field() . '<button class="btn sm pri" type="submit"' . ($nSub ? '' : ' disabled title="No quotation has been submitted yet"') . '>Close RFQ now (' . $nSub . ' of ' . $nInv . ' quoted)</button></form>'
            : '';
    @endphp
    {!! Ui::alert('warn', 'RFQ still open', 'You can prepare the comparison, but submission for approval waits until the RFQ is closed (deadline, revoke or cut-off).' . ($closeBtn ? '' : ' Closing needs the Procurement Supervisor (permission rfq.close).'), $closeBtn) !!}
@endif
<div style="margin-top:12px"></div>
<x-tabs :tabs="$tabs" :active="$tab" />
<div class="card">
    <div class="tb-bar">
        @if ($tab !== 'bulk')
            <a class="btn sm {{ $idx < 1 ? 'disabled' : '' }}" @if ($idx >= 1) href="{{ $nav($pids[$idx - 1]) }}" @endif>‹ Previous</a>
            <form method="get" action="{{ route('src.cs') }}" data-autosubmit style="display:inline">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select name="pid" style="max-width:300px">
                    @foreach ($c->s['booking']['lines'] as $l)
                        <option value="{{ $l['pid'] }}" @selected($l['pid'] === $pid)>{{ $l['pid'] }} · {{ \App\Domain\Masters::material($l['pid'])['name'] }} — {{ $c->csStat($l['pid']) }}</option>
                    @endforeach
                </select>
            </form>
            <a class="btn sm {{ $idx >= count($pids) - 1 ? 'disabled' : '' }}" @if ($idx < count($pids) - 1) href="{{ $nav($pids[$idx + 1]) }}" @endif>Next ›</a>
        @else
            <a class="btn sm" href="{{ route('cs.csv', $c->id) }}"><x-icon name="dl" :size="14" /> Export CSV</a>
        @endif
    </div>
    @if ($tab === 'product')
        @include('sourcing.cs._product')
    @elseif ($tab === 'tech')
        @include('sourcing.cs._tech')
    @else
        @include('sourcing.cs._bulk')
    @endif
</div>

@if ($tab === 'product')
    @php
        $rem = count(array_filter($c->s['booking']['lines'], fn ($l) => $c->csStat($l['pid']) === 'Pending' && $c->bids($l['pid'])));
    @endphp
    <div class="card" style="margin-top:12px"><h3>Bulk supplier select <span class="sp"></span><span class="pill p-info">Remaining products only · {{ $rem }}</span></h3>
        <div class="cp" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <form method="get" action="{{ route('src.cs') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="tab" value="product"><input type="hidden" name="pid" value="{{ $pid }}"><input type="hidden" name="preview" value="1">
                <select name="strat" style="width:auto">
                    @foreach (CaseBook::STRAT as $k => $v)<option value="{{ $k }}" @selected($strat === $k)>{{ $v }}</option>@endforeach
                </select>
                @if (Perm::can('cs.select'))<button class="btn pri" type="submit" @disabled($locked)>Preview &amp; apply to remaining</button>@endif
            </form>
            <span class="mut sm" style="flex:1;min-width:240px">Skips failed samples, conditional passes and offers whose MOQ exceeds the required quantity — those stay for a manual decision. Index uses illustrative weights (Policy Item 7).</span>
        </div>
        @if ($plan)
            <div class="cp" style="border-top:1px solid var(--line)">
                <b>Bulk select · {{ CaseBook::STRAT[$strat] }}</b>
                @if ($plan['res'])
                    <div class="tw"><table><thead><tr><th>Material</th><th>Selected supplier</th><th class="r">Qty</th><th class="r">Landed</th></tr></thead><tbody>
                        @foreach ($plan['res'] as $x)
                            <tr><td>{{ $x['l']['pid'] }} · {{ \App\Domain\Masters::material($x['l']['pid'])['name'] }}</td><td>{{ $x['b']['s']['name'] }}</td><td class="r">{{ \App\Domain\Fmt::n0($x['l']['qty']) }}</td><td class="r">{{ \App\Domain\Fmt::usd(CaseBook::landed($x['b']['q'])) }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
                @if ($plan['un'])
                    {!! Ui::alert('warn', count($plan['un']) . ' product(s) need a manual decision', 'No eligible offer under this rule: ' . e(implode(', ', array_column($plan['un'], 'pid')))) !!}
                @endif
                @if (!$plan['res'] && !$plan['un'])<div class="mut sm">No remaining products with quotations.</div>@endif
                <div style="margin-top:10px;display:flex;gap:8px">
                    @if ($plan['res'] && Perm::can('cs.select'))
                        <x-post :action="route('cs.bulk', $c->id)" :params="['strat' => $strat]" class="btn pri" :disabled="$locked">Apply to {{ count($plan['res']) }} product(s)</x-post>
                    @endif
                    <a class="btn" href="{{ request()->fullUrlWithQuery(['preview' => null]) }}">Cancel</a>
                </div>
            </div>
        @endif
    </div>
@endif

<div class="card cp" style="margin-top:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="flex:1"><b>Award recommendation</b>
        <div class="mut sm">{{ $cnt['pend'] ? "{$cnt['pend']} product(s) still pending — decide or exclude each." : ($locked ? "Submitted as {$A['id']} v{$A['version']}." : 'All products decided. Review the final recommendation and submit for sequential approval.') }}</div>
    </div>
    <a class="btn pri {{ $cnt['pend'] ? 'disabled' : '' }}" @if (!$cnt['pend']) href="{{ route('aw.rec') }}" @endif>Go to final recommendation</a>
</div>

{{-- dialogs --}}
@if (!$locked)
    @if (Perm::can('cs.select'))
        <x-dialog id="cs-alloc" title="Award supplier" :action="route('cs.allocate', $c->id)" ok="Save award">
            <input type="hidden" name="pid" value="{{ old('pid') }}"><input type="hidden" name="sid" value="{{ old('sid') }}">
            <div class="ins"><b data-fill-text="lbl">{{ old('lbl') }}</b><br><small data-fill-text="offer"></small></div>
            <input type="hidden" name="lbl" value="{{ old('lbl') }}">
            <div class="sm" style="margin:6px 0;color:var(--warn)" data-fill-text="warn"></div>
            <div class="fg">
                <x-field name="mode" label="Award" type="select" full :options="['full' => 'Full required quantity', 'partial' => 'Partial quantity']" />
                <x-field name="qty" label="Partial quantity (used when Award = Partial)" type="number" min="1" step="any" full />
                <x-field name="waiver" label="MOQ waiver reason" full placeholder="Required if the awarded qty is below the supplier MOQ" />
                <x-field name="just" label="Rationale / justification" type="textarea" rows="2" full />
            </div>
        </x-dialog>
        <x-dialog id="cs-excl" title="Exclude for re-tender" :action="route('cs.exclude', $c->id)" ok="Exclude">
            <input type="hidden" name="pid" value="{{ old('pid') }}">
            <div class="fg"><x-field name="r" label="Reason" type="textarea" rows="2" full required value="No valid quotation — re-tender / extend by authorisation." /></div>
        </x-dialog>
    @endif
    @if (Perm::can('cs.negotiate'))
        <x-dialog id="cs-neg" title="Negotiation round" :action="route('cs.negotiate', $c->id)" ok="Record round">
            <input type="hidden" name="pid" value="{{ old('pid') }}"><input type="hidden" name="sid" value="{{ old('sid') }}">
            <div class="alert info" style="margin-bottom:10px"><div class="t">The original quotation stays immutable. A negotiation round records the revised price; request a formal revised quotation where the change is material.</div></div>
            <div class="ins"><b data-fill-text="lbl"></b></div><input type="hidden" name="lbl" value="">
            <div class="fg">
                <x-field name="cur" label="Current unit price" readonly />
                <x-field name="np" label="Revised unit price (USD)" type="number" step="0.001" min="0" required />
                <x-field name="r" label="Round notes" type="textarea" rows="2" full required />
            </div>
        </x-dialog>
    @endif
@endif
@endif
@endsection
