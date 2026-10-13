@php($hasFilter = $state['q'] !== '' || array_filter($state['f']))
<div class="card tbl" id="tw-{{ $id }}">
    <form class="tb-bar" method="get" action="{{ url()->current() }}" data-autosubmit>
        @foreach (request()->query() as $k => $v)
            @if ($k !== $id && is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <div class="srch"><input type="search" name="{{ $id }}[q]" placeholder="Search…" value="{{ $state['q'] }}"></div>
        @foreach ($filters as $f)
            <select name="{{ $id }}[f][{{ $f['k'] }}]" title="{{ $f['label'] }}">
                <option value="">{{ $f['label'] }}: All</option>
                @foreach ($filterOptions($f) as $o)
                    <option @selected((string) ($state['f'][$f['k']] ?? '') === (string) $o)>{{ $o }}</option>
                @endforeach
            </select>
        @endforeach
        @if ($state['sort'])<input type="hidden" name="{{ $id }}[sort]" value="{{ $state['sort'] }}"><input type="hidden" name="{{ $id }}[dir]" value="{{ $state['dir'] }}">@endif
        <button class="btn sm" type="submit">Search</button>
        @if ($hasFilter)<a class="btn sm" href="{{ request()->fullUrlWithQuery([$id => null]) }}">Clear</a>@endif
        @if ($export)<a class="btn sm" download="{{ $id }}_export.csv" href="data:text/csv;charset=utf-8,{{ rawurlencode($csv) }}">Export CSV</a>@endif
        {!! $tools !!}
    </form>
    <div id="tb-{{ $id }}">
        @if (!$total)
            <div class="empty"><b>{{ $hasFilter ? 'No results match your search or filters' : $empty }}</b>
                @if ($hasFilter)<a class="btn sm" href="{{ request()->fullUrlWithQuery([$id => null]) }}">Clear search &amp; filters</a>@else{{ $emptyHint }}@endif
            </div>
        @else
            <div class="tw"><table>
                <thead><tr>
                    @foreach ($cols as $c)
                        <th class="{{ $c['cls'] ?? '' }}">@if (empty($c['nosort']))<a href="{{ $sortUrl($c['k']) }}" style="color:inherit;text-decoration:none">{!! $c['t'] !!}{{ $state['sort'] === $c['k'] ? ($state['dir'] === 'asc' ? ' ▲' : ' ▼') : '' }}</a>@else{!! $c['t'] !!}@endif</th>
                    @endforeach
                    @if ($actions)<th></th>@endif
                </tr></thead>
                <tbody>
                    @foreach ($page as $row)
                        <tr @if ($rowLink) class="clk" data-href="{{ ($rowLink)($row) }}" @endif>
                            @foreach ($cols as $c)
                                <td class="{{ $c['cls'] ?? '' }}">{!! isset($c['r']) ? ($c['r'])($row) : e($value($row, $c)) !!}</td>
                            @endforeach
                            @if ($actions)<td class="nw r">{!! ($actions)($row) !!}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <div class="pg">
                <span>{{ ($state['page'] - 1) * $state['per'] + 1 }}–{{ min($total, $state['page'] * $state['per']) }} of {{ $total }}</span>
                <span>
                    <a class="btn sm {{ $state['page'] <= 1 ? 'disabled' : '' }}" @if ($state['page'] > 1) href="{{ $pageUrl($state['page'] - 1) }}" @endif>Prev</a>
                    Page {{ $state['page'] }}/{{ $pages }}
                    <a class="btn sm {{ $state['page'] >= $pages ? 'disabled' : '' }}" @if ($state['page'] < $pages) href="{{ $pageUrl($state['page'] + 1) }}" @endif>Next</a>
                </span>
            </div>
        @endif
    </div>
</div>
