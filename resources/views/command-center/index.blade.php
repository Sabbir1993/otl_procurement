@extends('layouts.app')
@section('title', 'Command Center')
@section('crumbs')Command Center
@endsection
@section('heading')Command Center
@endsection
@section('subheading')Every import purchase case, from requisition to LC opening — what is waiting, who must act, and what is next.
@endsection
@section('actions')
    @if (\App\Domain\Perm::can('case.new'))
        <a class="btn pri" href="{{ route('cases', ['new' => 1]) }}"><x-icon name="plus" :size="15" /> New purchase case</a>
    @endif
@endsection

@section('content')
@php
    use App\Domain\CaseBook;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Support\ChartUi;
    use App\Support\Ui;
    $role = Perm::roleName();
    $col = array_merge(array_column(CaseBook::FLOW, 0), ['Completed']);
    $pendLines = $c ? count(array_filter($c->s['demand'], fn ($d) => $d['status'] === 'pending')) : 0;
    $cnt = $c ? $c->csCounts() : ['done' => 0, 'total' => 0];
    $nAct = count($active);
@endphp
<div class="hero"><h2>Import purchases · {{ $nAct }} active case{{ $nAct === 1 ? '' : 's' }}</h2>
    <p>@if (count($tasks))You have <b>{{ count($tasks) }}</b> item(s) waiting as {{ $role }}.@else Nothing is waiting for {{ $role }}. Switch role to see other queues.@endif</p>
    <div class="hs">
        <div><b>{{ $nAct }}</b><small>Active cases</small></div>
        <div><b>{{ $poAwaiting }}</b><small>POs awaiting acknowledgment</small></div>
        <div><b>{{ $rfqOpen }}</b><small>RFQ links open</small></div>
        <div><b>{{ $open }}</b><small>Open exceptions</small></div>
    </div>
</div>

@if ($c)
    {!! ChartUi::kpiRow([
        ['Working case', e($c->id), ['sub' => e($c->month), 'go' => 'case/' . $c->id]],
        ['Requisition lines pending', $pendLines, ['cls' => $pendLines ? 'warn' : '', 'go' => 'rq.board']],
        ['Quotes received', count(array_filter($c->s['inv'], fn ($i) => !empty($i['submitted']))) . '/' . count($c->s['inv']), ['go' => 'src.track']],
        ['Products decided', $cnt['done'] . '/' . $cnt['total'], ['go' => 'src.cs']],
        ['Awarded value', Fmt::usd(array_sum(array_column($c->lineAwards(), 'amt'))), ['sub' => 'material, USD', 'go' => 'aw.rec']],
        ['Open exceptions', $open, ['cls' => $open ? 'bad' : 'ok', 'go' => 'exc', 'tid' => 'exc', 'f' => ['st' => 'Open']]],
    ], 6) !!}
    @php($next = $c->nextAction())
    <div style="margin-top:12px">
        {!! Ui::flowBar($c) !!}
        <div class="alert info nextc"><div class="t"><b>Next step · {{ $c->id }}</b><span>{{ $next['t'] }}@if ($next['who']) <span class="mut">· {{ Perm::roleName($next['who']) }}</span>@endif</span></div>
            @if (ChartUi::canGo($next['go']))<div class="a"><a class="btn pri sm" href="{{ Ui::url($next['go']) }}">Go there</a></div>@endif
        </div>
    </div>
@else
    {!! Ui::empty('No purchase cases yet', 'Create the first case to start the import flow.') !!}
@endif

<div class="grid g21" style="margin-top:12px">
    <div class="grid" style="gap:12px">
        <div class="card"><h3>Needs your attention <span class="sp"></span><span class="pill">{{ count($tasks) }}</span></h3>
            <div class="cp">
                @forelse (array_slice($tasks, 0, 9) as $t)
                    <div class="chk"><span><b>{{ $t['t'] }}</b><br><small class="mut">{{ $t['cid'] }}</small></span>
                        @if (ChartUi::canGo($t['go']))
                            <x-post :action="route('home.goCase')" :params="['c' => $t['cid'], 'to' => $t['go']]" :class="'btn sm ' . ($t['k'] === 'bad' ? 'bad' : 'pri')">Open</x-post>
                        @endif
                    </div>
                @empty
                    <div class="empty"><b>All clear</b>Nothing is waiting for this role.</div>
                @endforelse
            </div>
        </div>
        <div class="card"><h3>Case pipeline</h3>
            <div class="cp"><div class="kan" style="grid-auto-columns:minmax(150px,1fr)">
                @foreach ($col as $i => $n)
                    @php($L = array_filter($cases, fn ($x) => min($x->flowIdx(), 8) === $i))
                    <div class="kc"><h4>{{ $n }}<span>{{ count($L) }}</span></h4>
                        <div class="kb">
                            @forelse ($L as $x)
                                <a class="kcard" href="{{ route('case.show', $x->id) }}" style="display:block;color:inherit;text-decoration:none"><b>{{ $x->id }}</b><div class="sm">{{ $x->month }}</div><small>{{ \Illuminate\Support\Str::limit($x->nextAction()['t'], 60, '') }}</small></a>
                            @empty
                                <div class="mut sm" style="padding:6px">—</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div></div>
        </div>
    </div>
    <div class="grid" style="gap:12px">
        <div class="card"><h3>Upcoming deadlines</h3>
            <div class="cp">
                @forelse (array_slice($deadlines, 0, 6) as $d)
                    <div class="chk"><span><b>{{ Masters::supplier($d['i']['sid'])['name'] }}</b><br><small class="mut">{{ $d['c']->s['rfq']['no'] }} · {{ Fmt::bothTz($d['i']['deadline']) }}</small></span></div>
                @empty
                    <div class="empty">No open RFQ deadlines.</div>
                @endforelse
            </div>
        </div>
        <div class="card"><h3>Spend by supplier <span class="sp"></span><small class="mut">awarded, USD</small></h3>
            <div class="cp">
                @if ($spend)
                    {!! ChartUi::barRows(array_map(fn ($s) => [Masters::supplier($s[0])['name'], $s[1]], $spend), fn ($v) => Fmt::usd($v)) !!}
                @else
                    <div class="empty">Appears after awards are decided.</div>
                @endif
            </div>
        </div>
        <div class="card"><h3>Recent activity <span class="sp"></span>@if (ChartUi::canGo('audit'))<a href="{{ route('audit') }}" class="sm">All</a>@endif</h3>
            <div class="cp tl">
                @foreach ($recent as $a)
                    <div><b class="sm">{{ $a->action }}</b> <span class="mut sm">{{ $a->ref }}</span><small>{{ $a->who }} · {{ Fmt::dS($a->ts_ms) }}</small></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
