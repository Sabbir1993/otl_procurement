# Architecture

## 1. Constraints (decided)
| Layer | Choice | Why |
|---|---|---|
| Application | **Laravel only** (monolith) | Single stack; no Node/SPA build required on target machine |
| PHP | **8.1.x** (XAMPP 8.1.25) | ⇒ **Laravel 10.x** (Laravel 11+ needs PHP ≥ 8.2). Do not upgrade framework without upgrading PHP |
| DB | **SQLite** (`database/database.sqlite`) | Zero-ops, file DB. Enable WAL + `foreign_keys=ON` |
| Front-end | Blade shell + vanilla JS engine from prototype (no bundler) | Parsed 1:1 from the prototype; static assets in `public/` |

No MySQL/Redis/queue worker assumed: use `database` queue + `schedule:run` via Windows Task Scheduler only when needed.

## 2. Phase-1 (frontend) structure - current
Server-rendered Blade, one controller per sidebar group, explicit named routes in `routes/web.php`.
```
routes/web.php                          43 named GET routes, grouped by sidebar menu
app/Http/Controllers/<Group>Controller  CommandCenter, PurchaseCase, Requisition, Sourcing, Award,
                                        PurchaseOrder, Import, Supplier, Report, Control, Admin
resources/views/<group-dir>/*.blade.php page views (extend layouts.app)
resources/views/layouts|partials|components   shell: sidebar (config/menu.php), topbar, mobile bar, <x-icon>
public/css/oilpms.css, public/js/app.js prototype CSS + shell-only JS
_src/ original prototype | _legacy/ superseded JS-engine phase
```
See `docs/changelog.md` for the full route/controller/view table and conversion notes. Page bodies are static prototype HTML until the backend phases replace them with Blade loops over real data.

## 3. Target (backend) architecture — Phase 3+
```
Browser ──HTTP──> Laravel (routes → Controllers → Services → Models) ──> SQLite
                    │  FormRequest validation · Policies/Gates (RBAC) · Events → Audit/Notify
                    └─ Mail (SMTP) · PDF (dompdf) · Scheduler (deadlines, reminders, scheduled PO dispatch)
```
Layers: `Http/Controllers` (thin) → `Services` (workflow & maths: `DeadlineService`, `ComparativeStatementService`, `ApprovalService`, `PoService`, `PiMatchService`) → `Models` (Eloquent) → `Enums` (statuses). Domain events write `audit_logs` and `notifications`.

JS engine stays as presentation until each screen is migrated to server-rendered Blade + small JS (progressive; the API contract is JSON from the same Laravel app, `routes/web.php` session-auth, no separate API tier).

### Core tables (draft)
`users, roles, role_permissions` · `materials, suppliers, supplier_products, supplier_terms` · `calendars, holidays` · `purchase_cases, demand_lines, booking_lines` · `rfqs, rfq_invitations(token_hash, deadline, orig_deadline), invitation_extensions, quotes, quote_lines, samples` · `cs_lines, cs_allocations, negotiations` · `approvals(award package, version, checksum), approval_steps` · `purchase_orders, po_lines, po_acks, po_amendments` · `pis, pi_matches, lc_records, import_docs` · `exceptions, notifications, audit_logs, email_templates, email_archive, policy_items, authority_matrix, acceptance_tests`.
Money: store as integer minor units or `DECIMAL` strings (SQLite is dynamically typed — cast in model; never float for money). Time: UTC ISO-8601.

## 4. Security-first
- Supplier links: random 32-byte token, store **sha256 hash only**; expiry = deadline; scope = one supplier+RFQ; server-side cutoff enforcement; rate-limit; revocable.
- Sessions: `SESSION_SECURE_COOKIE`, `SameSite=Lax`, CSRF on all POST; HTTPS in production.
- RBAC via Gates mapped 1:1 from prototype `PERM`; enforce **in controllers/services**, never only in UI.
- Sensitive data (supplier phone, bank details, PI bank info): encrypted casts (`encrypted`), masked in lists/logs; no PII in URLs or query strings.
- Audit: append-only; no update/delete routes.
- `.env`, `database.sqlite` outside web root in production; never commit.
- Bangladesh Bank: preserve LC/PI/payment evidence & change history for audit; FX/charges assumptions configurable (Policy item 9).

## 5. Deployment (target machine)
XAMPP/Apache → point vhost DocumentRoot at `public/`. Needs ext: `pdo_sqlite, sqlite3, mbstring, openssl, fileinfo` (all present). Backup = copy `database/database.sqlite` (use `sqlite3 .backup` while live).
