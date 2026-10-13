@extends('layouts.bare')
@section('title', 'Sign in')
@section('content')
<div class="main" style="width:100%;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px">
    <div class="card cp" style="width:min(440px,100%)">
        <div class="brand" style="padding:0 0 12px;display:flex;gap:10px;align-items:center"><div class="logo">OI</div><div><b>OIL-PMS</b><br><small class="mut">Import Purchase Module · Osman Interlinings Limited</small></div></div>
        <h3 style="padding:0;border:0;margin:0 0 10px">Sign in</h3>
        <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="fg" style="grid-template-columns:1fr">
                <x-field name="email" label="Email" type="email" required />
                <x-field name="password" label="Password" type="password" required />
            </div>
            @error('email')<p class="mut" style="color:var(--bad)">{{ $message }}</p>@enderror
            <div style="margin-top:12px"><button class="btn pri" type="submit">Sign in</button></div>
        </form>
        @if ($demo)
            <hr>
            <p class="sm mut">Local demo only — sign in as a role (test segregation of duties):</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach ($users as $u)
                    <form method="post" action="{{ route('demo.login') }}">@csrf<input type="hidden" name="role" value="{{ $u->role }}"><button class="btn sm">{{ config("roles.roles.{$u->role}.n") }}</button></form>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
