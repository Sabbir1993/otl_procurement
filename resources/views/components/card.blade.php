@props(['title' => null, 'pad' => true, 'class' => ''])
<div class="card {{ $class }}">
    @if ($title)<h3>{{ $title }}@isset($head) <span class="sp"></span>{{ $head }}@endisset</h3>@endif
    <div class="{{ $pad ? 'cp' : '' }}">{{ $slot }}</div>
</div>
