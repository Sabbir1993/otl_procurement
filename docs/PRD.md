# PRD — OIL-PMS · Import Purchase Module

Source: `_src/OIL-PMS_Import_Purchase_Module.html` (workable prototype v3, all data MOCK).
Owner: Osman Interlinings Limited (OIL) · Built by SSLWIRELESS.

## 1. Purpose
Digitise the monthly **import raw-material purchase cycle** (China / Korea suppliers) from requisition to LC opening, with segregation of duties, supplier-country working-day deadlines, full audit trail and controlled supplier portal.

**Unit of work = Purchase Case** (`IC-YYYY-MM`): one monthly import booking, tracked end to end.

## 2. Scope
| In scope | Out of scope (existing ERP — hand-off screens only) |
|---|---|
| Requisition board, consolidation, confirmed book qty | PR creation/editing (`pr`) |
| Supplier shortlist, RFQ, secure per-supplier links, deadlines, extensions | Shipment tracking (`ship`) |
| Quotation capture, samples & QA | GRN / inspection (`grn`) |
| Comparative Statement (CS), negotiation, award recommendation | Invoice & payment (`pay`) |
| 3-step approval (Marketing → Finance → ED) | |
| PO generation, dispatch, supplier ACK / dispute, amendments | |
| PI capture, 10-point PO↔PI match, import docs, LC approval/open | |
| Exceptions, notifications, audit trail, reports, admin masters | |

## 3. Personas & roles (RBAC)
| Key | Role | Key permissions |
|---|---|---|
| exec | Procurement Executive | demand/booking/sp/sup edit, RFQ create/send/remind, CS select/negotiate/submit, PO edit/dispatch, PI record/match, new case |
| sup | Procurement Supervisor | exec + demand freeze, booking confirm, RFQ extend/close/revoke, exception resolve, PO verify/correct, bank verify (2nd), MOQ waiver |
| mkt | Marketing | approval step 1 (ACK) |
| qa | QA / Technical | sample result edit |
| fin | Finance & Accounts | approval step 2, finance remark, LC approve, bank verify (1st), exception resolve, PI match |
| ed | ED / Final Approver | final approval, change approval |
| imp | Import / Logistics | PI record/match, import docs, LC open |
| admin | System Administrator | admin config, supplier edit |
| audit | Internal Audit / MD | read-only |

Menu visibility per role is defined in `ROLE_NAV` (see `app/Support/Navigation.php` once backend lands).

## 4. Functional requirements (by navigation group)
1. **Command Center** — KPIs, stage pipeline, delays, role-permitted live view (AC-20).
2. **Purchase Cases** — list, create, case detail with full flow.
3. **Requisitions** — Create/Edit PR (ERP link), Requisition Board (verify/reject with reason, consolidate; AC-02), Confirmed Book Qty (freeze → confirm, serial & doc).
4. **Sourcing / RFQ** — Supplier shortlist (supplier↔product map), RFQs (one per case, per-supplier drafts), Link & Deadline admin (extend/revoke/remind), Submitted quotations, Comparative Statement, Samples & QA.
5. **Negotiation & Awards** — negotiation history, final recommendation (justification rules), approval queue + award pack.
6. **Purchase Orders** — register, generate draft e-mails, dispatch/schedule/retry (AC-19), acknowledgments (Accept/Dispute only), amendments & disputes.
7. **Import & Warehouse** — PI & banking docs (10-point match), LC register, ERP hand-offs (shipment, GRN, payment).
8. **Suppliers** — directory (contact change stays *Unverified* until callback), **Supplier Portal** (tokenised, 6-tab quote form: Company, Commercial, Technical, Delivery & packing, Sample & docs, Review & submit).
9. **Reports** — savings & performance, pipeline & cycle time.
10. **Control** — exceptions (named owner, severity), notifications, audit trail.
11. **Administration** — users & roles, materials & masters, working-day calendars, e-mail templates (RFQ, reminder, confirmation, extension, closure, PO…), policy & approval authority, acceptance tests, demo utilities.

## 5. Key business rules (summary — full list in `RULES.md`)
- Deadlines = supplier-country approved working-day calendar (CN: 3 working days, 2 if urgent, close 17:00 local); unverified holiday year **blocks publication**.
- Quote validity, sample Fail ⇒ blocked from award; Conditional ⇒ approved deviation needed.
- Award needs justification when: split award, MOQ waiver, not lowest landed cost, deviation.
- Approval order fixed: Marketing ACK → Finance APP → ED final; remarks mandatory on return/reject; no self-approval, no skipped step.
- Approval locks commercial terms; later change ⇒ freeze unsent POs + re-approval.
- PO supplier may only Accept or Dispute. PO missing Incoterms named place ⇒ auto-email blocked + exception.
- PI bank-detail change ⇒ independent validation task; processing restricted.
- Landed cost = price×qty + freight + insurance + duty + bank.
- Index score weights (illustrative, Policy item 7): cost .35, tech .30, delivery .20, terms .10, history .05.

## 6. Acceptance criteria
AC-01…AC-20 (AC-18 intentionally absent in source) — listed in the prototype *Acceptance Tests* page and reproduced as UAT checklist in `PHASES.md` §Phase 7.

## 7. Open policy items (nothing pre-filled; configurable)
12 items incl. approval authority matrix (no BDT amount assumed), China deadline counting, sample waiver criteria, score weights, PO SLA, FX/landed-cost assumptions, three-way-match tolerances, mandatory PO clauses, integrations/retention.

## 8. Non-functional requirements
- **Compliance:** operates within SSLWIRELESS/PCI-DSS-aligned controls; Bangladesh Bank import-payment (LC/TT, FX) rules respected — retain LC/PI/bank records per Bangladesh Bank & company retention policy (Policy item 12).
- **Security:** token-hashed supplier links (sha256), expiring, single-supplier scope; server-side deadline enforcement; CSRF, RBAC, audit on every state change.
- **Privacy:** supplier contact data and bank details are personal/sensitive — encrypt at rest, mask in UI/logs, never in URLs. All examples use `[PLACEHOLDER]` values.
- **Time:** store UTC; display Asia/Dhaka (BST) + supplier TZ (CST/KST).
- **Compat:** PHP 8.1 (target machine) → Laravel 10; SQLite single file.
- **UX:** responsive (mobile bottom bar), light/dark theme, skeleton loaders.
- **Auditability:** immutable audit log (cap 1500 in prototype → unbounded table in backend).
