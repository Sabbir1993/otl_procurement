@props(['id' => 'tab', 'tabs' => [], 'active' => null])
{{-- Server-side tabs: links with ?tab=<key>. $tabs = [key => label]. Use <x-tabs> then switch on $tab in the view. --}}
@php($cur = $active ?? request('tab', array_key_first($tabs)))
<div class="tabs">
    @foreach ($tabs as $k => $label)
        <a class="tab {{ $k === $cur ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k]) }}" style="text-decoration:none;color:inherit">{!! $label !!}</a>
    @endforeach
</div>
