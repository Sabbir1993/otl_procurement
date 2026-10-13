{{-- One quotation field control (prototype fieldHtml). Expects $f (dictionary entry), $pid ('' for header fields), $F (form), $rfq (case rfq.reqFields), $errs. --}}
@php
    use App\Domain\QuoteSpec;
    $req = QuoteSpec::fReq($rfq, $f, $pid ?: null, $F);
    $val = QuoteSpec::fGet($F, $f, $pid ?: null);
    $bad = collect($errs)->contains(fn ($e) => $e['k'] === $f['k'] && $e['pid'] === ($pid ?: ''));
    $nm = $f['s'] === 'h' ? "h[{$f['k']}]" : "l[{$pid}][{$f['k']}]";
    $refresh = in_array($f['k'], ['payMethod', 'compliance', 'sampleDate', 'sampleAvail', 'kind'], true);
    $ro = !empty($f['ro']);
    // after a failed request keep what the supplier typed (old input), fall back to the saved draft
    if ($f['t'] !== 'file' && !$ro) {
        $val = old(trim(str_replace(['[', ']'], ['.', ''], $nm), '.'), $val);
    }
@endphp
<label class="f {{ !empty($f['full']) ? 'full' : '' }}">
    <span>{{ $f['l'] }}@if ($req) <b style="color:var(--bad)">*</b>@elseif ($f['req'] === 'C') <small class="mut">(optional)</small>@endif</span>
    @if ($f['t'] === 'select')
        <select name="{{ $nm }}" class="{{ $bad ? 'bad' : '' }}" @if ($refresh) data-refresh @endif>
            @if ((string) $val === '' && !in_array('', $f['opts'], true))<option value="">— select —</option>@endif
            @foreach ($f['opts'] as $o)
                <option value="{{ $o }}" @selected((string) $val === (string) $o)>{{ $o === '' ? '— select —' : $o }}</option>
            @endforeach
        </select>
    @elseif ($f['t'] === 'textarea')
        <textarea rows="2" name="{{ $nm }}" class="{{ $bad ? 'bad' : '' }}">{{ $val }}</textarea>
    @elseif ($f['t'] === 'file')
        <input type="file" name="{{ $nm }}" accept=".pdf,.xlsx,.jpg,.jpeg,.png">
        @if ($val)<small>Attached: <b>{{ $val }}</b></small>@endif
    @elseif ($f['t'] === 'tiers')
        <div class="tiers">
            @for ($i = 0; $i < 3; $i++)
                @php $t = (array) (($val ?? [])[$i] ?? []) @endphp
                <div><span class="mut sm">Tier {{ $i + 1 }} from</span>
                    <input type="number" min="0" placeholder="Min qty" name="{{ $nm }}[{{ $i }}][min]" value="{{ $t['min'] ?? '' }}">
                    <input type="number" min="0" step="0.0001" placeholder="Unit price" name="{{ $nm }}[{{ $i }}][price]" value="{{ $t['price'] ?? '' }}"></div>
            @endfor
        </div>
    @else
        <input type="{{ $f['t'] }}" name="{{ $nm }}" value="{{ is_array($val) ? '' : $val }}" @if (!empty($f['step'])) step="{{ $f['step'] }}" @endif @if ($ro) readonly @endif class="{{ $bad ? 'bad' : '' }}">
    @endif
    @if (!empty($f['h']))<small class="mut">{{ $f['h'] }}</small>@endif
</label>
