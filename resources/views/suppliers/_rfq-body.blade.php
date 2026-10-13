{{-- Supplier-facing RFQ / quotation screen (prototype portalRfq). Used by the PUBLIC token portal ($public = true, with $token) and the staff PREVIEW
     ($public = false: same screens, read-only, tabs are ?pTab= links). Expects $ctx from QuotationService::context(). --}}
@php
    use App\Domain\Clock;
    use App\Domain\Fmt;
    use App\Domain\Masters;
    use App\Domain\QuoteSpec;
    use App\Services\QuotationService;
    use App\Support\QuoteSheet;
    use App\Support\Ui;
    $c = $ctx['c']; $sid = $ctx['sid']; $sp = $ctx['sp']; $iv = $ctx['iv']; $F = $ctx['F']; $errs = $ctx['errs']; $tab = $ctx['tab'];
    $closed = $ctx['closed']; $done = $ctx['done']; $revising = $ctx['revising']; $extra = $ctx['extra'];
    $rfqReq = $c->s['rfq']['reqFields'] ?? [];
    $tabUrl = fn ($k) => $public ? route('supplier.portal', ['token' => $token, 'tab' => $k]) : request()->fullUrlWithQuery(['pTab' => $k]);
    $tabs = QuotationService::TABS;
    $keys = array_keys($tabs);
@endphp
@if (!$iv)
    {!! Ui::empty('No RFQ invitation for this supplier in ' . e($c->id), e($sp['name']) . ' has not been invited in this case.' . (($cal = Masters::calendar($sp['cal'] ?? '')) && $cal['approved'] ? '' : ' Its working-day calendar is not approved.')) !!}
@else
    @php $left = $iv['deadline'] - Clock::ms() @endphp
    <div class="phead">
        <div class="row" style="display:flex;gap:8px;flex-wrap:wrap"><span class="pill nd"><x-icon name="lock" :size="12" /> Secure link · token {{ \Illuminate\Support\Str::limit($iv['tok'] ?? '', 17, '…') }}</span><span class="pill">{{ $c->ivState($iv) }}</span></div>
        <h2 style="margin-top:8px">Request for Quotation {{ $iv['rfq'] }}</h2>
        <p>Osman Interlinings Limited (OIL) invites {{ $sp['name'] }} to quote for {{ count($iv['pids']) }} material(s). Only your own response is visible here.</p>
        <div class="dlx">
            <div><small>Deadline · China time</small><b>{{ Fmt::dCN($iv['deadline']) }}</b></div>
            <div><small>Deadline · Bangladesh</small><b>{{ Fmt::tz($iv['deadline'], 'H:i') }} BST (UTC+06:00)</b></div>
            <div><small>Time left</small><b>{{ $closed ? 'Closed' : max(0, intdiv($left, 86400000)) . ' d ' . intdiv($left % 86400000, 3600000) . ' h' }}</b></div>
        </div>
        @if (!empty($iv['ext']))<p style="margin-top:8px">Deadline extended — original {{ Fmt::dCN($iv['origDeadline'] ?? null) }}</p>@endif
    </div>
    <div style="height:12px"></div>

    @if (!empty($iv['revoked']))
        {!! Ui::alert('bad', 'This link has been revoked', 'Contact the OIL Procurement Department.') !!}
    @elseif ($closed && !$done)
        {!! Ui::alert('bad', 'Submission closed', 'The deadline has passed and final submission is disabled server-side. Drafts are not evaluated. Contact Procurement to request an authorised extension.') !!}
        <div style="height:10px"></div><div class="mut sm">Server time {{ Fmt::dBD(Clock::ms()) }} is the authority — your device clock is ignored.</div>
    @elseif ($done && !$revising)
        <div class="card cp">
            {!! Ui::alert('info', 'Quotation received', 'Reference <b>' . e($iv['quoRef']) . '</b> · revision ' . e($iv['rev']) . ' · ' . e(Fmt::dBD($iv['submittedAt'])) . '. Status: received, under evaluation. This acknowledgment is not supplier selection or PO acceptance.') !!}
            <div style="margin:12px 0">{!! QuoteSheet::render($c, $sid) !!}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn" type="button" onclick="window.print()">Print submission receipt</button>
                @if ($public && !$closed)
                    <form method="post" action="{{ route('supplier.portal.revise', $token) }}" style="display:inline">@csrf<button class="btn pri" type="submit">Revise quotation (new timestamped version)</button></form>
                @endif
            </div>
        </div>
    @else
        @php
            $tabErr = fn ($k) => count(array_filter($errs, fn ($e) => $e['tab'] === $k));
            $blocking = array_values(array_filter($errs, fn ($e) => $e['k'] !== 'decl'));
            $fields = array_values(array_filter(QuoteSpec::fields(), fn ($f) => $f['tab'] === $tab));
            $secBlocks = function (array $list, string $pid) use ($F, $rfqReq, $errs) {
                $secs = [];
                foreach ($list as $f) {
                    $secs[$f['sec']][] = $f;
                }
                $h = '';
                foreach ($secs as $name => $fs) {
                    $h .= '<div class="sec"><h4>' . e($name) . '</h4><div class="fg">';
                    foreach ($fs as $f) {
                        $h .= view('suppliers._field', ['f' => $f, 'pid' => $pid, 'F' => $F, 'rfq' => $rfqReq, 'errs' => $errs])->render();
                    }
                    $h .= '</div></div>';
                }
                return $h;
            };
        @endphp
        @if ($extra){!! Ui::alert('warn', 'OIL requires these extra parameters for this RFQ', e(implode(' · ', $extra))) !!}<div style="height:12px"></div>@endif
        @if ($revising)
            {!! Ui::alert('info', 'You are editing revision ' . ($iv['rev'] + 1), 'Your submitted revision ' . $iv['rev'] . ' (' . e($iv['quoRef']) . ') stays valid and is evaluated until you submit the new one.' . ($public ? '<div class="a"><button class="btn sm" type="submit" form="rev-cancel">Cancel revision</button></div>' : '')) !!}<div style="height:12px"></div>
        @endif
        @if ($public)
            <form id="rev-cancel" method="post" action="{{ route('supplier.portal.revise.cancel', $token) }}" hidden>@csrf</form>
            <form id="qform" method="post" action="{{ route('supplier.portal.save', $token) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
        @else
            {!! Ui::alert('warn', 'Preview — read-only', 'Staff cannot enter or submit a quotation on behalf of a supplier. Switch tabs to inspect the form exactly as the supplier sees it.') !!}<div style="height:12px"></div>
            <fieldset disabled style="border:0;padding:0;margin:0">
        @endif
        <div class="card">
            <div class="tabs" style="padding:0 10px">
                @foreach ($tabs as $k => $label)
                    @if ($public)
                        <button type="submit" name="goto" value="{{ $k }}" class="tab {{ $tab === $k ? 'on' : '' }}" style="background:none;border:0;border-bottom:2px solid transparent;font:inherit">{{ $label }}@if ($tabErr($k)) <span class="pill p-bad" style="padding:0 6px">{{ $tabErr($k) }}</span>@endif</button>
                    @else
                        <a class="tab {{ $tab === $k ? 'on' : '' }}" href="{{ $tabUrl($k) }}" style="text-decoration:none">{{ $label }}@if ($tabErr($k)) <span class="pill p-bad" style="padding:0 6px">{{ $tabErr($k) }}</span>@endif</a>
                    @endif
                @endforeach
            </div>
            <div class="cp">
                @if ($tab === 'review')
                    @if ($blocking)
                        {!! Ui::alert('bad', count($blocking) . ' item(s) block final submission', '<ul style="margin:6px 0 0 18px">' . implode('', array_map(fn ($e) => '<li><a href="' . e($tabUrl($e['tab'])) . '">' . e($e['m']) . '</a></li>', $blocking)) . '</ul>') !!}
                    @else
                        {!! Ui::alert('info', 'All required parameters are complete', 'Review the declaration and submit.') !!}
                    @endif
                    <div style="margin-top:12px">{!! $secBlocks(array_values(array_filter($fields, fn ($f) => $f['s'] === 'h')), '') !!}</div>
                    <input type="hidden" name="decl" value="0">
                    <label style="display:flex;gap:8px;margin:14px 0"><input type="checkbox" name="decl" value="1" @checked(!empty($F['decl']['ok']))> <span>I confirm the information in this quotation is accurate, any deviation from the RFQ specification is listed above, and I am authorised to submit it on behalf of {{ $sp['name'] }}.</span></label>
                    @if ($public)<button class="btn pri" type="submit" formaction="{{ route('supplier.portal.submit', $token) }}">Submit quotation</button>@endif
                @else
                    @php $H = array_values(array_filter($fields, fn ($f) => $f['s'] === 'h')) @endphp
                    @if ($H){!! $secBlocks($H, '') !!}@endif
                    @foreach ($iv['pids'] as $pid)
                        @php $lf = array_values(array_filter($fields, fn ($f) => $f['s'] === 'l' && QuoteSpec::fApplies($f, $pid))) @endphp
                        @if ($lf)
                            @php $m = Masters::material($pid) @endphp
                            <div class="card cp mcard"><div class="mh"><b>{{ $pid }} · {{ $m['name'] }}</b><span class="mut sm">{{ $m['comp'] }} · supplier product {{ Masters::spName($sid, $pid) }} · required {{ Fmt::n0($c->reqQty($pid)) }} {{ $m['uom'] }}</span></div>{!! $secBlocks($lf, $pid) !!}</div>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="cp" style="border-top:1px solid var(--line);display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <span class="mut sm">* required. Drafts are saved on this link but <b>not evaluated</b> — only a submitted version counts.</span><span style="flex:1"></span>
                @if ($public)
                    <button class="btn" type="submit" name="act" value="draft">Save draft</button>
                    @if ($tab !== 'review')<button class="btn pri" type="submit" name="goto" value="{{ $keys[min(5, array_search($tab, $keys, true) + 1)] }}">Next ›</button>@endif
                @endif
            </div>
        </div>
        @if ($public)
            </form>
            <script>
            // required-ness of some fields depends on others (payment method, compliance, sample date ...): save + re-render on change
            document.querySelectorAll('#qform [data-refresh]').forEach(function (el) { el.addEventListener('change', function () { document.getElementById('qform').submit(); }); });
            </script>
        @else
            </fieldset>
        @endif
    @endif
@endif
