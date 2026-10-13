@extends('layouts.app')
@section('title', 'Demo Utilities')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Demo Utilities
@endsection
@section('heading', 'Demo Utilities')
@section('subheading', 'Jump a case to any stage, simulate failures, or reset all demo data.')

@section('content')
@php $can = \App\Domain\Perm::can('admin.cfg'); @endphp
<div class="grid g2">
    <div class="card cp"><h3 style="padding:0;border:0">Load a case to a stage</h3>
        <p class="mut sm">Rebuilds the selected case with realistic data up to the chosen point so you can test any step.</p>
        @if ($can && $canReset)
            <form method="post" action="{{ route('ad.demo.stage') }}" data-confirm="Rebuild this case up to the chosen stage? Its current data is replaced.">@csrf
                <div class="fg">
                    <x-field name="c" label="Case" type="select" :value="$cases->contains('id', 'IC-2026-10') ? 'IC-2026-10' : $cases->first()?->id" :options="$cases->mapWithKeys(fn ($c) => [$c->id => $c->id . ' · ' . $c->month])->all()" />
                    <x-field name="s" label="Stage" type="select" value="quotes" :options="array_column($stages, 1, 0)" />
                </div>
                <div style="margin-top:12px"><button class="btn pri">Load stage</button></div>
            </form>
        @else
            <p class="mut sm">{{ $can ? 'Stage loading is disabled outside the local demo environment.' : 'Only the System Administrator can use these utilities.' }}</p>
        @endif
    </div>
    <div class="card cp"><h3 style="padding:0;border:0">Simulations &amp; data</h3>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px">
            @if ($can)
                <form method="post" action="{{ route('ad.demo.mail') }}" data-autosubmit>@csrf
                    <label class="chk"><input type="hidden" name="on" value="0"><input type="checkbox" name="on" value="1" @checked($mailFail)><span>Email provider fails on next PO dispatch (AC-19)</span></label>
                </form>
                <x-post :action="route('ad.demo.export')" class="btn">Export all data as JSON</x-post>
                @if ($canReset)
                    <button class="btn bad" type="button" data-open="demo-reset">Reset all demo data</button>
                @else
                    <p class="mut sm">Reset is disabled outside the local demo environment.</p>
                @endif
            @else
                <p class="mut sm">Only the System Administrator can use these utilities.</p>
            @endif
        </div>
    </div>
</div>
@if ($can && $canReset)
    <x-dialog id="demo-reset" title="Reset all demo data" :action="route('ad.demo.reset')" ok="Reset" danger>
        <p class="sm">This restores the original three cases, masters, templates, exceptions and audit trail. Everything you changed is lost.</p>
        <div class="fg"><x-field name="confirm" label="Type RESET to confirm" required full /></div>
    </x-dialog>
@endif
@endsection
