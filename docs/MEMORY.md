# Project Memory

Living log of decisions/context so any session can resume fast. Append, don't rewrite.

## Decisions
- 2026-10-13 · Stack fixed: **Laravel only**, **SQLite**, must run on machine PHP **8.1.25** ⇒ Laravel **10.x** (not 11/12).
- 2026-10-13 · Approach: frontend first (port prototype with relative path routes), backend planned afterwards.
- 2026-10-13 · Original prototype kept at `_src/OIL-PMS_Import_Purchase_Module.html` (do not edit).
- Prototype storage key `oil_pms_import_v4` (localStorage, `v:4`) — mock only.

## Source facts
- Prototype = single 1828-line HTML; CSS inline (line 1), JS IIFE lines 221–1827, all pages in `PAGES[key]`.
- Routing was hash-based (`#/key/arg`, `route()` / `go()` at ~L777). `ROUTE_OK` = detail pages allowed regardless of nav.
- Mock "now" = Tue 13 Oct 2026 08:00 UTC (14:00 BST). 3 demo cases; stage loader `STAGES` (11 stages).
- AC-18 missing from acceptance list in source.
- 12 open policy items; approval limits intentionally blank (no BDT assumed).

## Environment
- XAMPP 8, PHP 8.1.25 ZTS, Composer 2.8; ext pdo_sqlite/sqlite3/mbstring/openssl/fileinfo present.
- Project root: `D:\project_folder\php\xampp_8\htdocs\otl_procurement`.

## Org rules to honour
SSLWIRELESS (PCI-DSS, Bangladesh Bank): placeholders for sensitive values, security-first, flag data-privacy concerns.

## Open questions
- Server-rendered Blade vs JSON endpoints for backend (decide Phase 2; leaning JSON endpoints feeding existing JS).
- Auth model (local users vs SSO), e-mail provider, PDF engine, retention periods.
- LC record ID format (spec undefined).

## Progress log
- 2026-10-13 · Phase 0+1 done. Laravel 10 scaffolded in project root; `.env` DB_CONNECTION=sqlite; `database/database.sqlite` created (no migrations run yet).
- Routing: `config/oilpms.php` (page key → path) feeds `routes/web.php` and the JS router (`window.OILPMS_ROUTES`). All routes serve `layouts/app.blade.php`; unknown path → 404 shell. Verified in browser: nav click, direct URL, back/forward; no console errors.
- Run: `php artisan serve --port=8099` (or XAMPP vhost → `public/`).
- Admin pages show "Access restricted" unless role switcher = System Administrator (by design).
- Next: Phase 2 backend planning.
- 2026-10-13 · UI split into `resources/js/oilpms/**` (served as one bundle by `/_assets/oilpms.js`), per-page Blade views, real `<a>` links, `?case=`/`?tab.*=` URL state. **Agents: read `docs/changelog.md` first and append an entry after every change.**
- 2026-10-13 · Code now organised **per sidebar group** (`routes/menu/<g>.php`, `views/pages/<g>/`, `js/oilpms/menu/<g>/`); manifest order in `config/oilpms.php -> js`. Sub-folder install: asset URLs use `url()`; favicon 404 silenced.
- 2026-10-13 - REBUILT as server-rendered Laravel: controller per sidebar group, Blade view per page, explicit routes in web.php, sidebar from config/menu.php. Old JS-engine approach archived in _legacy/. Page bodies are static HTML captured from the prototype (mock data). Read docs/changelog.md first.
