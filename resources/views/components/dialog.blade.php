@props(['id', 'title', 'action' => null, 'method' => 'post', 'ok' => 'Save', 'danger' => false, 'wide' => false, 'drawer' => false])
{{-- Modal. Open with <button type="button" data-open="{{ $id }}" data-fill='{"field":"value"}'>. Re-opens itself after a validation error. --}}
@php($open = old('_dialog') === $id && ($errors->any() || session('toast.kind') === 'bad'))
<div class="ov {{ $drawer ? 'dr' : '' }}" id="dlg-{{ $id }}" data-dialog {{ $attributes }} @unless($open) hidden @endunless>
    <div class="dlg {{ $wide ? 'wide' : '' }}" role="dialog" aria-label="{{ $title }}">
        <div class="dh"><h3>{{ $title }}</h3><button type="button" class="btn gh sm" data-close aria-label="Close">×</button></div>
        @if ($action)
            <form method="{{ strtolower($method) === 'get' ? 'get' : 'post' }}" action="{{ $action }}" data-dialog-form>
                @csrf
                @if (!in_array(strtolower($method), ['get', 'post'])) @method($method) @endif
                <input type="hidden" name="_dialog" value="{{ $id }}">
                <div class="db">
                    {{ $slot }}
                    @if ($open && $errors->any())<div class="ferr" style="color:var(--bad);margin-top:8px">{{ $errors->first() }}</div>@endif
                </div>
                <div class="df"><button type="button" class="btn" data-close>Cancel</button><button class="btn {{ $danger ? 'bad' : 'pri' }}" type="submit">{{ $ok }}</button></div>
            </form>
        @else
            <div class="db">{{ $slot }}</div>
            @isset($foot)<div class="df">{{ $foot }}</div>@endisset
        @endif
    </div>
</div>
