# Changelog & Agent Hand-off

Append-only. Newest entry first. Every agent (Claude or other) **must** read `docs/MEMORY.md`, `docs/RULES.md` and this file before editing, and add an entry after finishing work.

Entry template: `## [YYYY-MM-DD] <title> — <agent>` · Why · Files added/changed/removed · How verified · Next / caveats.

---

## Current state (read this first)
- **Stack:** Laravel 10.x, PHP 8.1, SQLite. Server-rendered Blade. **Backend phase in progress** - read `docs/BACKEND_GUIDE.md` (conventions, ownership, contracts) before editing.
- **Run:** `php artisan serve --port=8099`; sign in at `/login` (local demo: one click per role). Tests: `php vendor/bin/phpunit` (in-memory DB).
- **Spec:** the prototype - `_src/OIL-PMS_Import_Purchase_Module.html` (rendered look) and `_legacy/oilpms-js-engine/` (page templates, `ACT.*` handlers, business logic).
- **Data:** seeded from the prototype's demo DB (`database/seeders/data/prototype-db.json`, regenerate with `node _legacy/dump-seed.cjs`).
- Pages not yet converted are still the static Blade snapshots (inert buttons); converted pages are dynamic with working actions (see per-module entries below).

### Layers
Routes `routes/web.php` (GET pages, auth group) + `routes/actions/*.php` (POST actions per module) -> Controllers (thin, one per menu group) -> Services (`app/Services`) -> Domain (`app/Domain`: Clock, Fmt, Perm, Masters, Deadline, QuoteSpec, Workflow, CaseBook) -> Models/SQLite. Views per menu group in `resources/views/<menu>/`; shell in `layouts/` + `partials/`; UI components in `resources/views/components` (`x-dtable`, `x-dialog`, `x-field`, `x-post`, `x-card`, `x-tabs`, `x-icon`) + `App\Support\Ui`.

---

## [2026-10-13] Backend foundation (schema, domain port, auth, UI kit) + Purchase Cases reference module - Claude
**Why:** user asked to make every action button work with a real backend, whole flow in order.
**Decisions:** relational masters/logs + JSON **case aggregate** in `purchase_cases.state` (ported prototype logic operates on it 1:1; promote to tables later if reporting needs it). Timestamps = epoch ms. Seed = prototype demo DB (exported by running the legacy engine headless in Node).
**Added:** migration `2026_10_13_000001_create_oilpms_tables` (15 tables + users.role/status), 15 models, `DatabaseSeeder`, `config/{roles,quote_fields,oilpms,icons,menu}.php`, `app/Domain/*` (verified: PHP `awardChk()` equals the checksum the JS stored for the seeded approval; CN deadline calc matches), `Perm`/`SegregationOfDuties`/`BusinessRule` + handler, `AuthController` + login (local demo role login/switch via `oilpms.demo_login`), `ShellComposer` (role-filtered sidebar, badges, bell, case selector), UI kit (`x-dtable` server-side search/filter/sort/paginate/CSV, `x-dialog`, `x-field`, `x-post`, `x-card`, `x-tabs`, `App\Support\Ui`), `public/js/app.js` (dialogs, toasts, confirm, autosubmit), `CaseService` + `PurchaseCaseController` + `routes/actions/cases.php` + dynamic `cases/index|show` (new case, make working case), `PoService::generate/acknowledge`, controllers re-split per owner, `tests/Feature/SmokeTest.php`, `docs/BACKEND_GUIDE.md`.
**Verified:** phpunit: guests redirected, every page route renders for admin, exec creates a case with audit row, marketing is blocked by segregation of duties.
**Gotchas:** see BACKEND_GUIDE (heredoc/backslash loss in bash, `@endsection` after a word char, `static` caches across requests, dotted route keys).

## [2026-10-13] Rebuild as proper server-rendered Laravel app (Blade per page, controller per menu) - Claude
**Why:** user wants a real Laravel application from the design: menu-wise pages, each with its own route registered in `web.php`.
**How:** crawled the prototype in the browser (Supervisor role; Administrator for admin pages), captured each page's header/crumbs/actions/body HTML (+ every tab as a snapshot), converted links to `route()` and escaped Blade syntax with a one-off script; raw captures kept in `_legacy/extract-json/`.
**Added:** 11 controllers, 43 Blade page views, `layouts/{app,bare}`, `partials/{sidebar,topbar,mobile-bar}`, `components/icon`, `app/View/Components/Icon.php`, `config/{menu,icons}.php`, `public/js/app.js`, `errors/404.blade.php`, explicit `routes/web.php` (43 named GET routes).
**Moved to `_legacy/`:** old `web.php`, JS engine (`oilpms-js-engine/`), `oilpms.config.php`, old layout.
**Removed:** `AssetController`, `config/oilpms.php`, `routes/menu/`, `resources/views/pages/`, `resources/js/oilpms/`, temp `/__extract` route.
**Verified:** all 43 routes return 200 with no Blade errors; browser: sidebar active state, accordion, tab snapshots + `?tab=`, no console errors.
**Gotchas:** Blade directives need a non-word char before `@` (`text@endsection` is NOT compiled), so sections are multi-line; `config("x.$key")` breaks on dotted keys; route defaults bind positionally to controller args; `@php(...)` inline form in the sidebar caused a parse error, use `@php ... @endphp`.
**Known gaps:** static data only; role-based menu (prototype `ROLE_NAV`) not applied (all groups shown); global search, working-case switch, Create menu actions, table filter/sort/pagination and dialogs are inert; supplier portal is a single static snapshot.

## [2026-10-13] (SUPERSEDED by the entry above) Re-split by sidebar menu group; per-group routes/views; fix 404 asset URL — Claude
**Why:** user reported a 404 resource and asked for the code split by UI section with sidebar-menu-wise web routing.
**Changed**
- JS: `resources/js/oilpms/pages/*.js` (14 coarse files) → `resources/js/oilpms/menu/<group>/<page>.js` (27 files, content unchanged, only cut at section boundaries; blank-line-only diff verified by non-blank line count).
- `config/oilpms.php` restructured to `menu.<group>`; `pages`, `detail`, `js` (bundle manifest) derived.
- `routes/web.php` now only loads `routes/menu/<g>.php` (11 files, new) + 404 fallback; `Route::view` replaces `PageController`.
- Views moved to `resources/views/pages/<g>/…`.
- `AssetController` orders files by the config manifest instead of glob.
- `layouts/app.blade.php`: script URL via `url('_assets/oilpms.js')` (was a root-relative `route(...,false)` that **404s when the app lives in a sub-folder such as `/otl_procurement/public`**); added `<link rel="icon" href="data:,">` to stop the `/favicon.ico` 404.
**Removed:** `app/Http/Controllers/PageController.php`, `resources/js/oilpms/pages/`, old flat `resources/views/pages/*.blade.php`.
**Verified:** 48 GET routes; 35 sidebar links (admin role) + 4 detail routes render, no reload, no console errors; assets all 200/304.
**Caveat:** the sub-folder 404 fix was verified by code reading only (no Apache in this session); if a 404 persists, report the exact URL from DevTools → Network.

## [2026-10-13] (superseded layout — see entry above) Split UI into modular files, real per-page routes/views, link + click-state routing — Claude
**Why:** make routes follow the sidebar menu and click activity; make the code workable by multiple agents.
**Added**
- `app/Http/Controllers/PageController.php` (`show`, `detail`), `app/Http/Controllers/AssetController.php` (JS bundle).
- `resources/views/pages/*.blade.php` (36 list pages), `pages/detail/*.blade.php` (7), `pages/not-found.blade.php`.
- `resources/js/oilpms/**` (20 files; split of the former single `public/js/oilpms.js`, content unchanged except router/links/query state).
**Changed**
- `config/oilpms.php`: pages now `key => [path,title]`; `detail` now `key => title`.
- `routes/web.php`: controller routes, `/_assets/oilpms.js`, 404 fallback view.
- `layouts/app.blade.php`: title/page sections, `OILPMS_PAGE`, script via named route.
- JS: sidebar single-item groups are `<a>`; click handler `preventDefault`s `data-go` anchors but lets modified clicks through; `ACT.tab` pushes `?tab.<id>=`; working-case selector writes `?case=`; `navigate()`/init call `readQ()`.
- `public/css/oilpms.css`: no-underline hover for sidebar/crumb links (appended).
**Removed:** `public/js/oilpms.js` (replaced by the modular sources + `/_assets/oilpms.js`).
**Verified (browser, admin role):** all 36 sidebar links navigate to the right path/title with no page reload and no console errors; deep-link restores tab; tab click → URL; back/forward restore tab state; detail routes 200.
**Gotcha found:** Laravel binds route *defaults* positionally to controller args → `detail()` reads `$request->route('key')`. Also never use `config("x.$key")` with dotted keys (page keys contain dots) — use `array_key_exists`.
**Next:** Phase 2 backend plan. Not done: portal sub-tab (`UI.p.tab`) and table filter/sort state are still not in the URL.

## [2026-10-13] Frontend port & docs — Claude
**Added:** `docs/{PRD,ARCHITECTURE,RULES,PHASES,DESIGN,MEMORY}.md`; Laravel 10 skeleton (SQLite configured in `.env`, `.env.example`); `config/oilpms.php`; `public/css/oilpms.css`; single-bundle JS with path routing replacing hash routing; Blade shell; `_src/` original HTML.
**Verified:** 36 pages + detail routes render; no console errors.
**Caveat:** `.env` contains the generated `APP_KEY` — keep out of VCS (already in `.gitignore`).


---
## [2026-10-13] Wave merge: modules A-F + UI parity + stage loader (integrator) - Claude
Fragments from `docs/changelog.d/` merged verbatim below. Full suite green; "Load a case to stage" (Admin > Demo utilities) is live via `StageLoader`.

### module-a-control.md
## Module A - control & reporting (home, an.perf, an.pipe, exc, notif, audit)

**What**
- Command Center (`home`): role queue (`tasksFor` port), hero stats, working-case KPI row with drill-down links, flow bar + next step, case pipeline board, upcoming RFQ deadlines, spend by supplier, recent activity. Drill-down links/buttons are shown only when `Perm::navAllows` lets the role open the target page (AC-20).
- Reports: `an.perf` (savings, response rate, sample first-pass, supplier table) and `an.pipe` (stage-to-stage cycle times) computed from `CaseBook::all()`; same definitions as the prototype.
- Exceptions (`exc`): KPIs, filtered table, resolve/update dialog (owner, status Open|Resolved, resolution note; perm `exc.resolve`).
- Notifications (`notif`): role-scoped list, mark all read, open (marks read, redirects to the link if the role may open it).
- Audit (`audit`): tabs log / message archive / integrations, archived e-mail viewer (`?tab=mail&view=MSG-..`), CSV export (honours case + search filters, itself audited as "Audit exported").
- Control pages and reports redirect to `home` with a warning toast when `navAllows` is false (server-side, not only hidden in the menu).

**Files**: `app/Services/{CommandCenterService,ReportService,ControlService}.php`, `app/Support/ChartUi.php`, controllers `CommandCenterController|ReportController|ControlController`, `routes/actions/control.php`, views `command-center/index`, `reports/{performance,pipeline}`, `control/{exceptions,notifications,audit}`, `tests/Feature/ControlReportingTest.php`.

**Routes added** (`routes/actions/control.php`): `POST home.goCase` (/home/go-case), `POST exc.save` (/control/exceptions, id in body), `POST notif.readAll`, `POST notif.open` (/control/notifications/{id}/open), `GET audit.export` (/control/audit/export?tab=log|mail&case=&q=).

**Tests**: `php vendor/bin/phpunit --filter ControlReporting` -> 12 tests / 158 assertions (all roles render, nav block, drill-down hiding, goCase, resolve + audit, reassign, rule failures, SoD block, notification scoping, mail view, CSV export + block, report numbers, role queues).

**Gaps / notes**
- Command Center PO tasks use `po.status` as stored ('Correction queue' / 'Scheduled' / 'Frozen'); the full `validatePO` check belongs to the PO module (E) - until it stores a status unsent POs show as "dispatch to supplier".
- Exceptions page KPI cards drill to the same page with `?exc[f][st]=...` filters; the other pages' x-dtable ids are owned by other modules, so only `exc` carries filters.
- Pipeline CSV export (dtable) shows -1 for stages with no timestamps (sort key); display is correct.
- "Mark all read" only affects notifications visible to the current role (admin: all).
- Full-suite note: `SmokeTest::test_every_page_renders` currently fails on another module's route `cs.csv` (needs `{id}`); it is not part of this module.

### module-b-requisitions.md
# Module B - requisitions & ERP hand-off stubs

## Added / changed
- `app/Services/RequisitionService.php`: all rules ported 1:1 from `menu/rq/*.js`, `freezeCase`, `bookingSerial`. Contract methods `freeze(CaseBook, ?by)` and `confirmBooking(CaseBook, docRef, ?by, ?ts, array $extra = [])` (mutate `$c->s` + log, caller saves). Action wrappers (`accept, revert, delete, bulkAccept, bulkReject, saveLine, freezeAction, editBooking, addBooking, deleteBooking, importCsv, report, confirm`) do `Perm::need` + stage rules + `CaseBook::tx`.
- Read API `lines()/stats()/demandQty()`: controllers and views never touch `$c->s['demand']` directly (coordinator request: demand storage may move to the ERP tables later).
- `RequisitionController`, `ErpHandoffController` (EXTMOD + hand-off queue computed from real cases: PR lines of working case, LC-opened POs, accepted POs, all POs).
- Views: `requisitions/board`, `requisitions/booking`, `requisitions/erp-base` (+ `requisitions/pr`, `import/shipment|grn|payment` as one-line wrappers).
- Routes (`routes/actions/requisitions.php`): `rq.line.save|accept|revert|delete`, `rq.bulk.accept|reject`, `rq.freeze`, `rq.booking.report` (GET CSV download), `rq.bk.add|save|delete|csv|confirm`.
- `rq.board?new=1` opens the add-line dialog (`data-open-on-new`).
- Confirm: signed document file (pdf/jpg/png, 5 MB) AND reference are mandatory; file is stored on the private `local` disk under `booking-docs/<case>/` (random name), original name kept in `booking.doc`, path in `booking.docPath`. Serial `IMPBOK/<yyyy of case month>/<MON>/<nn>` via the `IMPBOK-<MON>` sequence. Audit + notification (role exec).

## Tests
`tests/Feature/RequisitionTest.php` (10 tests, 177 assertions, passing): pages for every role, accept/revert/delete, bulk accept/reject (reason from settings), add/edit, freeze rules, post-freeze lock, booking pct/qty maths, CSV, confirm rules/serial/audit/notification, service contracts, segregation of duties.

## Known gaps / notes
- Bulk selection only covers rows on the visible dtable page (checkboxes are per page).
- Single-line reject is only via the bulk dialog (as in the prototype).
- Uploaded signed documents are not virus-scanned and have no retention/PII policy yet (privacy flag: may contain signatory personal data; keep off public disk; Bangladesh Bank/PCI evidence retention to be decided).
- Existing failure outside this module: `SmokeTest::test_every_page_renders` errors on module D's route `cs.csv` (needs `{id}`; add to the smoke sample map or skip list).

### module-c-rfq-portal.md
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

### module-d-cs-awards.md
## [2026-10-13] Module D - Comparative statement, samples & QA, negotiation, recommendation, approvals - Claude

**Why:** make `src.cs`, `src.samples`, `aw.neg`, `aw.rec`, `aw.appr`, `award.show` dynamic with every action working against the case aggregate (port of prototype `ACT.cs*`, `smpSave`, `awSubmit`, `apprSave`, `apprChgSave`, `submitAward`, `decide`, `negotiate`, `setFull`).

**Added**
- `app/Services/CsService.php` - `setFull`, `negotiate` (cross-module contract signatures, no permission check, caller saves) + guarded actions `allocate` (full/partial, MOQ waiver needs `cs.waiver` + reason, over-allocation block, raises `MOQ waiver`), `clear`, `justify`, `financeRemark` (`fin.remark`, only while package is at Finance/ED), `negotiateRound` (`cs.negotiate`), `exclude`/`include`, `bulkPlan`/`bulkApply`, `csvRows`, `sampleResult` (`sample.edit`; Fail drops the allocation + raises `Sample rejected`, AC-10).
- `app/Services/ApprovalService.php` - `submitAward`, `decide` (contract signatures) + guarded `submit` (`cs.submit`; RFQ closed, nothing pending, justification where `needsJust`, summary), `decideAs` (step permission `appr.mkt|fin|ed`, remarks mandatory on return/reject, stale checksum blocks approval, no self-approval, Marketing cannot reject), `changeRequest` (`appr.change`/`cs.negotiate`; freezes unsent POs, status returned, exception `Change after approval`). Final ED approval calls `PoService::generate`.
- Controllers (replaced stubs): `ComparativeStatementController`, `SampleController`, `AwardController` (actions live in the same controllers).
- Views: dynamic `sourcing/comparative-statement` (+ partials `sourcing/cs/_product|_tech|_bulk`), `sourcing/samples`, `awards/negotiation|recommendation|approvals|show`.
- `tests/Feature/CsAwardsTest.php` (12 tests).

**Routes added** (`routes/actions/cs-awards.php`): POST `cs.allocate|clear|justify|fin|negotiate|exclude|include|bulk` (`/cs/{id}/...`), GET `cs.csv`, POST `smp.save` (`/samples/result`), POST `aw.submit`, `appr.decide`, `appr.change` (`/award/{id}/...`). GET page routes unchanged.

**UI state:** CS page state is in the query string (`?tab=product|tech|bulk&pid=P-1001&strat=cost&preview=1`); bulk select is a GET preview then a POST apply. Allocation/negotiation/sample/decision forms are `x-dialog`s filled via `data-fill`. Buttons are hidden with `Perm::can`, the server enforces with `Perm::need`.

**Differences from the prototype (deliberate hardening)**
- A failed sample also removes the supplier's MOQ waiver entry.
- Server-side extras: pack must have no pending products to submit; Finance remark only at Finance/ED stage; Marketing cannot "reject"; a user who submitted or already decided the package cannot decide a later step (no self-approval); change request only on an approved package.
- Business-rule messages replace the prototype's toasts-with-return (shown as red toast, dialog re-opens with old input).

**Verified:** `php vendor/bin/phpunit --filter CsAwards` - 12 tests, 305 assertions pass (every page for 8 roles before/after submission, full chain allocate -> submit -> mkt -> fin -> ed -> POs, AC-10/11/12/13, stale checksum, self-approval, SoD for each action, MOQ waiver, negotiation re-pricing, bulk + CSV, cross-module service signatures). Browser smoke on a throwaway DB copy (port 8104): CS page renders, Award dialog fills and saves.

**Known gaps:** the sample-result dialog and allocation dialog do not toggle the partial-qty field by mode (server ignores qty for "full"); `Waived` sample result has no separate authorisation flow (as in the prototype); mail delivery is archive-only (Policy item 12). `tests/Feature/SmokeTest` currently fails on module C's `supplier.portal` route (needs a `{token}` sample) - not part of module D.

### module-e-po-import.md
## Module E - purchase orders, PI & banking documents, LC

**Pages made dynamic** (same look, Blade from the case aggregate; buttons hidden by `Perm::can`, server enforces `Perm::need`): `po.reg`, `po.show` (tabs `?tab=doc|chk|mail|tl`), `po.ack`, `po.disp`, `imp.pi`, `pi.show`, `imp.lc`. PO/PI/LC lists span all cases (like the prototype's `allPOs()`); the flow bar / correction-queue alert use the working case.

**Services**
- `PoService` (extended): `validate()` (5 checks), `failing()`, `status()`, `vars()`, `preview()`, `dispatch(CaseBook, array &$po, ?int $ts, ?string $by): bool`, `full()` (`PO-...-R2`), `locate()/idx()/with()/rows()` helpers; actions `genDrafts`, `dispatchMany`, `dispatchOne`, `schedule`, `runDue`, `retry`, `remind`, `resolveDispute`, `edit`, `devOk`. `generate()` and `acknowledge()` keep their signatures.
- `PiService` (new): `seed(CaseBook, array $po, array $over = [])` (port of seedPI; pass `$over['sys']` to avoid consuming a PI number), `match()` (10 points), `ok()`, `lcOf()`, `IMPDOCS`; actions `pick`, `record`, `approve`, `clarify`, `bank`, `doc`, `docsDone`, `lcApprove`, `lcOpen`.
- Rules: dispatch is gated by the 5 checks when interactive (`$by === null`); a failing PO is never mailed, an audit row + exception is written (AC-14: `Incoterms without place`; also `Supplier contact changed`, `MOQ waiver`). Only POs that are selected AND have a draft AND are Ready are sent, each to its own supplier, template `PO` archived in `mails` (AC-15). `$by = 'System'` (demo "load case to stage" loader) skips the gate and the e-mail failure simulation, like the prototype. `settings.flags.emailFail` -> PO held as `Delivery Failed`, exception `PO email failed`; `retry` clears the flag and re-sends the SAME PO number, resolves the exception (AC-19). Dispute `chg` outcome freezes the PO, returns the award (`appr.status = returned`), raises `Change after approval` (AC-16); `PoService::generate()` now re-issues a frozen (or changed, already-sent) PO under the same number with `rev + 1` and keeps a sent, unchanged PO as is (no duplicate PO to the supplier). Status `Frozen` wins over `Sent`. PI: recorded only against an Accepted PO (not after LC opened); beneficiary-bank change raises `Bank details changed` and needs `bank.verify1` (Finance) then `bank.verify2` (Supervisor) before the 10th match point passes; approve requires all 10 to match (BusinessRule listing the failures); import checklist and completion need `imp.docs` + approved PI; LC approve = `lc.approve` (Finance) after checks; open LC = `lc.open` (Import) after approval, amount taken from the validated PI.

**Routes added** (`routes/actions/po-import.php`, all POST, PO/PI number in body `no`): `po.gen, po.dispatch (nos[]), po.dispatch1, po.schedule, po.run, po.retry, po.remind, po.resolve, po.update, po.devok, pi.pick, pi.record, pi.approve, pi.clarify, pi.bank, pi.doc, pi.docsdone, lc.approve, lc.open`.

**Files**: `app/Services/{PoService,PiService}.php`, `app/Http/Controllers/{PurchaseOrderController,ImportController}.php`, `routes/actions/po-import.php`, views `purchase-orders/{register,show,acknowledgments,disputes,_resolve}`, `import/{pi,pi-show,lc}`, `tests/Feature/PoImportTest.php` (15 tests: pages for all 9 roles, AC-14/15/16/17/19, schedule/remind/edit/MOQ waiver, PI match/approve, LC chain, SoD, button visibility).

**Cross-module notes**
- "View as supplier" / "Portal" links go to `route('portal', ['pCase','pSup','pMode' => 'po'])` (module C).
- "Verify contact..." on the checks tab links to `sup.show` (module F owns `supVerify`); "Raise change request" on `po.disp` links to `aw.appr` and "Resolve" on related exceptions links to `exc` (modules D / A own `apprChgForm` / `excForm`).
- The PO dispute accepted -> `appr.status = returned`: module D's re-submit/approve calls `PoService::generate()` which produces the `-R2` revision.

**Known gaps**: no PDF is generated (the e-mail archive only records the template + "attachment" label, as in the prototype); `Run due schedule` dispatches every scheduled PO regardless of its time (prototype demo behaviour); e-mail delivery itself is not integrated (Policy item 12); LC expiry / amount are not validated against Bangladesh Bank rules (placeholders only). Existing failing test `SmokeTest::test_every_page_renders` (`supplier.portal` route needs a token) comes from module C, not E.

### module-f-suppliers-admin.md
## Module F - suppliers & administration

**Added**
- `app/Services/SupplierService.php` (save, verify, masking, reads `all/find/products`), `app/Services/AdminService.php` (users, materials, reasons, PO defaults, calendars, templates, policy, authority, acceptance results, failMail, export, reset; reads `users/materials`). All user/material/supplier persistence sits behind these two services (controllers/views do not query those models) so storage can be aligned with the legacy ERP tables later.
- `app/Domain/AdminCatalog.php` (ACS + STAGES catalogues from the prototype).
- `routes/actions/suppliers-admin.php`; dynamic views `suppliers/index|show|_form`, `admin/roles|masters|calendars|templates|policy|tests|demo`; `tests/Feature/SuppliersAdminTest.php` (12 tests).
- Controllers `SupplierController`, `AdminController` rewritten (thin).

**Routes added (POST)**: `sup.store`, `sup.verify`, `ad.user.save`, `ad.user.pw`, `ad.mat.save`, `ad.reason.add`, `ad.reason.del`, `ad.settings.save`, `ad.cal.on|off|day|year`, `ad.tpl.save`, `ad.policy.save`, `ad.auth.save`, `ad.test.set`, `ad.demo.mail|export|reset`.

**Rules / decisions**
- Supplier edit (`sup.edit`): name/country change -> Unverified + exception "Supplier identity changed"; email/contact/phone change -> Unverified + "Supplier contact changed". Verification needs `po.verify` (as in the prototype JS; Procurement Supervisor), requires a callback reference, resolves both exception types.
- Phone/address/full email shown only with `sup.edit`; masked otherwise. Lists always mask email.
- Users: temp password = random, stored hashed only, flashed once (`temp_password` session flash), never audited/logged. Cannot demote/deactivate yourself or the last active admin. Duplicate e-mail rejected. Extra: "Reset password".
- Calendars: approve (demo, adds 2026; KR gets prototype holidays), revoke, add holiday/make-up day (its year becomes verified; duplicates rejected), extra verify/un-verify year. Approve resolves "Calendar unavailable" exceptions.
- Templates: edit warns (toast) when placeholders were removed.
- All admin actions need `admin.cfg`; admin pages redirect with an SoD toast for roles without the `adm` menu.
- Export: all tables as JSON; users limited to id/name/email/role/status; keys matching tok/token/secret/passw/hash/remember redacted in case state; link tokens removed from mail bodies. NOTE: supplier phone/address are exported as stored (admin only, audited).
- Reset: `admin.cfg` + `config('oilpms.demo_login')` + typing RESET; re-runs `DatabaseSeeder` (demo users reset to demo credentials; custom users are kept).

**Frozen-file fix**: `resources/views/errors/404.blade.php` had `@endsection` glued to text (left an output buffer open, made 404 tests risky); fixed.

**Known gaps**: `stageLoad` form is visible but disabled (integrator). Temp password has no forced-change-on-first-login (no column yet). Supplier-product mapping (add/remove materials per supplier) not in the prototype, not built. `SmokeTest::test_every_page_renders` currently errors on module D's `cs.csv` route (needs a sample id) - not module F.

### ui-parity-admin.md
# UI parity: admin / control / reports pages

Audited against the prototype (PAGES[...] / ACT.*): sup.dir, sup.show, ad.roles, ad.master, ad.cal, ad.tpl, ad.policy, ad.tests, ad.demo, an.perf, an.pipe, exc, notif, audit, login, 404.

Already at parity (no change): sup.dir, sup.show, ad.roles, ad.master, ad.cal, ad.tpl, ad.policy, ad.tests, an.perf, an.pipe, exc, notif, audit (columns, filters, KPIs, tabs, dialogs, row actions, Perm::can visibility all match; ours adds password reset, CSV export, year verify).

Fixed:
- ad.demo: "Load a case to a stage" was a disabled placeholder. Now works: `StageLoader` (app/Services/StageLoader.php) rebuilds a case to any of the 11 stages by driving the real services (freeze, confirmBooking, generateDrafts/sendDraft, setFull/negotiate, submitAward/decide, PO generate/dispatch, PI seed, LC). Route `ad.demo.stage` (POST /admin/demo/stage), `AdminController::stageLoad`, `AdminService::loadStage` (admin.cfg + local-demo only, audited). Redirects to the case page with a toast.
- 404: uses the prototype empty-state card ("This page does not exist" + button).
- Login: heading + organisation name in the brand block.

Known gaps: quotation numbers for stage loads come from the seeded October quotes, re-scaled to the case's booked quantity (freight/insurance/duty), not regenerated by mkQuote. Sequence counters (RFQ/QUO/PO ids) keep increasing on every load.

Tests: tests/Feature/UiParityAdminTest.php.

### ui-parity-core.md
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

### ui-parity-po.md
# UI parity audit - PO / awards / PI / LC pages

Audited aw.neg, aw.rec, aw.appr, award.show, po.reg, po.show, po.ack, po.disp, imp.pi, pi.show, imp.lc against the prototype. All were already at parity (columns, KPIs, alerts, filters, dialogs, perms) except:

- po.disp: "Raise change request" header button now opens the prototype dialog (field + reason, posts to appr.change for the working case) instead of linking to the approval queue.
- pi.show: "Record supplier PI / PI revision" header button now shows (disabled) when the PO is not accepted or LC is open, like the prototype, instead of disappearing.

Intentional deviation kept: aw.neg "Change" is red when a revised price is higher (prototype is always green).
Not fixed: po.disp related-exception "Resolve" links to the Exceptions page rather than an inline dialog.
Tests: tests/Feature/UiParityPoTest.php.

### ui-parity-sourcing.md
# UI parity audit - sourcing pages

Audited src.supp, src.rfq, rfq.show, src.track, src.quotes, quote.show, portal (staff preview + public portal), src.cs (product/tech/bulk), src.samples against the prototype.
Almost everything was already at parity (headers, KPIs, alerts, columns, filters, row actions, dialogs, tabs, empty states, Perm gating).

Fixed:
- src.supp: row "remove" button now uses the trash icon like the prototype (was a text cross).
- rfq.show: drafts table gained the prototype's Status filter (Draft/Scheduled/Sent/Blocked); rows carry data-st.

Tests: tests/Feature/UiParitySourcingTest.php.

Not changed (deliberate): staff portal preview is read-only (prototype allowed interaction; staff must not submit for suppliers);
public PO view uses a compact PO layout instead of the full poDoc.
