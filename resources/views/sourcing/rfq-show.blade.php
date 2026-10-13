@extends('layouts.app')
@php
    use App\Domain\Deadline;
    use App\Domain\Clock;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\RfqService;
    use App\Support\Ui;
    $has = !empty($c->s['rfq']['no']);
    $no = $c->s['rfq']['no'] ?? '';
    $drafts = $c->s['drafts'];
    $sent = count(array_filter($drafts, fn ($d) => $d['status'] === 'Sent'));
    $sched = count(array_filter($drafts, fn ($d) => $d['status'] === 'Scheduled'));
    $blk = count(array_filter($drafts, fn ($d) => $d['status'] === 'Blocked'));
    $canSend = Perm::can('rfq.send');
    $title = $has ? $no . ' · V' . $c->s['rfq']['ver'] : 'RFQ not found';
@endphp
@section('title', $title)
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">RFQs</a> / {{ $has ? $no : $c->id }}
@endsection
@section('heading'){{ $title }}
@endsection
@section('subheading'){{ $c->id }} · {{ $c->s['booking']['serial'] ?? '' }} · {{ !empty($c->s['rfq']['urgent']) ? 'Urgent window (2 working days)' : 'Standard window (3 working days)' }}
@endsection
@section('actions')
    @if ($has)
        <span class="pill p-acc">{{ RfqService::status($c) }}</span>
        @if (Perm::can('rfq.create') && $c->s['booking']['confirmed'])
            <button class="btn" type="button" data-open="rfq-form">Add suppliers</button>
        @endif
    @endif
@endsection

@section('content')
@if (!$has)
    {!! Ui::empty('No RFQ for this case yet', 'Create the RFQ from the confirmed booking.' . (Perm::can('rfq.create') && $c->s['booking']['confirmed'] ? ' <button class="btn pri" type="button" data-open="rfq-form">New RFQ</button>' : '')) !!}
@else
    {!! Ui::flowBar($c, 'src.rfq') !!}
    @if ($blk)
        {!! Ui::alert('bad', $blk . ' draft(s) blocked', 'A supplier-country calendar is not approved. Approve it in Administration, then re-open “Add suppliers”.', '<a class="btn sm" href="' . e(route('ad.cal')) . '">Open calendars</a>') !!}
    @endif
    <div style="margin-top:12px">{!! Ui::kpiRow([['Suppliers', count($drafts)], ['Sent', $sent, ['cls' => 'ok']], ['Scheduled', $sched], ['Blocked', $blk, ['cls' => $blk ? 'bad' : '']], ['Materials', count($c->s['booking']['lines'])]]) !!}</div>
    <div class="grid g21" style="margin-top:12px">
        <div>
            <form method="post" id="drf-form" class="card tbl">
                @csrf
                <div class="tb-bar">
                    <b>RFQ drafts</b>
                    <select id="drf-status" title="Filter by status" style="width:auto" onchange="var v=this.value;document.querySelectorAll('#drf-form tbody tr[data-st]').forEach(function(r){r.style.display=(!v||r.dataset.st===v)?'':'none'})"><option value="">Status: all</option>@foreach (['Draft','Scheduled','Sent','Blocked'] as $so)<option>{{ $so }}</option>@endforeach</select><span class="sp" style="flex:1"></span>
                    @if ($canSend)
                        <button class="btn sm pri" type="submit" formaction="{{ route('rfq.send', $c->id) }}">Send selected now</button>
                        <button class="btn sm" type="button" data-open="rfq-sched">Schedule selected…</button>
                        <button class="btn sm" type="submit" formaction="{{ route('rfq.run', $c->id) }}" title="Dispatches drafts whose scheduled time has arrived (the scheduler)">Run due schedule</button>
                        <button class="btn sm" type="submit" formaction="{{ route('rfq.run', $c->id) }}" name="all" value="1" title="Demo: dispatch every scheduled draft now">Run all scheduled now</button>
                    @endif
                </div>
                <div class="tw"><table>
                    <thead><tr>@if ($canSend)<th style="width:28px"><input type="checkbox" title="Select all drafts" onclick="document.querySelectorAll('#drf-form input[name=&quot;ids[]&quot;]:not(:disabled)').forEach(function(x){x.checked=this.checked}.bind(this))"></th>@endif<th>Supplier</th><th>Materials / qty</th><th>Quotation deadline</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($drafts as $d)
                        @php
                            $sp = Masters::supplier($d['sid']);
                            $iv = $c->invOf($d['sid']);
                            $dl = $iv ? ['ts' => $iv['deadline']] : Deadline::calc($sp['cal'] ?? '', Clock::ms(), !empty($d['urgent']) ? 2 : 3);
                            $pick = in_array($d['status'], ['Draft', 'Failed'], true);
                        @endphp
                        <tr data-st="{{ $d['status'] }}">
                            @if ($canSend)<td><input type="checkbox" name="ids[]" value="{{ $d['id'] }}" @disabled(!$pick)></td>@endif
                            <td><b>{{ $sp['name'] }}</b><div class="mut sm">{{ $d['sid'] }} · calendar {{ $sp['cal'] ?? '' }}</div></td>
                            <td>@foreach ($d['pids'] as $p)<div class="sm">{{ $p }} · {{ Masters::spName($d['sid'], $p) }} · {{ Fmt::n0($c->reqQty($p)) }} {{ Masters::material($p)['uom'] }}</div>@endforeach</td>
                            <td>@if (isset($dl['err']))<span class="sm" style="color:var(--bad)">{{ $dl['err'] }}</span>@else<span class="sm">{{ Fmt::dCN($dl['ts']) }}</span><div class="mut sm">{{ Fmt::tz($dl['ts'], 'H:i') }} BST</div>@endif</td>
                            <td>{!! Ui::pill($d['status']) !!}@if ($d['status'] === 'Scheduled')<div class="mut sm">{{ Fmt::dS($d['at'] ?? null) }}</div>@endif</td>
                            <td class="nw r"><a class="btn sm" href="{{ request()->fullUrlWithQuery(['preview' => $d['id']]) }}"><x-icon name="eye" :size="13" /> Preview</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">No drafts yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </form>
        </div>
        <div class="grid" style="gap:12px">
            <div class="card"><h3>RFQ conditions</h3><div class="cp">{!! Ui::dl(array_values(array_filter([
                ['Sender', e($c->s['sender'])], ['Sampling', e($c->s['rfq']['sample'] ?? 'Yes')], ['Documents', e($c->s['rfq']['docs'] ?? 'TDS, COA, test report')], ['Notes', e($c->s['rfq']['notes'] ?? '') ?: '—'],
                !empty($c->s['rfq']['urgent']) ? ['Urgent reason', e($c->s['rfq']['reason'] ?? '')] : null,
                ['Extra required parameters', e(implode(', ', array_map(fn ($k) => collect(config('quote_fields.extra_req'))->firstWhere(0, $k)[1] ?? $k, $c->s['rfq']['reqFields'] ?? []))) ?: '—'],
            ]))) !!}</div></div>
            <div class="ins"><small>Deadline rules</small>Counted in supplier-country working days from the <b>next</b> working day after sending, including holidays and make-up workdays. Closes 17:00 China = 15:00 Bangladesh. Unapproved calendars block sending.</div>
            <div class="ins"><small>Link security</small>One random token per supplier, only its hash is stored. HTTPS, expiry, rate limits, no prices in URLs, no rival visibility.</div>
        </div>
    </div>

    @if ($canSend)
        {{-- prototype "Schedule send" dialog; the inputs/submit belong to #drf-form so the ticked drafts are posted with them --}}
        <x-dialog id="rfq-sched" title="Schedule send">
            <div class="fg"><label class="full"><span>Send at (Bangladesh time)</span><input type="datetime-local" name="t" form="drf-form" required value="{{ gmdate('Y-m-d\TH:i', intdiv(Clock::ms(), 1000) + 6 * 3600 + 86400) }}"></label></div>
            <p class="mut sm">The deadline is calculated from the actual send time. Tick the drafts to schedule first.</p>
            <x-slot:foot><button type="button" class="btn" data-close>Cancel</button><button class="btn pri" type="submit" form="drf-form" formaction="{{ route('rfq.schedule', $c->id) }}">Schedule</button></x-slot:foot>
        </x-dialog>
    @endif

    @if ($preview && ($pd = collect($drafts)->firstWhere('id', $preview)))
        @php($pm = app(RfqService::class)->preview($c, $pd))
        <div class="ov" id="dlg-mail-prev" data-dialog><div class="dlg wide" role="dialog" aria-label="Draft email">
            <div class="dh"><h3>Draft email · {{ Masters::supplier($pd['sid'])['name'] }}</h3><button type="button" class="btn gh sm" data-close aria-label="Close">×</button></div>
            <div class="db">
                @if (isset($pm['err']))
                    {!! Ui::alert('bad', 'Blocked', e($pm['err'])) !!}
                @else
                    <div class="mail"><div class="hd">To: {{ $pm['to'] }}<br>From: {{ $pm['from'] }}<br>Subject: {{ $pm['subject'] }}</div>{{ $pm['body'] }}</div>
                @endif
            </div>
            <div class="df"><a class="btn" href="{{ request()->fullUrlWithQuery(['preview' => null]) }}">Close</a></div>
        </div></div>
    @endif

    @if (Perm::can('rfq.create') && $c->s['booking']['confirmed'])
        @include('sourcing._rfq-dialog', ['c' => $c])
    @endif
@endif
@endsection
