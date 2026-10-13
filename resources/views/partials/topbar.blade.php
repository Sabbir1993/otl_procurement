@php
    $can = fn ($a) => \App\Domain\Perm::can($a);
@endphp
<div class="top">
    <button class="icon-btn hamb" data-act="mobnav" aria-label="Menu"><x-icon name="menu" /></button>
    <button class="icon-btn hide-m" data-act="sidetoggle" aria-label="Collapse sidebar"><x-icon name="menu" /></button>
    <div class="sp"></div>
    <a class="icon-btn" href="{{ route('notif') }}" title="Notifications"><x-icon name="bell" />@if ($shell['bell'])<span class="badge">{{ $shell['bell'] }}</span>@endif</a>
    <div class="rel">
        <div class="avatar" data-act="menu" data-m="user" title="{{ $shell['user'] }}">{{ $shell['initials'] }}</div>
        <div class="menu" id="menu-user" hidden style="min-width:250px">
            <div style="padding:8px 10px"><b>{{ $shell['user'] }}</b><div class="mut sm">{{ $shell['roleName'] }}</div></div>
            <hr>
            @if ($shell['demo'])
                <form method="post" action="{{ route('demo.switch') }}" data-autosubmit>
                    @csrf
                    <div style="padding:4px 10px" class="sm mut">Demo role switcher — test segregation of duties</div>
                    <div style="padding:0 10px 8px">
                        <select name="role" style="width:100%">
                            @foreach ($shell['roles'] as $k => $r)<option value="{{ $k }}" @selected($k === $shell['role'])>{{ $r['n'] }}</option>@endforeach
                        </select>
                    </div>
                </form>
                <hr>
            @endif
            <a data-act="theme"><x-icon name="sun" :size="15" /> Toggle light / dark</a>
            @if ($can('admin.cfg'))<a href="{{ route('ad.demo') }}"><x-icon name="cfg" :size="15" /> Demo utilities</a>@endif
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn gh sm" style="width:100%;text-align:left">Sign out</button></form>
        </div>
    </div>
</div>
