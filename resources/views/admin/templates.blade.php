@extends('layouts.app')
@section('title', 'Email Templates')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Email Templates
@endsection
@section('heading', 'Email Templates')
@section('subheading', 'Copy-ready formats. {PLACEHOLDERS} are filled from validated master and transaction data. Automated messages come from an official OIL sender and are archived.')

@section('content')
@php
    $can = \App\Domain\Perm::can('admin.cfg');
    preg_match_all('/\{[A-Z0-9_]+\}/', $t->subject . ' ' . $t->body, $m);
    $ph = array_values(array_unique($m[0]));
@endphp
<div class="card">
    <form class="tb-bar" method="get" action="{{ route('ad.tpl') }}" data-autosubmit>
        <select name="code">@foreach ($all as $x)<option value="{{ $x->code }}" @selected($x->code === $t->code)>{{ $x->name }}</option>@endforeach</select>
        <span style="flex:1"></span>
        @if ($can)<button class="btn sm pri" type="button" data-open="tpl-form"><x-icon name="edit" :size="13" /> Edit template</button>@endif
    </form>
    <div class="cp">
        <div class="mail"><div class="hd">Subject: {{ $t->subject }}</div>{{ $t->body }}</div>
        <p class="mut sm" style="margin-top:8px">Placeholders: @forelse ($ph as $p)<code>{{ $p }}</code> @empty none @endforelse</p>
    </div>
</div>
@if ($can)
    <x-dialog id="tpl-form" title="Edit template · {{ $t->name }}" :action="route('ad.tpl.save', $t->code)" ok="Save template" wide>
        <div class="fg">
            <x-field name="s" label="Subject" :value="$t->subject" required full />
            <x-field name="b" label="Body" type="textarea" :value="$t->body" :rows="14" required full />
        </div>
        <p class="mut sm">Keep the {PLACEHOLDERS} intact: {{ implode(' ', $ph) }}</p>
    </x-dialog>
@endif
@endsection
