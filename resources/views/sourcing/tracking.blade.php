@extends('layouts.app')
@section('title', 'Link & Deadline Admin')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('src.rfq') }}">Sourcing / RFQ</a> / Link &amp; Deadline Admin
@endsection
@section('subheading')Secure links, reminders, authorised extensions and revocation. At cut-off, final submission is disabled server-side; drafts stay unevaluated.
@endsection
@section('actions')
    @if ($c)
        <span class="pill p-acc">{{ $c->id }}</span>
        @if (\App\Domain\Perm::can('rfq.close'))
            <x-post :action="route('rfq.close', $c->id)" :class="'btn ' . ($c->s['inv'] && !$c->rfqClosed() ? 'warnb' : '')" confirm="Apply cut-off now? Open supplier links are revoked and final submission is disabled." :disabled="!$c->s['inv'] || $c->rfqClosed()">Close RFQ at cut-off</x-post>
        @endif
    @endif
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\RfqService;
    use App\Support\Ui;
    $sups = Masters::suppliers();
    $canRem = Perm::can('rfq.remind');
    $canExt = Perm::can('rfq.extend');
    $canRev = Perm::can('rfq.revoke');
    $st = fn ($k) => $c ? count(array_filter($c->s['inv'], fn ($i) => $c->ivState($i) === $k)) : 0;
    $svc = app(RfqService::class);
    $cols = [
        ['k' => 'c', 't' => 'Case', 's' => fn ($r) => $r['c']->id],
        ['k' => 's', 't' => 'Supplier', 's' => fn ($r) => $sups[$r['i']['sid']]['name'] ?? $r['i']['sid'], 'r' => fn ($r) => '<b>' . e($sups[$r['i']['sid']]['name'] ?? $r['i']['sid']) . '</b><div class="mut sm">token ' . e(\Illuminate\Support\Str::limit($r['i']['tok'] ?? '', 17, '…')) . '</div>'],
        ['k' => 'st', 't' => 'Link state', 's' => fn ($r) => $r['c']->ivState($r['i']), 'r' => fn ($r) => Ui::pill($r['c']->ivState($r['i'])) . (count($r['i']['ext'] ?? []) ? '<div class="mut sm">extended ×' . count($r['i']['ext']) . '</div>' : '')],
        ['k' => 'dl', 't' => 'Deadline (China · Bangladesh)', 's' => fn ($r) => $r['i']['deadline'], 'r' => fn ($r) => '<span class="sm">' . e(Fmt::bothTz($r['i']['deadline'])) . '</span>' . (count($r['i']['ext'] ?? []) ? '<div class="mut sm">original ' . e(Fmt::dD($r['i']['origDeadline'] ?? null)) . '</div>' : '')],
        ['k' => 'ip', 't' => 'Last access (masked IP)', 's' => fn ($r) => $r['i']['ip'] ?? '', 'r' => fn ($r) => !empty($r['i']['ip']) ? '<code>' . e($r['i']['ip']) . '</code>' : '—'],
        ['k' => 'q', 't' => 'Quote', 's' => fn ($r) => $r['i']['quoRef'] ?? '', 'r' => fn ($r) => !empty($r['i']['submitted']) ? e($r['i']['quoRef'] . ' · rev ' . $r['i']['rev']) : '—'],
    ];
    $btn = fn ($url, $r, $label, $cls = 'btn sm', $confirm = null, $title = null) => '<form method="post" action="' . e($url) . '" style="display:inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="sid" value="' . e($r['i']['sid']) . '"><button type="submit" class="' . $cls . '"' . ($title ? ' title="' . e($title) . '"' : '') . '>' . $label . '</button></form>';
    $actions = function ($r) use ($btn, $canRem, $canExt, $canRev, $svc) {
        $cid = $r['c']->id;
        $open = !$r['c']->ivDone($r['i']);
        $h = '';
        if ($open && $canRem) {
            $h .= $btn(route('rfq.remind', $cid), $r, 'Remind') . ' ';
        }
        if ($canExt) {
            $fill = e(json_encode(['c' => $cid, 'sid' => $r['i']['sid'], 'sname' => Masters::supplier($r['i']['sid'])['name'], 'orig' => Fmt::dCN($r['i']['deadline']), 'd' => $svc->suggestExtension($r['i']), 'r' => '']));
            $h .= '<button type="button" class="btn sm" data-open="iv-ext" data-fill="' . $fill . '" data-action="' . e(route('rfq.extend', $cid)) . '">Extend</button> ';
        }
        if ($open && $canRev) {
            $h .= $btn(route('rfq.revoke', $cid), $r, '🔒', 'btn sm gh', 'Revoke this supplier link? The supplier will no longer be able to open or submit.', 'Revoke link') . ' ';
        }
        return $h . '<a class="btn sm gh" href="' . e(route('portal', ['pCase' => $cid, 'pSup' => $r['i']['sid'], 'pMode' => 'rfq'])) . '">Portal</a>';
    };
    $filters = [
        ['k' => 'c', 'label' => 'Case', 'get' => fn ($r) => $r['c']->id, 'opts' => array_map(fn ($x) => $x->id, $cases)],
        ['k' => 'st', 'label' => 'Link state', 'get' => fn ($r) => $r['c']->ivState($r['i']), 'opts' => ['Sent', 'Viewed', 'Draft started', 'Submitted', 'Expired', 'Revoked']],
    ];
@endphp
@if ($c)
    {!! Ui::flowBar($c, 'src.track') !!}
    {!! Ui::kpiRow([
        ['Invited', count($c->s['inv'])],
        ['Submitted', $st('Submitted'), ['cls' => 'ok']],
        ['Draft / viewed', $st('Draft started') + $st('Viewed') + $st('Sent'), ['cls' => 'warn']],
        ['Expired / revoked', $st('Expired') + $st('Revoked'), ['cls' => $st('Expired') ? 'bad' : '']],
        ['RFQ state', $c->s['inv'] ? ($c->rfqClosed() ? 'Closed' : 'Open') : '—'],
    ]) !!}
@endif
<div style="margin-top:12px">
    <x-dtable id="trk" :cols="$cols" :rows="$rows" :filters="$filters" :actions="$actions" empty="No RFQ links yet" empty-hint="Send an RFQ first." />
</div>

@if ($canExt)
    <x-dialog id="iv-ext" title="Extend deadline" :action="route('rfq.extend', $c?->id ?? 'x')" ok="Approve extension">
        <input type="hidden" name="sid" value="{{ old('sid') }}">
        <div class="alert info" style="margin-bottom:10px"><div class="t">Authorised extension only. The original deadline is retained, the supplier is notified (template D) and an audit entry is written.</div></div>
        <p class="sm">Supplier: <b data-fill-text="sname">{{ old('sid') }}</b></p>
        <div class="fg">
            <x-field name="orig" label="Current deadline" readonly full />
            <x-field name="d" label="New deadline date (closes 17:00 China time)" type="date" required />
            <x-field name="r" label="Reason for extension" type="textarea" :rows="2" required full />
        </div>
    </x-dialog>
    <script>
    // The extend form posts to the invitation's own case: switch the form action when a row's Extend button opens the dialog.
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-open="iv-ext"]'); if (!b) return;
        var f = document.querySelector('#dlg-iv-ext form'); if (f && b.getAttribute('data-action')) f.setAttribute('action', b.getAttribute('data-action'));
    });
    </script>
@endif
@endsection
