<aside class="side">
    <div class="brand"><div class="logo">OI</div><div><b>OIL-PMS</b><small>Import Purchase</small></div></div>
    <div class="nav">
        @foreach ($shell['groups'] as $g)
            @if (count($g['items']) === 1)
                <a class="ng-h {{ $g['on'] ? 'on' : '' }}" href="{{ route($g['items'][0]['route']) }}" title="{{ $g['title'] }}">
                    <x-icon :name="$g['icon']" :size="18" /><span>{{ $g['title'] }}</span>
                </a>
            @else
                <div class="ng-h {{ $g['on'] ? 'open on' : '' }}" data-act="ngtoggle" title="{{ $g['title'] }}">
                    <x-icon :name="$g['icon']" :size="18" /><span>{{ $g['title'] }}</span><span class="chev"><x-icon name="chev" :size="14" /></span>
                </div>
                <div class="ng-items">
                    @foreach ($g['items'] as $i)
                        <a class="ni {{ $i['on'] ? 'on' : '' }}" href="{{ route($i['route']) }}">{{ $i['label'] }}@if ($i['badge']) <span class="nb">{{ $i['badge'] }}</span>@endif</a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
    <div class="sidefoot">{{ $shell['user'] }} · {{ $shell['roleName'] }}<br>Working case: {{ $shell['caseId'] ?? '—' }}</div>
</aside>
