@extends('layouts.app')
@section('title', 'Policy & Approval Authority')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / <a href="{{ route('ad.roles') }}">Administration</a> / Policy
@endsection
@section('heading', 'Policy & Approval Authority')
@section('subheading', 'Decisions OIL must confirm before the specification is final. Nothing is pre-filled. Approval limits and alternates are configurable rules — no BDT amount is assumed.')

@section('content')
@php
    use App\Support\Ui;
    $can = \App\Domain\Perm::can('admin.cfg');
    $cols = [
        ['k' => 'n', 't' => '#', 'cls' => 'r'],
        ['k' => 'text', 't' => 'Policy decision', 'r' => fn ($p) => '<span class="sm">' . e($p->text) . '</span>'],
        ['k' => 'owner', 't' => 'Owner', 'r' => fn ($p) => e($p->owner) ?: '<span class="mut">—</span>'],
        ['k' => 'outcome', 't' => 'Decision', 'r' => fn ($p) => e($p->outcome) ?: '<span class="mut">—</span>'],
        ['k' => 'target_date', 't' => 'Target', 'r' => fn ($p) => e($p->target_date) ?: '—'],
        ['k' => 'status', 't' => 'Status', 'r' => fn ($p) => Ui::pill($p->status, $p->status === 'Confirmed' ? 'p-ok' : 'p-warn')],
    ];
    $actions = $can ? fn ($p) => '<button class="btn sm" type="button" data-open="pol-' . $p->n . '">Update</button>' : null;
@endphp
{!! Ui::kpiRow([['Open', $items->where('status', 'Open')->count(), ['cls' => 'warn']], ['Confirmed', $items->where('status', 'Confirmed')->count(), ['cls' => 'ok']]]) !!}
<div style="margin-top:12px">
    <x-dtable id="pol" :cols="$cols" :rows="$items" :actions="$actions" />
</div>

@if ($can)
    {{-- one dialog per item (action URL differs) --}}
    @foreach ($items as $p)
        <x-dialog id="pol-{{ $p->n }}" title="Policy item #{{ $p->n }}" :action="route('ad.policy.save', $p->n)" ok="Save">
            <p class="sm">{{ $p->text }}</p>
            <div class="fg">
                <x-field name="owner" label="Decision owner" :value="$p->owner" />
                <x-field name="date" label="Target date" type="date" :value="$p->target_date" />
                <x-field name="out" label="Decision / outcome" type="textarea" :value="$p->outcome" :rows="2" full />
                <x-field name="st" label="Status" type="select" :value="$p->status" :options="['Open', 'Confirmed']" />
            </div>
        </x-dialog>
    @endforeach
@endif

<div class="card" style="margin-top:12px"><h3>Approval authority matrix <span class="sp"></span><small class="mut">Policy item 1 — leave blank until OIL decides</small></h3>
    <div class="cp">
        @foreach ($authority as $i => $a)
            <form method="post" action="{{ $can ? route('ad.auth.save', $i) : '#' }}" class="chk" style="gap:8px">@csrf
                <b style="width:70px">{{ $a['lvl'] }}</b>
                <input name="lim" value="{{ $a['lim'] }}" placeholder="Limit — TBD, not assumed" style="flex:1" maxlength="120" @readonly(!$can)>
                <input name="who" value="{{ $a['who'] }}" placeholder="Approver / alternate" style="flex:1" maxlength="120" @readonly(!$can)>
                @if ($can)<button class="btn sm">Save</button>@endif
            </form>
        @endforeach
    </div>
</div>
@endsection
