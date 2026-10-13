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
