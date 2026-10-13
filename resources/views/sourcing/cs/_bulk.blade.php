@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Support\Ui;

    $L = $c->s['booking']['lines'];
    $sups = array_filter(Masters::suppliers(), fn ($s) => collect($L)->contains(fn ($l) => isset($c->s['quotes'][$s['id']][$l['pid']])));
    $tA = $c->lineAwards();
    $sumBy = fn ($rows, $k) => array_sum(array_column($rows, $k));
@endphp
<div class="tw"><table>
    <thead><tr><th>Product × supplier</th>@foreach ($sups as $s)<th class="r">Supplier {{ $s['tag'] }}</th>@endforeach<th class="r">Material value</th><th class="r">Est. landed</th><th>Status</th></tr></thead>
    <tbody>
        @foreach ($L as $l)
            @php
                $pm = Masters::material($l['pid']);
                $x = $c->csOf($l['pid']);
                $tot = 0;
                $est = 0;
            @endphp
            <tr><td><b>{{ $l['pid'] }}</b><div class="mut sm">{{ $pm['name'] }} · {{ Fmt::n0($l['qty']) }} {{ $pm['uom'] }}</div></td>
                @foreach ($sups as $s)
                    @php $a = $x['alloc'][$s['id']] ?? 0; @endphp
                    <td class="r">
                        @if (!$a)
                            {!! isset($c->s['quotes'][$s['id']][$l['pid']]) ? '<span class="mut sm">quoted</span>' : '<span class="mut">—</span>' !!}
                        @else
                            @php
                                $q = $c->s['quotes'][$s['id']][$l['pid']];
                                $tot += $q['price'] * $a;
                                $est += \App\Domain\CaseBook::landed($q) * $a / $q['qty'];
                            @endphp
                            <b>{{ Fmt::n0($a) }}</b> <span class="mut sm">@ {{ Fmt::usd3($q['price']) }}</span>
                        @endif
                    </td>
                @endforeach
                <td class="r">{{ $tot ? Fmt::usd($tot) : '—' }}</td><td class="r">{{ $est ? Fmt::usd($est) : '—' }}</td><td>{!! Ui::pill($c->csStat($l['pid'])) !!}</td></tr>
        @endforeach
        <tr><td><b>Total</b></td>
            @foreach ($sups as $s)<td class="r"><b>{{ Fmt::usd($sumBy(array_filter($tA, fn ($x) => $x['sid'] === $s['id']), 'amt')) }}</b></td>@endforeach
            <td class="r"><b>{{ Fmt::usd($sumBy($tA, 'amt')) }}</b></td><td class="r"><b>{{ Fmt::usd($sumBy($tA, 'est')) }}</b></td><td></td></tr>
    </tbody>
</table></div>
