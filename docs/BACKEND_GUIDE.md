# Backend Guide (read before touching code)

Phase 2/3 of the project: every prototype page and action button becomes a real Laravel feature.
Spec = the prototype. Its logic lives in `_legacy/oilpms-js-engine/` (page templates, `ACT.*` handlers, business functions). Its rendered look is already in the static Blade views under `resources/views/<menu>/`.

## Stack & constraints
Laravel 10 / PHP 8.1 (no 8.2+ features) / SQLite. Server-rendered Blade. No Node build. Controllers are thin; business rules live in services; every state change writes an audit row; roles are enforced server-side.

## Data model (important)
- Relational: `materials, suppliers, supplier_products, calendars, exceptions, audit_logs, app_notifications, mails, sequences, settings, email_templates, policy_items, acceptance_tests, saved_views, users`.
- **Case aggregate:** everything workflow-related for ONE purchase case is JSON in `purchase_cases.state`, wrapped by `App\Domain\CaseBook` (`$cb->s` = the array; see the class header for the shape). All timestamps inside it are epoch **milliseconds** UTC (`Clock::ms()`).
- Seed = the prototype's own demo DB: `php artisan db:seed` loads 3 cases (IC-2026-09 completed, IC-2026-10 at "quotations received", IC-2026-11 fresh) + exceptions/audit/mails/notifications. Regenerate the JSON with `node _legacy/dump-seed.cjs`.

## Domain layer (`app/Domain`, already ported & verified against the prototype)
`Clock` · `Fmt` (n0, usd, dBD, dCN ... same output as the prototype) · `Perm` (can / need / role / roleName / navAllows; config/roles.php) · `Masters` (cached materials/suppliers/calendars/settings) · `Deadline` (working-day engine) · `QuoteSpec` (quotation field dictionary + pricing maths; config/quote_fields.php) · `Workflow` (nid, log, notify, raise, resolve, mail) · `CaseBook` (aggregate + case helpers: reqQty, bids, elig, csOf, scored, pick, lineAwards, awardChk, flow, nextAction ...).
Business exceptions: `App\Exceptions\BusinessRule` (shown as red toast + form re-opens with old input), `SegregationOfDuties` (thrown by `Perm::need('x')`, shown as amber toast). Never return a bare 403/500 for these.

## How to build a page + its actions (follow `PurchaseCaseController`, `CaseService`, `cases/index|show.blade.php`, `routes/actions/cases.php`)
1. **Service** `app/Services/<Name>Service.php`: one public method per action. Inside: `Perm::need('perm.key')` (permission keys = prototype `need('...')`, config/roles.php), validate business rules (`throw new BusinessRule('...')`), mutate through `CaseBook::tx($caseId, fn (CaseBook $cb) => ...)` (transaction + save), call `Workflow::log/notify/raise/mail`. Port the prototype function 1:1 - same rules, same messages, same numbers.
2. **Controller** (thin): `$request->validate([...])`, call the service, `return $this->done('message', 'ok'|'warn'|'bad')` (redirect back + toast) or `$this->doneTo($url, ...)`.
3. **Routes**: GET pages already exist in `routes/web.php` (route name = page key, e.g. `src.cs`; do not rename). Only change the controller class/method on a GET line you own. **All new routes (POST/PUT/DELETE and extra GETs) go in `routes/actions/<your-module>.php`** (auto-loaded inside the `auth` group). Name them `<area>.<verb>`.
4. **View** `resources/views/<menu>/<page>.blade.php` replaces the static snapshot with Blade built from data. Reuse the prototype markup/CSS classes (copy structure from the existing static view of that page - it IS the target design). Use components: `<x-dtable>` (server-side search/filter/sort/paginate/CSV, see App\View\Components\Dtable), `<x-dialog>` (+ open with `<button data-open="id" data-fill='{"field":"v"}'>`), `<x-field>`, `<x-post>` (one-click POST button with optional `confirm`), `<x-card>`, `<x-tabs>` (server-side `?tab=`), `App\Support\Ui` (pill, alert, kpi, kpiRow, dl, link, url, flowBar, empty). Show/hide buttons with `Perm::can()` like the prototype did, but the server must still enforce via the service.
5. **Tests**: `tests/Feature/<Module>Test.php` (extend `Tests\TestCase`, `use RefreshDatabase`, `$this->seed(DatabaseSeeder::class)`, `$this->asRole('exec')`). Cover each action's happy path, its business-rule failures and the segregation-of-duties block. phpunit uses an in-memory SQLite (phpunit.xml), so it never touches `database/database.sqlite`.

## Page layout contract
Views extend `layouts.app` and set sections: `title`, `crumbs`, `heading` (defaults to title), `subheading`, `actions`, `content`.
**Gotcha:** Blade does not compile a directive that directly follows a word character, so `...text@endsection` on one line breaks. Always put `@endsection` on its own line when the section body is plain text.
`$shell` (role, user, menu, badges, cases) is injected into the layout/partials automatically.

## Environment gotchas (Windows)
- **Do not write PHP/JS files through bash heredocs or `python - <<EOF` when they contain backslashes** (namespaces `App\...`, regexes): the tool strips/halves them. Use the Write/Edit tools for such files.
- Never run `php artisan migrate:fresh`/`db:seed` against `database/database.sqlite` (shared dev DB used by others). Use phpunit. If you need a running server use a unique port (module letter A..F -> 8101..8106) and a throwaway copy of the DB via `DB_DATABASE=<path>` env.
- Don't edit files owned by another module (see the ownership table). Shared files (`config/*`, `app/Domain/*`, `app/Support/*`, `resources/views/layouts|partials|components`, `public/js/app.js`, `public/css/oilpms.css`) are frozen: if you need a change, add a new file of your own (e.g. `app/Domain/<Module>Helpers.php`, extra CSS in a `<style>` block inside your view) and mention it in your changelog fragment. Only exception: fixing a clear bug in a frozen file is allowed if you describe it in your fragment.

## Ownership (one agent per row)
| Module | Pages (route names) | Controllers / services |
|---|---|---|
| A control & reporting | `home`, `an.perf`, `an.pipe`, `exc`, `notif`, `audit` | CommandCenterController, ReportController, ControlController |
| B requisitions & ERP stubs | `rq.board`, `rq.booking`, `pr`, `ship`, `grn`, `pay` | RequisitionController, ErpHandoffController, RequisitionService |
| C RFQ, quotations & supplier portal | `src.supp`, `src.rfq`, `rfq.show`, `src.track`, `src.quotes`, `quote.show`, `portal` | SourcingController, PortalController, RfqService, QuotationService |
| D comparative statement, samples, awards | `src.cs`, `src.samples`, `aw.neg`, `aw.rec`, `aw.appr`, `award.show` | ComparativeStatementController, SampleController, AwardController, CsService, ApprovalService |
| E purchase orders, PI & LC | `po.reg`, `po.show`, `po.ack`, `po.disp`, `imp.pi`, `pi.show`, `imp.lc` | PurchaseOrderController, ImportController, PoService (extend), PiService |
| F suppliers & administration | `sup.dir`, `sup.show`, `ad.roles`, `ad.master`, `ad.cal`, `ad.tpl`, `ad.policy`, `ad.tests`, `ad.demo` | SupplierController, AdminController, SupplierService, AdminService |
| done (reference) | `cases`, `case.show` | PurchaseCaseController, CaseService |

Cross-module contracts (implement exactly these signatures so modules compose):
- `PoService::generate(CaseBook $c): void` exists (called by D's final approval). E extends the class with dispatch/validate/ack.
- B: `RequisitionService::freeze(CaseBook $c, ?string $by = null): void` and `confirmBooking(...)`; C: `RfqService::generateDrafts(CaseBook $c, array $opt = []): void`, `sendDraft(CaseBook $c, array &$draft, ?int $ts = null, ?string $by = null): bool`, `checkNoBid(CaseBook $c, ?string $by = null): void`; D: `CsService::setFull`, `negotiate`, `ApprovalService::submitAward(CaseBook $c, ?string $by, ?string $summary)`, `decide(CaseBook $c, string $d, ?string $remark, ?string $by)`; E: `PoService::acknowledge(CaseBook $c, array &$po, string $decision, ?string $reason)` exists (used by the supplier portal, C). E: `PoService::dispatch(CaseBook $c, array &$po, ?int $ts = null, ?string $by = null): bool`, `PiService::seed(...)`. These are what the demo "load case to stage" utility (integrator task) will call.

## Changelog protocol
Do NOT edit `docs/changelog.md` (the integrator merges). Write `docs/changelog.d/<module>.md`: what you added/changed, routes added, tests added, known gaps. Keep it factual.
