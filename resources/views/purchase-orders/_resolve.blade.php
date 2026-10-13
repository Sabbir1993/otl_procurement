@if (\App\Domain\Perm::can('exc.resolve'))
    {{-- Shared "Resolve dispute" dialog. Open with data-open="po-res" data-fill='{"no":"PO-...","dispute":"..."}'. --}}
    <x-dialog id="po-res" title="Resolve dispute" :action="route('po.resolve')" ok="Record resolution">
        <input type="hidden" name="no" value="{{ old('no') }}">
        <div class="alert warn" style="margin-bottom:10px"><div class="t"><b>Supplier says</b><span data-fill-text="dispute">—</span></div></div>
        <div class="fg">
            <x-field name="o" label="Outcome" type="select" value="stand" :options="[['stand', 'PO stands (dispute rejected)'], ['chg', 'Accept change → freeze & re-approve']]" full />
            <x-field name="r" label="Resolution note" type="textarea" :rows="2" required full />
        </div>
        <p class="mut sm">Accepting a change freezes the PO and returns the award for approval. After re-approval the PO is re-issued under the same number as revision R2 — nothing is overwritten.</p>
    </x-dialog>
@endif
