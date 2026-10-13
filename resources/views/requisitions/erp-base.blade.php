@extends('layouts.app')
@section('title', $e['t'])
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / {{ $e['t'] }}
@endsection
@section('heading'){{ $e['t'] }}
@endsection
@section('subheading')Stage {{ $e['s'] }} · existing ERP module — outside this prototype
@endsection

@section('content')
<div class="card cp"><div class="alert info"><div class="t"><b>Existing ERP module</b>{{ $e['d'] }}</div></div>
    <div class="grid g2" style="margin-top:12px">
        <div class="card cp"><small class="mut">This module sends</small><div class="b">{{ $e['give'] }}</div></div>
        <div class="card cp"><small class="mut">It sends back</small><div class="b">{{ $e['want'] }}</div></div>
    </div>
    <div class="ins" style="margin-top:12px"><small>Hand-off queue</small>{{ $summary }}</div>
    @if ($rows)
        <div class="card tbl" style="margin-top:12px"><div class="tw"><table>
            <thead><tr>@foreach ($heads as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>@foreach (array_slice($rows, 0, 50) as $r)<tr>@foreach ($r as $v)<td>{{ $v }}</td>@endforeach</tr>@endforeach</tbody>
        </table></div></div>
    @endif
    <div style="margin-top:12px"><a class="btn pri" href="{{ route($e['to']) }}">{{ $e['tl'] }}</a> <span class="mut sm">Integration points are TBD (Open Policy Item 12).</span></div>
</div>
@endsection
