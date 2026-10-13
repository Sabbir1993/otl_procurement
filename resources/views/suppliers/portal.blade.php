@extends('layouts.bare')
@section('title', 'Supplier Portal')
@section('content')
@php
    $pos = \App\Services\QuotationService::portalPos($c, $sid);
@endphp
<div class="main" style="width:100%">
    <form class="pbar" method="get" action="{{ route('portal') }}" data-autosubmit>
        <input type="hidden" name="pMode" value="{{ $mode }}">
        <b>Supplier External Portal</b><span class="pill p-warn">Preview — isolated from internal screens, read-only</span><span class="sp"></span>
        <label>Case <select name="pCase">@foreach ($cases as $x)<option value="{{ $x->id }}" @selected($x->id === $c->id)>{{ $x->id }}</option>@endforeach</select></label>
        <label>Previewing as <select name="pSup">@foreach ($sups as $s)<option value="{{ $s['id'] }}" @selected($s['id'] === $sid)>{{ $s['name'] }}</option>@endforeach</select></label>
        <div class="seg">
            <a class="btn sm" href="{{ route('portal', ['pCase' => $c->id, 'pSup' => $sid, 'pMode' => 'rfq']) }}">{{ $mode === 'rfq' ? '● ' : '' }}RFQ</a>
            <a class="btn sm" href="{{ route('portal', ['pCase' => $c->id, 'pSup' => $sid, 'pMode' => 'po']) }}">{{ $mode === 'po' ? '● ' : '' }}PO</a>
        </div>
        <a class="btn sm" href="{{ route('home') }}">← Back to PMS</a>
    </form>
    <div class="pbody">
        @if ($mode === 'rfq')
            @include('suppliers._rfq-body', ['ctx' => $ctx, 'public' => false, 'token' => null])
        @else
            @include('suppliers._po-body', ['c' => $c, 'sid' => $sid, 'pos' => $pos, 'public' => false, 'token' => null])
        @endif
    </div>
</div>
@endsection
