@extends('layouts.app')
@section('title', 'Materials & Masters')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Materials &amp; Masters
@endsection
@section('heading', 'Materials & Masters')
@section('subheading', 'Controlled master data used by requisitions, RFQs, the comparative statement and PO validation.')

@section('content')
@php
    use App\Domain\Perm;
    use App\Support\Ui;
    $can = Perm::can('admin.cfg');
@endphp
<x-tabs :tabs="['mat' => 'Materials', 'rsn' => 'Rejection reasons', 'po' => 'PO defaults', 'fx' => 'FX · UOM · Incoterms · Ports']" :active="$tab" />

@if ($tab === 'mat')
    @php
        $cols = [
            ['k' => 'id', 't' => 'ID', 'r' => fn ($m) => '<b>' . e($m['id']) . '</b>'],
            ['k' => 'name', 't' => 'Material'], ['k' => 'meta', 't' => 'Meta name'], ['k' => 'comp', 't' => 'Composition'],
            ['k' => 'uom', 't' => 'UOM'], ['k' => 'type', 't' => 'Type'],
        ];
        $actions = $can ? fn ($m) => '<button class="btn sm" type="button" data-open="mat-form" data-fill="' . e(json_encode(array_intersect_key($m, array_flip(['id', 'name', 'meta', 'comp', 'uom', 'type'])))) . '">Edit</button>' : null;
        $tools = $can ? '<button class="btn sm pri" type="button" data-open="mat-form" data-fill=\'{"id":"","name":"","meta":"","comp":"","uom":"m","type":"fabric"}\'>+ Add material</button>' : '';
    @endphp
    <x-dtable id="mat" :cols="$cols" :rows="$mats" :actions="$actions" :tools="$tools" />
    @if ($can)
        <x-dialog id="mat-form" title="Material" :action="route('ad.mat.save')" ok="Save material">
            <input type="hidden" name="id" value="{{ old('id') }}">
            <div class="fg">
                <x-field name="name" label="Material name" required full />
                <x-field name="meta" label="Meta name" />
                <x-field name="comp" label="Composition" full />
                <x-field name="uom" label="UOM" type="select" value="m" :options="['m', 'kg', 'yd', 'roll']" />
                <x-field name="type" label="Type" type="select" value="fabric" :options="['fabric', 'powder', 'chemical']" />
            </div>
        </x-dialog>
    @endif
@elseif ($tab === 'rsn')
    <div class="card cp" style="max-width:640px">
        <p class="mut sm">Reasons offered when rejecting a requisition line. Marketing can see them.</p>
        @foreach ($reasons as $i => $r)
            <div class="chk"><span>{{ $r }}</span>
                @if ($can)<x-post :action="route('ad.reason.del')" :params="['i' => $i]" class="btn sm gh" confirm="Remove this reason?" title="Remove">✕</x-post>@endif
            </div>
        @endforeach
        @if ($can)
            <form method="post" action="{{ route('ad.reason.add') }}" style="display:flex;gap:8px;margin-top:10px">@csrf
                <input name="r" placeholder="Add reason" required maxlength="160" style="flex:1"><button class="btn pri">Add</button>
            </form>
        @endif
    </div>
@elseif ($tab === 'po')
    <div class="card cp" style="max-width:720px">
        <p class="mut sm">Defaults copied onto every generated PO. A blank required field sends the PO to the correction queue.</p>
        <form method="post" action="{{ $can ? route('ad.settings.save') : '#' }}">@csrf
            <div class="fg">
                <x-field name="billTo" label="Bill-to address" :value="$set['billTo'] ?? ''" full :readonly="!$can" />
                <x-field name="shipTo" label="Ship-to / delivery address" :value="$set['shipTo'] ?? ''" full :readonly="!$can" />
                <x-field name="tol" label="Quantity tolerance" :value="$set['tol'] ?? ''" :readonly="!$can" />
                <x-field name="packing" label="Packing / marking" :value="$set['packing'] ?? ''" :readonly="!$can" />
                <x-field name="docs" label="Required trade documents" :value="$set['docs'] ?? ''" full :readonly="!$can" />
                <x-field name="freightPerCbm" label="Freight assumption (USD per CBM) — used only when a supplier gives CBM; 0 = not used" type="number" :value="$set['freightPerCbm'] ?? 0" min="0" step="0.01" full :readonly="!$can" />
            </div>
            @if ($can)<div style="margin-top:12px"><button class="btn pri">Save defaults</button></div>@endif
        </form>
    </div>
@else
    <div class="grid g2">
        <div class="card cp"><h4>FX rate</h4>
            <div class="alert warn" style="margin:8px 0"><div class="t">Rates must come from a dated, controlled source. None is assumed.</div></div>
            {!! Ui::dl([['Source', '[CONTROLLED-FX-SOURCE]'], ['Rate date', '[RATE-DATE]'], ['USD → BDT', '[RATE]'], ['CNY → USD', '[RATE]']]) !!}
        </div>
        <div class="card cp"><h4>Incoterms 2020</h4>
            <div style="margin-top:8px">@foreach (['EXW','FCA','CPT','CIP','DAP','DPU','DDP','FAS','FOB','CFR','CIF'] as $x)<span class="pill" style="margin:2px">{{ $x }}</span>@endforeach</div>
            <h4 style="margin-top:14px">Units of measure</h4>
            <div style="margin-top:8px">@foreach (['m','kg','yd','roll'] as $x)<span class="pill" style="margin:2px">{{ $x }}</span>@endforeach</div>
            <h4 style="margin-top:14px">Ports &amp; places</h4>
            <div style="margin-top:8px">@foreach (['Shanghai Port','Ningbo Port','Qingdao Port','Dongguan Factory','Chattogram Port (discharge)'] as $x)<span class="pill" style="margin:2px">{{ $x }}</span>@endforeach</div>
        </div>
    </div>
@endif
@endsection
