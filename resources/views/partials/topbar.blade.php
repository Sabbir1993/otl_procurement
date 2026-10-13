@php
    $can = fn ($a) => \App\Domain\Perm::can($a);
@endphp
<div class="top">
    <button class="icon-btn hamb" data-act="mobnav" aria-label="Menu"><x-icon name="menu" /></button>
    <button class="icon-btn hide-m" data-act="sidetoggle" aria-label="Collapse sidebar"><x-icon name="menu" /></button>
    <form class="gs" method="get" action="{{ route('cases') }}"><x-icon name="search" /><input id="gsin" name="cases[q]" placeholder="Search cases, RFQs, POs, suppliers, materials…" autocomplete="off" data-gs="{{ route('gs.search') }}"><div class="gsr" id="gsr" hidden></div></form>
    <form method="post" action="{{ route('case.select') }}" data-autosubmit>
        @csrf
        <select class="fsel" name="id" title="Working case" style="max-width:230px">
            @foreach ($shell['cases'] as $c)
                <option value="{{ $c->id }}" @selected($c->id === $shell['caseId'])>{{ $c->id }} · {{ $c->month }}</option>
            @endforeach
        </select>
    </form>
    <div class="sp"></div>
    <div class="rel">
        <button class="btn pri" data-act="menu" data-m="create"><x-icon name="plus" :size="15" /> Create</button>
        <div class="menu" id="menu-create" hidden>
            @if ($can('case.new'))<a href="{{ route('cases') }}?new=1">New purchase case</a>@endif
            @if ($can('demand.edit'))<a href="{{ route('rq.board') }}?new=1">Add requisition line</a>@endif
            @if ($can('rfq.create'))<a href="{{ route('src.rfq') }}?new=1">New RFQ</a>@endif
            @if ($can('sup.edit'))<a href="{{ route('sup.dir') }}?new=1">New supplier</a>@endif
            @if ($can('pi.record'))<a href="{{ route('imp.pi') }}?new=1">Record supplier PI</a>@endif
            @unless ($can('case.new') || $can('demand.edit') || $can('rfq.create') || $can('sup.edit') || $can('pi.record'))<a>Not available for this role</a>@endunless
        </div>
    </div>
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
