# Role-wise access (RBAC + segregation of duties)

Source of truth: `config/roles.php` (`perm` = what a role may do, `role_nav` = which menu pages it sees). Enforcement: every service method calls `Perm::need('<permission>')` (server side, throws `SegregationOfDuties` -> amber toast); the UI only hides buttons with `Perm::can()`. Menu visibility is cosmetic - a hidden page is still blocked by the permission checks of its actions. If you change this file, change the matrices below in the same commit.

Roles are demo users `{role}@oil-pms.test` locally (`config('oilpms.demo_login')`); in production the role is the `users.role` column (legacy role/permission tables were not supplied, see `LEGACY_DB_MAPPING.md` §5). Privacy: supplier phone, address and full e-mail are visible only to roles with `sup.edit`.

## 1. Roles
| Key | Role | Purpose |
|---|---|---|
| `exec` | Procurement Executive | Prepares: requisition lines, booking, RFQ, comparison, negotiation, PO, PI |
| `sup` | Procurement Supervisor | Executive rights + controls: freeze, confirm booking, extend/close/revoke links, waivers, verification, resolve exceptions |
| `mkt` | Marketing | Requester; first approver of an award (acknowledge) |
| `qa` | QA / Technical | Records sample / QA results |
| `fin` | Finance & Accounts | Finance approval, finance remark, LC approval, bank-detail verification (first check) |
| `ed` | ED / Final Approver | Final award approval, change request after approval |
| `imp` | Import / Logistics | PI, import documents, LC opening |
| `admin` | System Administrator | Users, masters, calendars, templates, policy, demo utilities (no business approvals) |
| `audit` | Internal Audit / MD (read-only) | Sees everything except admin masters; can change nothing |

## 2. Permission keys
| Permission | Meaning | exec | sup | mkt | qa | fin | ed | imp | admin | audit |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `case.new` | Create a purchase case | ✔ | ✔ | | | | | | | |
| `demand.edit` | Add / edit / verify / accept / reject requisition lines (bulk too) | ✔ | ✔ | | | | | | | |
| `demand.freeze` | Freeze the booking requisition | | ✔ | | | | | | | |
| `booking.edit` | Edit booking lines / book qty | ✔ | ✔ | | | | | | | |
| `booking.confirm` | Confirm book qty (signed document + serial) | | ✔ | | | | | | | |
| `sp.edit` | Supplier-material shortlist mappings | ✔ | ✔ | | | | | | | |
| `sup.edit` | Create / edit suppliers; see supplier contact data | ✔ | ✔ | | | | | | ✔ | |
| `po.verify` | Verify supplier contact by callback (clears "contact/identity changed") | | ✔ | | | | | | | |
| `rfq.create` | Create / update RFQ drafts | ✔ | ✔ | | | | | | | |
| `rfq.send` | Send / schedule / run RFQ drafts | ✔ | ✔ | | | | | | | |
| `rfq.remind` | Send reminder to a supplier | ✔ | ✔ | | | | | | | |
| `rfq.extend` | Authorised deadline extension | | ✔ | | | | | | | |
| `rfq.revoke` | Revoke a supplier link | | ✔ | | | | | | | |
| `rfq.close` | Apply cut-off (close RFQ) | | ✔ | | | | | | | |
| `sample.edit` | Record sample / QA result | | | | ✔ | | | | | |
| `cs.select` | Allocate / clear / exclude / bulk-apply in the comparative statement | ✔ | ✔ | | | | | | | |
| `cs.negotiate` | Negotiation rounds (versioned price revisions) | ✔ | ✔ | | | | | | | |
| `cs.waiver` | MOQ waiver | | ✔ | | | | | | | |
| `cs.submit` | Submit the award package for approval | ✔ | ✔ | | | | | | | |
| `appr.mkt` | Marketing acknowledgement (cannot reject) | | | ✔ | | | | | | |
| `appr.fin` | Finance approval / return / reject | | | | | ✔ | | | | |
| `fin.remark` | Finance remark (only at Finance / ED stage) | | | | | ✔ | | | | |
| `appr.ed` | Final approval / return / reject (generates POs) | | | | | | ✔ | | | |
| `appr.change` | Change request on an approved package | | | | | | ✔ | | | |
| `po.edit` | Edit PO terms before dispatch; accept deviation | ✔ | ✔ | | | | | | | |
| `po.correct` | Correct PO data blocking dispatch | | ✔ | | | | | | | |
| `po.dispatch` | Dispatch / schedule / retry / remind / resolve PO disputes | ✔ | ✔ | | | | | | | |
| `pi.record` | Record supplier PI and revisions | ✔ | ✔ | | | | | ✔ | | |
| `pi.match` | 10-point PI match, approve PI, clarification | ✔ | ✔ | | | ✔ | | ✔ | | |
| `bank.verify1` | Bank-detail verification, first control (Finance) | | | | | ✔ | | | | |
| `bank.verify2` | Bank-detail verification, second control (Supervisor) | | ✔ | | | | | | | |
| `imp.docs` | Import document checklist | | | | | | | ✔ | | |
| `lc.approve` | LC approval | | | | | ✔ | | | | |
| `lc.open` | Open the LC | | | | | | | ✔ | | |
| `exc.resolve` | Resolve exceptions | | ✔ | | | ✔ | | | | |
| `admin.cfg` | Users, masters, calendars, templates, policy, tests, demo utilities, export/reset | | | | | | | | ✔ | |

Not role-gated (any signed-in role): view pages in the role's menu, working-case selection, global search (results limited to pages the role can open), own notifications, CSV export of visible tables, audit-trail CSV for roles that see Audit Trail.

## 3. Menu / page access
✔ = visible, a list = only those pages, – = hidden. Page keys are route names.
| Menu group (pages) | exec | sup | mkt | qa | fin | ed | imp | admin | audit |
|---|---|---|---|---|---|---|---|---|---|
| Command Center (`home`) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Purchase Cases (`cases`, `case.show`) | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| Requisitions (`pr`, `rq.board`, `rq.booking`) | ✔ | ✔ | board, booking | – | – | – | – | ✔ | ✔ |
| Sourcing / RFQ (`src.supp`, `src.rfq`/`rfq.show`, `src.track`, `src.quotes`/`quote.show`, `src.cs`, `src.samples`) | ✔ | ✔ | cs, samples | quotes, cs, samples | cs | cs | – | ✔ | ✔ |
| Negotiation & Awards (`aw.neg`, `aw.rec`, `aw.appr`/`award.show`) | ✔ | ✔ | ✔ | rec, appr | ✔ | ✔ | – | ✔ | ✔ |
| Purchase Orders (`po.reg`/`po.show`, `po.ack`, `po.disp`) | ✔ | ✔ | reg | – | ✔ | reg | reg, ack | ✔ | ✔ |
| Import & Warehouse (`imp.pi`/`pi.show`, `imp.lc`, `ship`, `grn`, `pay`) | ✔ | ✔ | – | – | ✔ | lc | ✔ | ✔ | ✔ |
| Suppliers (`sup.dir`/`sup.show`, `portal`) | ✔ | ✔ | – | – | – | – | – | ✔ | directory |
| Reports (`an.perf`, `an.pipe`) | ✔ | ✔ | perf | – | ✔ | ✔ | – | ✔ | ✔ |
| Control (`exc`, `notif`, `audit`) | ✔ | ✔ | notif, audit | notif | exc, notif, audit | notif, audit | notif | ✔ | ✔ |
| Administration (`ad.*`) | – | – | – | – | – | – | – | ✔ | – |

Admin pages: roles without the Administration menu are redirected with a warning toast.

## 4. Segregation-of-duties rules (beyond the permission table)
- **Preparer ≠ approver:** the roles holding `cs.select/submit` hold none of `appr.*`; approvers cannot edit allocations.
- **Award chain:** Marketing acknowledges → Finance approves → ED approves; each step must follow the previous one. Marketing cannot reject. A user who submitted the package or already decided a step cannot decide a later step. A stale package (checksum changed after a decision) is blocked.
- **Change after approval:** `appr.change` (ED) only on an approved package; raises a "Change after approval" exception and sends the package back for re-approval (PO becomes `-R2` when re-issued).
- **Bank details (AC-17):** processing is blocked until both `bank.verify1` (Finance) and `bank.verify2` (Supervisor) are recorded - two different people.
- **Supplier master:** e-mail / contact / phone changes raise "Supplier contact changed"; name / country changes raise "Supplier identity changed"; both mark the supplier Unverified and block PO dispatch until `po.verify` (Supervisor) records a callback reference.
- **PO dispatch (AC-14/15):** a PO failing the 5-check validation is never mailed; only the selected supplier receives its PO.
- **Users (admin):** cannot demote or deactivate yourself or the last active admin; temporary passwords are random, shown once, stored hashed, never audited.
- **Supplier portal:** external suppliers have **no role**. They act only through a per-supplier secret link (SHA-256 stored, throttled, expiring, revocable); the portal denies every internal permission and records the actor as "Supplier". Staff "Supplier Portal Preview" is read-only.
- **Demo-only controls:** role switcher, one-click demo login, "Load a case to stage", reset and export are available only with `oilpms.demo_login` on and `admin.cfg`; they must be off in production.

## 5. Security & compliance notes (Bangladesh Bank / PCI-DSS minded)
- Dual control on bank-detail verification and a mandatory callback on supplier contact changes address payment-diversion fraud; keep these two rules when moving to production.
- No card data is handled; bank/contact data are masked in lists and shown only to `sup.edit` roles. Production should add encrypted casts for bank fields (Phase S in `MODULE_PLAN.md`).
- Every state change writes `audit_logs` (actor, role, case, action); `audit` role can export it. Retention period is an open owner decision (policy item 12).
- Open: MFA/OTP for approvers, password policy and forced first-login change (legacy `users.force_password`), session timeout, delegation / approval limits by amount (policy item 1) - not enforced yet, so any `appr.*` holder can approve any value.

## 6. Changing a role's rights
1. Edit `config/roles.php` (`perm` and/or `role_nav`).
2. Ensure the action's service calls `Perm::need()` with the same key.
3. Update §2 / §3 here, add or adjust a test (`tests/Feature/*`, `asRole(...)`), and note it in `docs/changelog.md`.
