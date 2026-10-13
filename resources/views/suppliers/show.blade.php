@extends('layouts.app')
@section('title', $s['name'])
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('sup.dir') }}">Suppliers</a> / {{ $s['id'] }}
@endsection
@section('heading', $s['name'])
@section('subheading', $s['id'] . ' · ' . $s['cc'] . ' · ' . $s['contact'])
@section('actions')
    @if (\App\Domain\Perm::can('sup.edit'))
        <button class="btn" type="button" data-open="sup-form" data-fill="{{ json_encode(array_intersect_key($s, array_flip(['id', 'name', 'cc', 'cal', 'contact', 'desig', 'address', 'email', 'phone', 'status']))) }}"><x-icon name="edit" :size="14" /> Edit</button>
    @endif
@endsection

@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\Perm;
    use App\Services\SupplierService;
    use App\Support\Ui;
    $full = Perm::can('sup.edit');   // phone / address are sensitive: full value only for roles that maintain the master
@endphp
<x-tabs :tabs="['pf' => 'Profile', 'mat' => 'Materials', 'rfq' => 'RFQ history', 'po' => 'Purchase orders']" :active="$tab" />

@if ($tab === 'pf')
    <div class="grid g2">
        <div class="card cp">{!! Ui::dl([
            ['Supplier ID', e($s['id'])], ['Legal name', e($s['name'])], ['Country', e($s['cc'])], ['Work calendar', e($s['cal'])],
            ['Supplier since', e($s['since'])], ['Status', Ui::pill($s['status'])],
        ]) !!}</div>
        <div class="card cp">{!! Ui::dl([
            ['Contact', e($s['contact']) . ' · ' . e($s['desig'] ?? '')],
            ['Email', e($full ? $s['email'] : SupplierService::maskEmail($s['email']))],
            ['Phone', e($full ? $s['phone'] : SupplierService::mask($s['phone']))],
            ['Address', e($full ? $s['address'] : SupplierService::mask($s['address']))],
            ['Verification', Ui::pill($s['emailVerified'] ? 'Verified' : 'Unverified')],
            ['Bank details', '[BENEFICIARY-ACCOUNT-PLACEHOLDER] — masked'],
        ]) !!}
            @if (!$s['emailVerified'] && Perm::can('po.verify'))
                <div style="margin-top:10px"><button class="btn pri" type="button" data-open="sup-verify">Record callback verification…</button></div>
            @elseif (!$s['emailVerified'])
                <p class="mut sm" style="margin-top:10px">Waiting for a Procurement Supervisor to record the independent callback.</p>
            @endif
        </div>
    </div>
@elseif ($tab === 'mat')
    @php
        $cols = [
            ['k' => 'pid', 't' => 'Material', 'r' => fn ($r) => '<b>' . e($r['pid']) . '</b> ' . e($r['mname'])],
            ['k' => 'name', 't' => 'Supplier product'],
            ['k' => 'st', 't' => 'Status', 's' => fn ($r) => $r['active'] ? 'Active' : 'Inactive', 'r' => fn ($r) => Ui::pill($r['active'] ? 'Active' : 'Inactive')],
        ];
    @endphp
    <x-dtable id="supm" :cols="$cols" :rows="$products" empty="No materials mapped" />
@elseif ($tab === 'rfq')
    @php
        $cols = [
            ['k' => 'c', 't' => 'Case'], ['k' => 'rfq', 't' => 'RFQ'],
            ['k' => 'st', 't' => 'Link', 'r' => fn ($r) => Ui::pill($r['st'])],
            ['k' => 'deadline', 't' => 'Deadline', 'r' => fn ($r) => e(Fmt::bothTz($r['deadline']))],
            ['k' => 'q', 't' => 'Quote'],
        ];
    @endphp
    <x-dtable id="supr" :cols="$cols" :rows="$rfqs" empty="No RFQs sent to this supplier" />
@elseif ($tab === 'po')
    @php
        $cols = [
            ['k' => 'no', 't' => 'PO', 'r' => fn ($r) => '<b>' . e($r['no']) . '</b>'], ['k' => 'c', 't' => 'Case'],
            ['k' => 'total', 't' => 'Value', 'cls' => 'r', 'r' => fn ($r) => 'USD ' . Fmt::n0($r['total'])],
            ['k' => 'ack', 't' => 'Acknowledgment', 'r' => fn ($r) => Ui::pill($r['ack'])],
        ];
    @endphp
    <x-dtable id="supp" :cols="$cols" :rows="$pos" :row-link="fn ($r) => route('po.show', $r['no'])" empty="No purchase orders" />
@endif

@if ($full)
    @include('suppliers._form')
@endif
@if (Perm::can('po.verify') && !$s['emailVerified'])
    <x-dialog id="sup-verify" title="Verify supplier contact" :action="route('sup.verify', $s['id'])" ok="Mark verified">
        <p class="sm">Call a number that is <b>already in the vendor master</b> — never one taken from the email requesting the change.</p>
        <div class="fg"><x-field name="ref" label="Callback reference / call log ID" required full /></div>
    </x-dialog>
@endif
@endsection
