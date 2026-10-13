@props(['action', 'params' => [], 'confirm' => null, 'class' => 'btn sm', 'method' => 'post', 'disabled' => false, 'title' => null, 'danger' => false])
{{-- One-click action button (POST form). confirm = text shown in a confirm prompt first. --}}
<form method="post" action="{{ $action }}" style="display:inline" @if($confirm) data-confirm="{{ $confirm }}" @endif>
    @csrf
    @if (strtolower($method) !== 'post') @method($method) @endif
    @foreach ($params as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <button type="submit" class="{{ $class }}{{ $danger ? ' bad' : '' }}" @disabled($disabled) @if($title) title="{{ $title }}" @endif>{{ $slot }}</button>
</form>
