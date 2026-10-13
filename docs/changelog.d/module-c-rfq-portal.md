## [2026-10-13] Module C - RFQ, quotations, supplier portal (completed after an interrupted run) - Claude

**Scope:** Supplier Shortlist, RFQs, RFQ detail (drafts), Link & Deadline Admin, Submitted Quotations, quotation detail, Supplier Portal Preview (staff, read-only) and the public tokenised supplier portal.

**Services:** `RfqService` (mapping CRUD, `generateDrafts`, `sendDraft`, `checkNoBid`, `saveRfq`, `sendDrafts`, `schedule`, `runDue`, `remind`, `revoke`, `extend`, `close`, link tokens: only the SHA-256 is stored, plaintext is re-derived from `app.key`), `QuotationService` (form dictionary, per-tab validation, draft/submit/revise, cut-off enforced server side, masked IP, portal PO acknowledgement).
**Controllers:** `SourcingController`, `PortalController` (staff preview), `SupplierPortalController` + `SupplierPortalActor` middleware (public).
**Routes:** `routes/actions/rfq.php` (`sp.save|toggle|delete`, `rfq.save|send|schedule|run|close|remind|revoke|extend`), `routes/portal.php` (public `/s/{token}` group, throttled, outside `auth`; `supplier.portal*`).

**Fixes made in this session (user report: create button missing, RFQ module broken vs UI)**
- Case flow stepper rendered as unstyled text on every page: `Ui::flowBar` emitted `<a>` but the CSS targets `.steps div`. Now `div[data-href]` like the prototype (clickable via app.js).
- Page heading was double-escaped (`Link &amp; Deadline Admin`) and layout used `View::getSection` as a default: `layouts/app.blade.php` now yields `heading` else `title`.
- Public portal view `suppliers/_rfq-body.blade.php` did not compile (inline `@php(...)` with nested parentheses swallowed the whole `@else` branch) -> converted to `@php ... @endphp` (also `_field.blade.php`).
- "Create" menu: `New RFQ`, `New supplier`, `Record supplier PI` now open their dialog (`?new=1` + `data-open-on-new` on `rfq-form`, `sup-form`, `pi-pick`). RFQ page button always reads "New RFQ" (as prototype); disabled with an explanation until the booking is confirmed.
- RFQ detail: "Schedule selected…" is a dialog (as prototype) instead of a bare datetime input in the toolbar.
- Signed-in user visiting `/login` was redirected to the non-existent `/home` (`RouteServiceProvider::HOME` is now `/`).
- `SmokeTest`: sample ids for `cs.csv`, `{token}` portal routes skipped (covered by `RfqPortalTest`).

**Tests:** `tests/Feature/RfqPortalTest.php` (8): pages for 9 roles, create-button permissions, dialogs open via `?new=1`, styled flow bar, token portal + unknown token 404, revoke audited, SoD on RFQ actions, extension validation, post-cut-off submission refused. Full suite: 73 tests / 1216 assertions green.

**Known gaps:** no PDF/real SMTP (mail archived only); optional supplier e-mail OTP and file virus-scan hook (BRD 09/20) not built; RFQ revision V2+ not built (RFQ stays V1); brand-new-case path (confirm booking -> New RFQ -> send -> supplier submits) is covered only piecewise, to be exercised end to end by the integrator's "load case to stage".
