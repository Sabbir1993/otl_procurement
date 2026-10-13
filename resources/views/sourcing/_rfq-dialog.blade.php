{{-- RFQ builder dialog (prototype ACT.rfqForm). Expects $c (CaseBook). Posts to rfq.save. --}}
@php
    use App\Domain\Masters;
    use App\Services\RfqService;
    $pool = $c->poolSup();
    $ex = array_column(array_filter($c->s['drafts'], fn ($d) => in_array($d['status'], ['Sent', 'Scheduled'], true)), 'sid');
    $reopen = old('_dialog') === 'rfq-form';
@endphp
<x-dialog id="rfq-form" :title="($c->s['rfq']['no'] ? 'Update' : 'New') . ' RFQ · ' . $c->id" :action="route('rfq.save', $c->id)" ok="Create drafts" wide data-open-on-new>
    <div class="fg">
        <x-field name="urgent" label="Response window" type="select" :options="[['0', 'Standard — 3 supplier-country working days'], ['1', 'Urgent — 2 working days (authorised reason)']]" :value="'0'" full />
        <x-field name="reason" label="Reason for urgency (required if urgent)" full />
        <x-field name="sender" label="Sender mailbox" type="select" :options="RfqService::SENDERS" :value="$c->s['sender']" />
        <x-field name="sample" label="Sampling required" type="select" :options="['Yes', 'No — approved waiver']" :value="$c->s['rfq']['sample'] ?? 'Yes'" />
        <x-field name="docs" label="Required quality documents" :value="$c->s['rfq']['docs'] ?? 'TDS, COA, test report'" full />
        <x-field name="notes" label="Special conditions / notes to suppliers" type="textarea" :rows="2" :value="$c->s['rfq']['notes'] ?? ''" full />
    </div>
    <h4 style="margin:12px 0 6px">Extra mandatory parameters <small class="mut">— suppliers must fill these on top of the standard required fields</small></h4>
    <div class="fg">
        @foreach (config('quote_fields.extra_req') as [$k, $l])
            <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="rf[]" value="{{ $k }}" @checked($reopen ? in_array($k, (array) old('rf', []), true) : in_array($k, $c->s['rfq']['reqFields'] ?? [], true))> {{ $l }}</label>
        @endforeach
    </div>
    <h4 style="margin:12px 0 6px">Invite suppliers <small class="mut">— from active Supplier Shortlist mappings</small></h4>
    <div style="max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:8px 12px">
        @forelse ($pool as $sid => $pids)
            @php($sp = Masters::supplier($sid))
            @php($cal = Masters::calendar($sp['cal'] ?? '') ?? null)
            <div class="chk"><span><label><input type="checkbox" name="sup[]" value="{{ $sid }}" @disabled(in_array($sid, $ex, true)) @checked(!in_array($sid, $ex, true) && (!$reopen || in_array($sid, (array) old('sup', []), true)))> <b>{{ $sp['name'] }}</b></label><br>
                <small class="mut">{{ implode(' · ', array_map(fn ($p) => $p . ' (' . \App\Domain\Fmt::n0($c->reqQty($p)) . ' ' . Masters::material($p)['uom'] . ')', $pids)) }}{{ in_array($sid, $ex, true) ? ' · already sent' : '' }}</small></span>
                <span class="pill {{ $cal && $cal['approved'] ? '' : 'p-bad' }}">{{ $sp['cal'] ?? '' }}{{ $cal && $cal['approved'] ? '' : ' unapproved' }}</span></div>
        @empty
            <div class="mut sm">No active supplier mappings for the booked materials — add them in the Supplier Shortlist.</div>
        @endforelse
    </div>
    <p class="mut sm" style="margin-top:8px">Drafts are created first; nothing is emailed until you send them from the RFQ page.</p>
</x-dialog>
