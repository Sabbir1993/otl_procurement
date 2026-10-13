@extends('layouts.app')
@section('title', 'Notifications')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('exc') }}">Control</a> / Notifications
@endsection
@section('heading')Notifications
@endsection
@section('subheading')In-app alerts for your role.
@endsection
@section('actions')
    <x-post :action="route('notif.readAll')" class="btn">Mark all read</x-post>
@endsection
@section('content')
@php
    use App\Domain\Fmt;
    use App\Domain\Perm;
    use App\Support\ChartUi;
@endphp
<div class="card">
    @forelse ($list as $n)
        <div class="chk" style="padding:10px 14px;{{ $n->read ? 'opacity:.6' : '' }}">
            <span><b>{{ $n->txt }}</b><br><small class="mut">{{ Fmt::dS($n->ts_ms) }}{{ $n->role ? ' · ' . Perm::roleName($n->role) : '' }}</small></span>
            {!! ChartUi::sevPill($n->sev) !!}
            @if ($n->link)
                <x-post :action="route('notif.open', $n->id)">Open</x-post>
            @endif
        </div>
    @empty
        <div class="empty"><b>No notifications</b>You are all caught up.</div>
    @endforelse
</div>
@endsection
