# UI parity audit - sourcing pages

Audited src.supp, src.rfq, rfq.show, src.track, src.quotes, quote.show, portal (staff preview + public portal), src.cs (product/tech/bulk), src.samples against the prototype.
Almost everything was already at parity (headers, KPIs, alerts, columns, filters, row actions, dialogs, tabs, empty states, Perm gating).

Fixed:
- src.supp: row "remove" button now uses the trash icon like the prototype (was a text cross).
- rfq.show: drafts table gained the prototype's Status filter (Draft/Scheduled/Sent/Blocked); rows carry data-st.

Tests: tests/Feature/UiParitySourcingTest.php.

Not changed (deliberate): staff portal preview is read-only (prototype allowed interaction; staff must not submit for suppliers);
public PO view uses a compact PO layout instead of the full poDoc.
