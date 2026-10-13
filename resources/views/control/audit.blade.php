@extends('layouts.app')
@section('title', 'Audit Trail')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('exc') }}">Control</a> / Audit Trail
@endsection
@section('heading')Audit Trail
@endsection
@section('subheading')Append-only: who did what, when, on which record. Submitted and approved documents are never overwritten — prior versions are kept.
@endsection
@php
    use App\Domain\Fmt;
    use App\Domain\Perm;
    use App\Models\AuditLog;
    use App\Models\MailRecord;
    use App\Models\PurchaseCase;
    use App\Support\Ui;
    $mails = MailRecord::orderByDesc('ts_ms')->get();
    $qs = request()->query();
    $tabKey = $tab === 'mail' ? 'mails' : 'aud';
    $exp = ['tab' => $tab, 'case' => data_get($qs, "$tabKey.f.cid"), 'q' => data_get($qs, "$tabKey.q")];
@endphp
@section('actions')
    @if ($tab !== 'integ')
        <a class="btn" href="{{ route('audit.export', array_filter($exp)) }}">Export CSV</a>
    @endif
@endsection

@section('content')
<x-tabs :tabs="['log' => 'Audit log', 'mail' => 'Message archive (' . $mails->count() . ')', 'integ' => 'Integrations']" :active="$tab" />
@if ($tab === 'log')
    @php
        $rows = AuditLog::orderByDesc('ts_ms')->orderByDesc('id')->get()->map(fn ($a) => $a->toArray() + ['cid' => $a->case_id ?: ''])->all();
        $cols = [
            ['k' => 'ts_ms', 't' => 'Time (BST)', 'r' => fn ($r) => Fmt::dS($r['ts_ms'])],
            ['k' => 'who', 't' => 'User', 'r' => fn ($r) => '<b>' . e($r['who']) . '</b>'],
            ['k' => 'role', 't' => 'Role', 'r' => fn ($r) => e(Perm::roleName($r['role']) ?: $r['role'])],
            ['k' => 'action', 't' => 'Action'],
            ['k' => 'ref', 't' => 'Reference'],
            ['k' => 'cid', 't' => 'Case', 'r' => fn ($r) => $r['cid'] ?: '—'],
            ['k' => 'detail', 't' => 'Detail', 'r' => fn ($r) => '<span class="sm">' . e($r['detail']) . '</span>'],
        ];
        $filters = [['k' => 'cid', 'label' => 'Case', 'get' => fn ($r) => $r['cid'], 'opts' => PurchaseCase::orderBy('id')->pluck('id')->all()]];
    @endphp
    <x-dtable id="aud" :cols="$cols" :rows="$rows" :filters="$filters" :export="false" :per="25" empty="No audit entries yet" />
@elseif ($tab === 'mail')
    @php
        $cols = [
            ['k' => 'ts_ms', 't' => 'Time', 'r' => fn ($r) => Fmt::dS($r->ts_ms)],
            ['k' => 'id', 't' => 'Message ID', 'r' => fn ($r) => '<code>' . e($r->id) . '</code>'],
            ['k' => 'cid', 't' => 'Case', 's' => fn ($r) => $r->case_id ?: '', 'r' => fn ($r) => $r->case_id ?: '—'],
            ['k' => 'to', 't' => 'To'],
            ['k' => 'subject', 't' => 'Subject'],
            ['k' => 'status', 't' => 'Status', 'r' => fn ($r) => Ui::pill($r->status)],
        ];
        $filters = [['k' => 'cid', 'label' => 'Case', 'get' => fn ($r) => $r->case_id ?: '', 'opts' => PurchaseCase::orderBy('id')->pluck('id')->all()]];
        $actions = fn ($r) => '<a class="btn sm" href="' . e(request()->fullUrlWithQuery(['view' => $r->id])) . '">View</a>';
    @endphp
    <x-dtable id="mails" :cols="$cols" :rows="$mails->all()" :filters="$filters" :actions="$actions" :export="false" empty="No archived messages yet" />
    @if ($mail)
        <div class="ov" id="dlg-mailview" data-dialog>
            <div class="dlg wide" role="dialog" aria-label="Archived message">
                <div class="dh"><h3>Archived message · {{ $mail->id }}</h3><a class="btn gh sm" href="{{ request()->fullUrlWithQuery(['view' => null]) }}" aria-label="Close">×</a></div>
                <div class="db"><div class="mail"><div class="hd">To: {{ $mail->to }}<br>Subject: {{ $mail->subject }}<br>Sent: {{ Fmt::dBD($mail->ts_ms) }} · {{ $mail->status }}</div>{{ $mail->body }}</div></div>
                <div class="df"><a class="btn" href="{{ request()->fullUrlWithQuery(['view' => null]) }}">Close</a></div>
            </div>
        </div>
    @endif
@else
    @php
        $cols = [
            ['k' => 'n', 't' => 'Integration', 'r' => fn ($r) => '<b>' . e($r['n']) . '</b>'],
            ['k' => 'p', 't' => 'Purpose'],
            ['k' => 's', 't' => 'Status', 'r' => fn ($r) => $r['s'] === 'TBD' ? '<span class="pill">TBD · Policy item 12</span>' : Ui::pill($r['s'], $r['s'] === 'Active' ? 'p-ok' : null) . (isset($r['note']) ? ' <span class="mut sm">' . e($r['note']) . '</span>' : '')],
        ];
    @endphp
    <x-dtable id="integ" :cols="$cols" :rows="$integ" :export="false" />
@endif
@endsection
