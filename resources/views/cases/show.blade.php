@extends('layouts.app')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Support\Ui;
    $A = $c->s['appr'];
    $aw = $c->lineAwards();
    $cnt = $c->csCounts();
    $inv = $c->s['inv'];
    $pos = $c->s['pos'];
    $stage = [
        ['Requisition', 'rq.board', $c->s['frozen'] ? 'done' : 'on', count($c->s['demand']) . ' lines · ' . count(array_filter($c->s['demand'], fn ($d) => $d['status'] === 'accepted')) . ' accepted · ' . count(array_filter($c->s['demand'], fn ($d) => $d['status'] === 'pending')) . ' pending'],
        ['Booking', 'rq.booking', $c->s['booking']['confirmed'] ? 'done' : ($c->s['frozen'] ? 'on' : ''), $c->s['booking']['serial'] ?? 'Not confirmed'],
        ['RFQ', 'src.rfq', $inv ? 'done' : ($c->s['booking']['confirmed'] ? 'on' : ''), $c->s['rfq']['no'] ? $c->s['rfq']['no'] . ' · ' . count($inv) . ' sent' : 'Not created'],
        ['Quotes', 'src.track', $c->rfqClosed() ? 'done' : ($inv ? 'on' : ''), $inv ? count(array_filter($inv, fn ($i) => !empty($i['submitted']))) . '/' . count($inv) . ' submitted' : '—'],
        ['Comparison', 'src.cs', $cnt['total'] && !$cnt['pend'] ? 'done' : ($inv ? 'on' : ''), $cnt['total'] ? "{$cnt['done']}/{$cnt['total']} decided" : '—'],
        ['Approval', 'aw.appr', $A['status'] === 'approved' ? 'done' : (in_array($A['status'], ['mkt', 'fin', 'ed']) ? 'on' : ''), $A['id'] ? "{$A['id']} v{$A['version']} · {$A['status']}" : 'Not submitted'],
        ['Purchase orders', 'po.reg', $pos && !array_filter($pos, fn ($p) => $p['ack'] !== 'Accepted') ? 'done' : ($pos ? 'on' : ''), $pos ? count($pos) . ' PO · ' . count(array_filter($pos, fn ($p) => !empty($p['sent']))) . ' sent' : '—'],
        ['PI & LC', 'imp.pi', $pos && !array_filter($pos, fn ($p) => empty($c->s['lc'][$p['no']]['open'])) ? 'done' : ($c->s['pis'] ? 'on' : ''), count($c->s['pis']) . ' PI · ' . count(array_filter($c->s['lc'], fn ($l) => !empty($l['open']))) . ' LC'],
    ];
    $tabs = ['ov' => 'Overview', 'req' => 'Requisition', 'rfq' => 'RFQ &amp; quotes', 'award' => 'Comparison &amp; award', 'po' => 'POs, PI &amp; LC', 'tl' => 'Timeline'];
    $next = $c->nextAction();
@endphp
@section('title', $c->id . ' · ' . $c->name)
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('cases') }}">Purchase Cases</a> / {{ $c->id }}
@endsection
@section('heading'){{ $c->id }} · {{ $c->name }}
@endsection
@section('subheading'){{ $c->month }} · created {{ Fmt::dD($c->s['created']) }} by {{ $c->by }} @if ($c->s['booking']['serial'])· booking {{ $c->s['booking']['serial'] }}@endif
@endsection
@section('actions')
    <x-post :action="route('case.work', $c->id)" class="btn">Make working case</x-post>
@endsection

@section('content')
{!! Ui::flowBar($c) !!}
<div class="alert info nextc"><div class="t"><b>Next step · {{ $c->id }}</b><span>{{ $next['t'] }}@if ($next['who']) <span class="mut">· {{ \App\Domain\Perm::roleName($next['who']) }}</span>@endif</span></div><div class="a"><a class="btn pri sm" href="{{ Ui::url($next['go']) }}">Go there</a></div></div>
<div style="margin-top:12px"></div>
<x-tabs :tabs="$tabs" :active="$tab" />

@if ($tab === 'ov')
    <div class="grid g4">
        @foreach ($stage as $s)
            <a class="card cp" href="{{ Ui::url($s[1]) }}" style="display:block;color:inherit;text-decoration:none;border-top:3px solid {{ $s[2] === 'done' ? 'var(--ok)' : ($s[2] === 'on' ? 'var(--warn)' : 'var(--line)') }}">
                <b>{{ $s[0] }}</b> {!! $s[2] === 'done' ? Ui::pill('Done', 'p-ok') : ($s[2] === 'on' ? Ui::pill('In progress', 'p-warn') : Ui::pill('Not started')) !!}
                <div class="sm mut" style="margin-top:6px">{{ $s[3] }}</div>
            </a>
        @endforeach
    </div>
@elseif ($tab === 'req')
    <div class="card tbl"><div class="tw"><table>
        <thead><tr><th>Material</th><th class="r">Demand</th><th class="r">Accepted</th><th class="r">Rejected</th><th class="r">Pending</th><th class="r">Book qty</th></tr></thead>
        <tbody>
        @foreach (Masters::materials() as $p)
            @if (collect($c->s['demand'])->contains('pid', $p['id']))
                <tr><td><b>{{ $p['id'] }}</b> {{ $p['name'] }}</td><td class="r">{{ Fmt::n0($c->demandSum($p['id'])) }}</td><td class="r">{{ Fmt::n0($c->demandSum($p['id'], 'accepted')) }}</td><td class="r">{{ Fmt::n0($c->demandSum($p['id'], 'rejected')) }}</td><td class="r">{{ Fmt::n0($c->demandSum($p['id'], 'pending')) }}</td><td class="r">{{ $c->reqQty($p['id']) ? Fmt::n0($c->reqQty($p['id'])) : '—' }} {{ $p['uom'] }}</td></tr>
            @endif
        @endforeach
        </tbody></table></div></div>
@elseif ($tab === 'rfq')
    @if ($inv)
        <div class="card tbl"><div class="tw"><table>
            <thead><tr><th>Supplier</th><th>Link</th><th>Deadline (China · Bangladesh)</th><th>Quote</th></tr></thead>
            <tbody>
            @foreach ($inv as $i)
                <tr><td><b>{{ Masters::supplier($i['sid'])['name'] }}</b></td><td>{!! Ui::pill($c->ivState($i)) !!}</td><td class="sm">{{ Fmt::bothTz($i['deadline']) }}</td><td>{{ !empty($i['submitted']) ? $i['quoRef'] . ' · rev ' . $i['rev'] : '—' }}</td></tr>
            @endforeach
            </tbody></table></div></div>
    @else
        {!! Ui::empty('No RFQ sent yet', 'Create the RFQ after the booking is confirmed.') !!}
    @endif
@elseif ($tab === 'award')
    @if ($aw)
        <div class="card tbl"><div class="tw"><table>
            <thead><tr><th>Material</th><th>Supplier</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
            <tbody>
            @foreach ($aw as $x)
                <tr><td>{{ $x['pid'] }} · {{ Masters::material($x['pid'])['name'] }}</td><td>{{ Masters::supplier($x['sid'])['name'] }}</td><td class="r">{{ Fmt::n0($x['qty']) }}</td><td class="r">{{ Fmt::usd3($x['price']) }}</td><td class="r">{{ Fmt::usd($x['amt']) }}</td></tr>
            @endforeach
            </tbody></table></div></div>
    @else
        {!! Ui::empty('Nothing awarded yet', 'Decide the comparative statement first.') !!}
    @endif
@elseif ($tab === 'po')
    @if ($pos)
        <div class="card tbl"><div class="tw"><table>
            <thead><tr><th>PO</th><th>Supplier</th><th class="r">Value</th><th>Dispatch</th><th>Acknowledgment</th><th>PI / LC</th></tr></thead>
            <tbody>
            @foreach ($pos as $p)
                <tr class="clk" data-href="{{ route('po.show', $p['no']) }}"><td><b>{{ $p['no'] }}</b></td><td>{{ Masters::supplier($p['sid'])['name'] }}</td><td class="r">USD {{ Fmt::n0($p['total']) }}</td><td>{!! Ui::pill($p['sent'] ? $p['status'] : 'Draft') !!}</td><td>{!! Ui::pill($p['ack']) !!}</td><td>{!! Ui::pill($c->lcState($p)) !!}</td></tr>
            @endforeach
            </tbody></table></div></div>
    @else
        {!! Ui::empty('No purchase orders', 'POs are generated automatically after final approval.') !!}
    @endif
@else
    <div class="card"><div class="cp tl">
        @forelse ($events as $a)
            <div><b class="sm">{{ $a->action }}</b> <span class="mut sm">{{ $a->ref }}</span><div class="sm">{{ $a->detail }}</div><small>{{ $a->who }} · {{ Fmt::dS($a->ts_ms) }}</small></div>
        @empty
            <div class="empty">No events yet.</div>
        @endforelse
    </div></div>
@endif
@endsection
