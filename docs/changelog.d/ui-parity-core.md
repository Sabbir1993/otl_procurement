# UI parity audit - core pages + shell

Audited against `_src/OIL-PMS_Import_Purchase_Module.html`: home, cases, case.show, rq.board, rq.booking, pr, ship, grn, pay, sidebar/topbar/mobile-bar.

## Already at parity (no change needed)
- home: hero, 6-KPI row, flow bar, next-step card, attention list, pipeline kanban, deadlines, spend bars, recent activity, New case button (Perm-gated).
- cases: all 9 columns, Stage filter, row link, New case dialog (name/month/notes).
- case.show: stage cards, 6 tabs, Make working case, per-tab empty states.
- rq.board: alert variants, KPIs, bulk accept/reject, add/edit/delete/revert/accept, freeze, filters, consolidated demand.
- rq.booking: not-frozen empty state, download, CSV upload, edit/add/delete, confirmation card + signed-doc dialog.
- pr/ship/grn/pay: info alert, sends/returns cards, hand-off queue, link button (plus a real queue table - kept).
- Sidebar groups/items/badges/role filtering, Create menu (Perm-gated), case selector, bell badge, user menu with role switcher, mobile bar.

## Missing and fixed
- Shell: global search dropdown (grouped Cases / RFQs / POs / Suppliers / Materials / Exceptions, 5 per group, debounced) - new `SearchController`, route `gs.search` (routes/actions/cases.php), additive block in `public/js/app.js`; `.gsr` CSS already existed. Results are limited to groups the role may open (server side); Enter still falls back to the cases search.
- Shell: "Read-only role" banner for Internal Audit/MD on every page (`layouts/app.blade.php`, minimal additive).

## Tests
`tests/Feature/UiParityCoreTest.php` (search markup, grouping, role filtering, read-only banner). Full suite: 81 tests green.

## Not fixed / notes
- Create menu items navigate with `?new=1` and open the page dialog (instead of in-place dialogs); Record supplier PI opens the PI picker.
- Brand subtitle omits "Prototype" and sidefoot omits "Demo data" text deliberately (server-backed app).
- Not eyeballed in the browser pane.
