# Phases

| # | Phase | Output | Status |
|---|---|---|---|
| 0 | Docs & scaffold | `/docs/*`, Laravel 10 + SQLite project | ✅ done |
| 1 | **Frontend port** | Prototype UI as server-rendered Blade pages, controller per menu, routes in web.php | ✅ done (43 routes verified) |
| 2 | Backend planning | Detailed ERD, API/route contract, migration order, seed spec (this doc updated) | ⏳ next |
| 3 | Foundation backend | Auth, RBAC gates, masters (materials, suppliers, calendars), audit/notification core, seeders from mock data | |
| 4 | Requisition → RFQ | Cases, demand board, booking, RFQ drafts, deadline engine, supplier portal tokens, e-mail archive | |
| 5 | Quote → Award | Quotes, samples/QA, CS, negotiation, approval chain (checksum) | |
| 6 | PO → LC | PO generate/dispatch/ack/dispute, PI match, import docs, LC | |
| 7 | Control, reports, hardening | Exceptions, dashboards, reports, UAT AC-01…AC-20, security review, backup/runbook | |

## Phase 1 — Frontend port (detail)
1. Extract CSS → `public/css/oilpms.css`; JS engine → `public/js/oilpms.js` (body unchanged except routing).
2. Replace hash routing with path routing (`route()`, `go()`, `popstate`, initial redirect) using `window.OILPMS_BASE`.
3. Blade layout `layouts/app.blade.php`; `routes/web.php` with one named route per page key + catch-all fallback to shell.
4. Verify every nav item loads from a direct URL and via in-app click; back/forward works; refresh keeps page.
5. Keep mock data + localStorage until backend replaces it per screen.

**Done when:** all 40+ routes render identically to the original HTML, no console errors, no `#/` left.

## Phase 2 — Backend planning (deliverables)
- ERD (tables in `ARCHITECTURE.md` §3 expanded with columns/indexes).
- Per-screen data contract: which JS `DB.*` structure maps to which table/endpoint.
- Gate list from `PERM`; seeders from `MATS/SUPS/SPMAP/QT/DEM0/CALS/POLICY/TPL0`.
- Decide: server-rendered Blade per screen vs JSON endpoints feeding the existing JS (recommended: JSON endpoints first → least rewrite).

## Phase 7 — UAT checklist
AC-01 PR created, sequential ID · AC-02 PR consolidation traceable · AC-03 three vendor-bound URLs · AC-04 vendor isolation · AC-05 holiday-aware deadline · AC-06 server-side cutoff · AC-07 extension audited · AC-08 required GSM blocks submit · AC-09 currency/UOM conversion · AC-10 failed sample blocks award · AC-11 Marketing return gates Finance · AC-12 Finance return gates ED · AC-13 PO from locked data · AC-14 missing Incoterms place blocks PO e-mail · AC-15 only selected supplier gets PDF · AC-16 dispute needs approved revision · AC-17 PI bank change ⇒ validation task · AC-19 mail failure retry same PO ID · AC-20 role-permitted dashboard.
