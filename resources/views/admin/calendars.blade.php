@extends('layouts.app')
@section('title', 'Working-Day Calendars')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Calendars
@endsection
@section('heading', 'Working-Day Calendars')
@section('subheading', 'Deadlines use the supplier country’s approved calendar — never a global Monday–Friday. Unverified holiday data blocks RFQ publication.')

@section('content')
@php
    use App\Support\Ui;
    $can = \App\Domain\Perm::can('admin.cfg');
    $dd = fn ($list) => $list ? implode(', ', array_map(fn ($d) => date('d M Y', strtotime($d . ' UTC')), $list)) : '—';
@endphp
<div class="grid g2">
    @foreach ($cals as $k => $c)
        <div class="card cp">
            <div style="display:flex;gap:8px;align-items:center"><h3 style="padding:0;border:0">{{ $c['name'] }}</h3><span style="flex:1"></span>{!! Ui::pill($c['approved'] ? 'Active' : 'Blocked', $c['approved'] ? 'p-ok' : 'p-bad') !!}</div>
            {!! Ui::dl([
                ['Time zone', e($c['tz']) . ' (UTC' . ($c['off'] >= 0 ? '+' : '') . str_pad($c['off'], 2, '0', STR_PAD_LEFT) . ':00)'],
                ['Closing hour', $c['close'] . ':00 local'],
                ['Verified years', $c['years'] ? e(implode(', ', $c['years'])) : '<span style="color:var(--bad)">none</span>'],
                ['Public holidays', e($dd($c['holidays']))],
                ['Make-up workdays', e($dd($c['makeup']))],
            ]) !!}
            <p class="mut sm">{{ $k === 'CN' ? 'Demo data — an administrator must verify against the official schedule each year.' : 'No approved calendar yet. RFQs to suppliers on this calendar are blocked.' }}</p>
            @if ($can)
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    @if ($c['approved'])
                        <x-post :action="route('ad.cal.off', $k)" class="btn warnb" confirm="Revoke approval of the {{ $k }} calendar? RFQs to these suppliers will be blocked.">Revoke approval</x-post>
                    @else
                        <x-post :action="route('ad.cal.on', $k)" class="btn pri">Approve demo calendar</x-post>
                    @endif
                    <button class="btn" type="button" data-open="cal-day-{{ $k }}">Add holiday / make-up day</button>
                </div>
                <form method="post" action="{{ route('ad.cal.year', $k) }}" style="display:flex;gap:6px;margin-top:8px;align-items:center">@csrf
                    <input type="number" name="year" min="2020" max="2100" placeholder="Year" required style="width:90px">
                    <button class="btn sm" name="op" value="verify">Verify year</button>
                    <button class="btn sm" name="op" value="unverify">Un-verify</button>
                </form>
                <x-dialog id="cal-day-{{ $k }}" title="Add calendar day · {{ $k }}" :action="route('ad.cal.day', $k)" ok="Add">
                    <div class="fg">
                        <x-field name="date" label="Date" type="date" required />
                        <x-field name="kind" label="Type" type="select" value="holiday" :options="['holiday' => 'Public holiday (non-working)', 'makeup' => 'Make-up working day']" />
                    </div>
                </x-dialog>
            @endif
        </div>
    @endforeach
</div>
<div class="card cp" style="margin-top:12px"><h3 style="padding:0;border:0">Deadline rule engine</h3><ol class="sm" style="margin:8px 0 0 18px;line-height:1.8"><li>Confirm supplier country and its assigned calendar.</li><li>Calendar includes public holidays and make-up workdays; yearly data verified by an administrator.</li><li>Store UTC; show China time <b>and</b> Bangladesh time with zone name and offset.</li><li>Closing = Nth eligible working day after the next working day following sending, at the configured hour.</li><li>Reminder one supplier-country working day before close.</li><li>At cut-off, revoke final submit server-side; keep submitted records.</li><li>Extension needs a new date, reason and approver; the original deadline is kept.</li><li>Calendar unavailable → block publication and alert Procurement.</li></ol></div>
@endsection
