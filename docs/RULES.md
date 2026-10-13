# Rules

## A. Engineering rules
1. **Laravel 10 / PHP 8.1 only.** No syntax or packages needing PHP ≥ 8.2 (no readonly classes, no DNF types, no `json_validate`). Check `composer.json` `config.platform.php = 8.1.25`.
2. **SQLite only.** No MySQL-specific SQL/JSON functions. Foreign keys ON, WAL mode. Migrations must be SQLite-safe (no dropping columns w/o doctrine/dbal awareness; prefer new migration + table rebuild).
3. **Money never float.** Decimal strings / integer minor units; round half-up at line level.
4. **Time:** persist UTC; render Asia/Dhaka (BST) and supplier TZ (CN `Asia/Shanghai`, KR `Asia/Seoul`).
5. Controllers thin; business logic in `app/Services`; validation in FormRequests; authorization via Gates/Policies — **server-side always**.
6. Every state change → `audit_logs` row (who, role, action, ref, detail, case). No update/delete on audit.
7. Relative URLs / `route()` helper only; no hard-coded host paths.
8. Frontend phase: keep prototype behaviour identical; change only routing + asset paths. No framework/bundler additions.
9. Match surrounding code style; small commits per phase.

## B. Security & compliance rules (SSLWIRELESS org policy)
1. Never commit or print real card numbers, tokens, merchant credentials. Examples use `[PLACEHOLDER]` (e.g. `[SUPPLIER-PHONE]`, `[TOKEN]`, `[BANK-ACCOUNT]`).
2. Security-first: CSRF, output escaping (`esc`/Blade `{{ }}`), rate-limiting, least privilege, secure cookies.
3. Supplier tokens: store hash only; show plain token once at send time.
4. Flag privacy concerns: supplier contacts and bank details = sensitive → encrypt at rest, mask in UI/logs, exclude from exports unless role allows.
5. Bangladesh Bank compliance: LC/TT/FX records retained, traceable and change-controlled; bank-detail change requires **independent callback verification** (two-person: Finance then Supervisor).
6. No secrets in `.env.example`; `.env` and `*.sqlite` in `.gitignore`.

## C. Business rules (from prototype engine)
**Deadlines**
- `calcDeadline(calendar, issueTs, n)`: skip day of issue, count `n` supplier-country working days (weekends off, holidays off, makeup days on); close at calendar close hour (CN 17:00).
- n = 3 (urgent = 2). Calendar not approved or year not verified ⇒ draft **Blocked** + exception *Calendar unavailable*.
- Invitation state: Submitted > Revoked > Expired (now > deadline) > Sent.
- Extension keeps `origDeadline`, appends to `ext[]`, new deadline communicated (template D), audited.
- RFQ closed when every invitation is submitted/revoked/expired; closed with a line having zero valid bids ⇒ exception *No bid*.

**Quotes / eligibility**
- Landed = `price*qty + freight + insurance + duty + bank`.
- Not eligible: sample `Fail`, sample `Pending`, validity expired.
- Warnings: sample `Conditional`, MOQ > required, declared deviation.
- Auto-pick candidates: eligible, MOQ ≤ required, sample ≠ Conditional. Strategies: lowest landed / highest quality / lowest lead / highest index.
- Index = `.35*lowestLanded/landed*100 + .30*quality + .20*minDays/days*100 + .10*terms + .05*history` (illustrative; Policy item 7).
- CS line status: Excluded | Complete (alloc ≥ required) | Partial | Pending.
- **Justification required** when: deviation declared, conditional sample, MOQ waiver, split award, selected ≠ lowest landed.
- Alloc above MOQ rule: MOQ > allocated qty ⇒ waiver (supervisor `cs.waiver`) with commercial reason.

**Approval**
- Status flow: `none → mkt → fin → ed → approved` ; `returned | rejected` restarts at Marketing as new version.
- Award pack has checksum (`hash` of pid/sid/qty/price); if CS changes after submit, approve is blocked (*pack out of date*).
- Remarks mandatory on return/reject. Reject unavailable at Marketing.
- Approved ⇒ commercial terms frozen, POs auto-generated. Change request ⇒ freeze unsent POs, status `returned`, exception *Change after approval*.
- Segregation of duties: `need(permission)` — actor lacking permission is refused with who can do it.

**PO**
- Must have Incoterms **named place**, quantity tolerance, claims clause; else dispatch blocked + exception *PO vs…/Incoterms without place*.
- Dispatch uses approved locked data only; retry on mail failure **reuses same PO ID** (AC-19).
- Supplier action: Accept or Dispute only. Dispute stops PI progression; material change ⇒ re-approval.

**PI / LC**
- PI compared to PO on **10 match points** (`piMatch`); PO must be `Accepted` first.
- Bank-detail change ⇒ exception *Bank details changed*, restrict processing until two verifications.
- Import docs checklist ⇒ Finance LC approval ⇒ Import opens LC (ref recorded).

**Supplier master**
- Contact/identity change via portal ⇒ `Unverified` until callback; exceptions *Supplier contact/identity changed*.

**Exceptions** (type → default owner, severity) — see `EXT` map in prototype; each exception: id `EXC-YYYY-nnnnn`, status Open/Resolved, notifies by severity.

## D. IDs & numbering
`nid(kind,year)` → `KIND-YYYY-00001` per-kind sequence (RFQ, EXC, PO, award `AP…`, supplier `SUP-00001`, case `IC-YYYY-MM`). Backend: transactional per-kind counters table.
