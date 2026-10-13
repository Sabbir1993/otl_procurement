@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'options' => [], 'required' => false, 'hint' => null, 'full' => false, 'rows' => 3, 'step' => null, 'min' => null, 'max' => null, 'readonly' => false, 'placeholder' => null, 'id' => null])
@php
    $val = old($name, $value);
    // options: ['a','b'] | ['a' => 'Label'] | [['a','Label'], ...]
    $opts = [];
    foreach ($options as $k => $o) {
        if (is_array($o)) { $opts[] = [$o[0], $o[1] ?? $o[0]]; }
        elseif (is_int($k)) { $opts[] = [$o, $o]; }
        else { $opts[] = [$k, $o]; }
    }
@endphp
<label class="f {{ $full ? 'full' : '' }}">
    <span>{{ $label ?? \Illuminate\Support\Str::headline($name) }}{{ $required ? ' *' : '' }}</span>
    @if ($type === 'select')
        <select name="{{ $name }}" @if($id) id="{{ $id }}" @endif @required($required) {{ $attributes }}>
            @foreach ($opts as [$v, $l])
                <option value="{{ $v }}" @selected((string) $v === (string) $val)>{{ $l }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea name="{{ $name }}" rows="{{ $rows }}" @if($id) id="{{ $id }}" @endif @required($required) @readonly($readonly) placeholder="{{ $placeholder }}" {{ $attributes }}>{{ $val }}</textarea>
    @elseif ($type === 'checkbox')
        <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $val) {{ $attributes }}>
    @else
        <input name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : $val }}" @if($id) id="{{ $id }}" @endif @required($required) @readonly($readonly) @if($step) step="{{ $step }}" @endif @if(!is_null($min)) min="{{ $min }}" @endif @if(!is_null($max)) max="{{ $max }}" @endif placeholder="{{ $placeholder }}" {{ $attributes }}>
    @endif
    @if ($hint)<small class="mut">{{ $hint }}</small>@endif
</label>
