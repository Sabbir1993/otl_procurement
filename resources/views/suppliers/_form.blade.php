{{-- Supplier create/edit dialog (perm sup.edit). Opened with data-open="sup-form" + data-fill. --}}
<x-dialog id="sup-form" title="Supplier" :action="route('sup.store')" ok="Save supplier" data-open-on-new>
    <input type="hidden" name="id" value="{{ old('id') }}">
    <div class="fg">
        <x-field name="name" label="Legal supplier name" required full />
        <x-field name="cc" label="Country" value="CN" required />
        <x-field name="cal" label="Working-day calendar" type="select" value="CN" :options="array_keys(\App\Domain\Masters::calendars())" />
        <x-field name="contact" label="Contact name" required />
        <x-field name="desig" label="Designation" />
        <x-field name="address" label="Registered address" full />
        <x-field name="email" label="Contact email" type="email" required />
        <x-field name="phone" label="Phone" value="[SUPPLIER-PHONE]" />
        <x-field name="status" label="Status" type="select" value="Active" :options="['Active', 'Inactive', 'Blocked']" />
    </div>
    <p class="mut sm">Changing the legal name, country or contact details marks the supplier Unverified until an independent callback is recorded. Bank details are never entered here.</p>
</x-dialog>
