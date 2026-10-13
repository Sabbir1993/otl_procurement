@extends('layouts.bare')
@section('title', 'Supplier Portal')
@section('content')
<div class="main" style="width:100%">
    <div class="pbar">
        <b>Osman Interlinings Limited · Supplier Portal</b>
        <span class="pill">Secure supplier link</span>
        <span class="sp"></span>
        <div class="seg">
            <a class="btn sm" href="{{ route('supplier.portal', $token) }}">{{ $mode === 'rfq' ? '● ' : '' }}RFQ</a>
            @if ($pos)<a class="btn sm" href="{{ route('supplier.portal.po', $token) }}">{{ $mode === 'po' ? '● ' : '' }}Purchase orders</a>@endif
        </div>
    </div>
    <div class="pbody">
        @if ($mode === 'rfq')
            @include('suppliers._rfq-body', ['ctx' => $ctx, 'public' => true, 'token' => $token])
        @else
            @include('suppliers._po-body', ['c' => $c, 'sid' => $sid, 'pos' => $pos, 'public' => true, 'token' => $token])
        @endif
    </div>
</div>
@endsection
