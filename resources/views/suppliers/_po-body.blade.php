{{-- Supplier PO acknowledgment (prototype portalPo): the supplier can only Accept or Dispute a PO, never modify it.
     $public = true -> action buttons (token portal); false -> read-only staff preview. Expects $c, $sid, $pos, optional $token. --}}
@php
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Http\Controllers\SupplierPortalController;
    use App\Support\Ui;
    $sp = Masters::supplier($sid);
@endphp
@if (!$pos)
    {!! Ui::empty('No purchase order for this supplier in ' . e($c->id), 'A PO appears here after Finance/ED approval and a successful automatic dispatch.') !!}
@else
    <div class="phead"><h2>Purchase order acknowledgment</h2><p>{{ $sp['name'] }} · you can only <b>Accept</b> or <b>Dispute</b> a PO — never modify it.</p></div>
    <div style="height:12px"></div>
    @foreach ($pos as $po)
        <div class="card" style="margin-bottom:14px">
            <h3>{{ $po['no'] }}{{ ($po['rev'] ?? 1) > 1 ? ' rev ' . $po['rev'] : '' }} {!! Ui::pill($po['ack']) !!}<span class="sp"></span>
                @if ($public && $po['ack'] === 'Awaiting')
                    <form method="post" action="{{ route('supplier.portal.po.accept', [$token, $po['no']]) }}" style="display:inline">@csrf<button class="btn pri" type="submit">Accept PO</button></form>
                @endif
            </h3>
            <div class="cp">
                {!! Ui::dl([
                    ['Supplier', e($sp['name'])], ['Case', e($c->id)], ['Total', e('USD ' . Fmt::n0($po['total']))],
                    ['Incoterms', e(trim(($po['inc'] ?? '') . ' ' . ($po['place'] ?? '')))], ['Payment', e($po['payment'] ?? '')],
                    ['Latest shipment', e(Fmt::dD($po['ship'] ?? null))], ['Ship to', e($po['shipTo'] ?? '')], ['Bill to', e($po['billTo'] ?? '')],
                    ['Quantity tolerance', e($po['tol'] ?? '')], ['Packing', e($po['packing'] ?? '')], ['Documents', e($po['docs'] ?? '')], ['Claims', e($po['claims'] ?? '')],
                ]) !!}
                <div class="tw" style="margin-top:10px"><table>
                    <thead><tr><th>Material</th><th>Supplier code</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($po['lines'] as $l)
                        <tr><td>{{ $l['pid'] }} · {{ Masters::material($l['pid'])['name'] }}</td><td>{{ $l['name'] ?? '' }}</td><td class="r">{{ Fmt::n0($l['qty']) }}</td><td class="r">{{ Fmt::usd3($l['price']) }}</td><td class="r">{{ Fmt::usd($l['amt']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if ($po['ack'] === 'Accepted')
                    <div style="margin-top:10px">{!! Ui::alert('info', 'PO accepted', 'Please submit your Proforma Invoice referencing this PO number; it must match the approved terms.') !!}</div>
                @endif
                @if (!empty($po['dispute']))
                    <div style="margin-top:10px">{!! Ui::alert('warn', 'Dispute recorded', e($po['dispute']) . ' — OIL Procurement will respond; updated terms require an approved revision.') !!}</div>
                @endif
                @if ($public && $po['ack'] === 'Awaiting')
                    <details style="margin-top:12px"><summary class="btn bad sm" style="display:inline-block">Dispute…</summary>
                        <form method="post" action="{{ route('supplier.portal.po.dispute', [$token, $po['no']]) }}" style="margin-top:10px">
                            @csrf
                            <div class="fg">
                                <x-field name="t" label="Which term is incorrect?" type="select" :options="SupplierPortalController::DISPUTE_TERMS" full />
                                <x-field name="r" label="Details" type="textarea" :rows="3" required full />
                            </div>
                            <button class="btn bad" type="submit" style="margin-top:8px">Submit dispute</button>
                        </form>
                    </details>
                @endif
            </div>
        </div>
    @endforeach
@endif
