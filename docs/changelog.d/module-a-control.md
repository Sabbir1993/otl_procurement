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
