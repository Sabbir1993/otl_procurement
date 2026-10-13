# Module-wise plan to a fully functional OIL-PMS

Sources: BRD `OIL-PMS-BRD-001` (`_src/OIL_Comprehensive_Procurement_Process_Specification.md`, 23 stages, AC-01..20), the manager deck (22 stages, PR -> LC opening), the workbook/manual (see `docs/SRS_ANALYSIS.md` for deltas), the prototype (`_src/OIL-PMS_Import_Purchase_Module.html`) and the legacy ERP schema (`_src/otl_db1.sql`, see `docs/LEGACY_DB_MAPPING.md`).
Status legend: ✅ done · 🔄 in progress (module agent running) · ⬜ planned · ❓ needs owner input.

## 0. Principles
1. Spec order = process order; each module is shippable (pages + working buttons + tests + audit) before the next depends on it.
2. Business rules are server-side, ported 1:1 from the prototype, and cited by BRD stage / AC id in tests.
3. Legacy tables win: relevant masters (`product`, `customer`, `brand`, `marketing_person`, `users`, requisition tables) are reused; new tables follow the legacy pattern (singular names, int ids, `created/updated/deleted_at/by`, `imp_` prefix).
4. Security-first, PCI-DSS-minded, Bangladesh Bank aware: no real secrets/cards in seeds, sensitive supplier/bank data encrypted + masked, dual control on bank-detail change, full audit.
5. Every module ends with: feature tests, changelog fragment, acceptance-test mapping, role matrix check.

## 1. Foundation (✅ done)
Schema + seed from prototype DB, domain port (Clock, Fmt, Perm, Masters, Deadline, QuoteSpec, Workflow, CaseBook), auth + demo role login, role-filtered shell, UI kit (`x-dtable`, `x-dialog`, `x-field`, `x-post`, `x-tabs`), purchase-case module, test harness.
Hardening still to do (cross-cutting, Phase X below): MFA/OTP for internal approvers, session policy, rate limits, encrypted casts.

## 2. Functional modules
| # | Module (menu) | BRD stages / AC | Scope & actions to make real | Status | Depends on |
|---|---|---|---|---|---|
| M1 | Purchase cases + case flow | cross-cutting | create case, working case, flow bar, next-action, timeline | ✅ | - |
| M2 | Requisitions (rq.board, rq.booking, pr) | 01-03, AC-01, AC-02 | verify/accept/reject lines (reasons), bulk, add/edit line, freeze, booking edit, confirm book qty with signed doc + serial, Import Booking Report, CSV; **PR id `PR-YYYY-NNNNN` and consolidation batch `CB-YYYY-NNNNN`** with PR-line traceability (BRD 01/02 - prototype has only lines); legacy `product_req_from_mktp(+_dtl)` as the source (Phase L) | 🔄 agent B | M1, Phase L |
| M3 | Sourcing: shortlist, RFQ, tracking, quotations | 04-09, AC-03..08 | supplier-product map, RFQ builder (urgent, extra required fields, **RFQ revision V2.. + notify**), per-supplier secure token (hash), invitation e-mails, reminders (1 working day before), extend/revoke/close, schedule send, deadline engine (CN calendar, 3/2 days, 17:00 CST), no-bid handling | 🔄 agent C | M2, calendars |
| M4 | Supplier portal (external) | 06-09, 20, AC-04/06/08 | tokenised public routes, 6-tab form from field dictionary, draft/submit/revise, server cutoff, PO accept/dispute; **optional e-mail OTP, file upload with type/size + AV scan hook, printable receipt, revision history** | 🔄 agent C | M3, M10 PO ack |
| M5 | Evaluation: comparative statement, samples/QA | 10-12, AC-09, AC-10 | CS matrix, landed cost, strategies, allocation/split/MOQ waiver, justification, finance remark, exclusion, CSV; sample register + QA result; Fail/Conditional/Waived-by-approval; **currency/UOM normalisation with dated FX source (AC-09 - prototype is USD only)** | 🔄 agent D | M3 |
| M6 | Negotiation & awards | 13-17, AC-11..13 | negotiation rounds (versioned, original quote immutable), recommendation + submit, Marketing ACK -> Finance APP -> ED, return/reject restart, change request after approval, **approval authority matrix thresholds (policy item 1), delegation, overdue escalation** | 🔄 agent D | M5 |
| M7 | Purchase orders | 18-20, AC-13..16, AC-19 | generate (frozen terms), validation checklist, **PDF generation + checksum snapshot**, dispatch/schedule/retry (same PO id), mail log, ack reminders/SLA, disputes -> revision R2.., deviation acceptance | 🔄 agent E (+ PDF ⬜) | M6 |
| M8 | Import: PI, LC | 21-22 (to LC), AC-17 | record PI, 10-point match, clarification, approve, bank-change dual control, import docs checklist, LC approval (Finance) + open (Import) | 🔄 agent E | M7 |
| M9 | Suppliers + administration | 05, 14, masters | supplier master (controlled changes, callback verification), users/roles, materials/masters, calendars (holiday verification), templates, policy items, authority matrix, acceptance-test tracker, demo utilities; **vendor prequalification/compliance fields, FX/UOM/Incoterm/port masters (BRD 16)** | 🔄 agent F (+ masters ⬜) | - |
| M10 | Control & reporting | 15, AC-20 | command centre, exceptions (resolve), notifications, audit trail + export, reports (savings & performance, pipeline & cycle time); **KPI set of BRD 15 incl. RFQ response rate, PR-to-PO lead time, on-time delivery, sample acceptance, ack SLA, three-way-match exception rate** | 🔄 agent A (+ KPIs ⬜) | all |
| M11 | ERP hand-off + stages 22-23 | 22-23, AC-18 | read the legacy ERP shipment / GRN / invoice / payment tables for the case (hand-off screens today); BRD target: shipment tracking, GRN, three-way match, payment closure. **Decision needed: build here or integrate with ERP** (deck stops at LC opening) | 🔄 agent B (stubs) / ❓ | M8, legacy DDL |

## 3. Cross-cutting phases
| Phase | Content | When |
|---|---|---|
| **L - Legacy alignment** | Replace `materials` with `product`, requisition JSON with `product_req_from_mktp(_dtl)` + `imp_requisition_line`, users with legacy columns, names -> `imp_*`, int ids + audit/soft-delete columns, `*_ms` -> datetime; promote JSON aggregate to child tables incrementally (RFQ/invitation -> quotation -> CS/award -> PO -> PI/LC) behind the existing services. Needs the missing legacy DDL (see mapping §5). | right after the current agent wave merges |
| **I - Integration of the wave** | merge changelog fragments, run full suite, wire demo "load case to stage" to the services, fix cross-module seams | next |
| **N - Notifications & scheduler** | queue + `schedule:run` (Windows Task Scheduler): RFQ reminders, deadline close, scheduled PO dispatch, overdue approval/ack escalation, ETA alerts; real SMTP mailer with retry/idempotency (BRD 11) | after I |
| **D - Documents** | PO/RFQ PDFs (dompdf), document repository for quote attachments/TDS/COA/test reports with AV hook, retention policy (policy item 12) | after I |
| **S - Security & compliance** | MFA/OTP for approvers, password policy + history (legacy `users` columns), rate limiting, encrypted casts for bank/contact data, masked UI, privacy review, Bangladesh Bank checklist (LC/TT/FX evidence retention, dual control), backup/restore runbook | parallel from L |
| **R - Reporting & KPIs** | BRD 15 dashboards with real formulas; no invented targets | after M10 |
| **U - UAT & go-live** | AC-01..AC-20 executed as feature tests + manual UAT script; production config (MySQL decision), deployment on XAMPP/Apache vhost | last |

## 4. Acceptance-test coverage plan (BRD 18)
AC-01/02 M2 · AC-03/04/05/06/07/08 M3-M4 · AC-09/10 M5 · AC-11/12/13 M6 · AC-14/15/16/19 M7 · AC-17 M8 · AC-18 M11 (three-way match) · AC-20 M10. Each AC gets a named test (`ac01_...`) so the Admin "Acceptance Tests" page can show live pass/fail from CI later.

## 5. Gaps between BRD and the prototype (carry into scope)
23 stages vs prototype 22 (stages 22 import/GRN and 23 payment only as hand-off) · PR/consolidation entities · RFQ revisions · supplier OTP & AV scanning · FX/UOM normalisation · PO PDF · reminders/escalations · KPIs with real baselines · vendor prequalification & sanction checks · domestic-purchase and single-source/emergency branches · services/capital purchase templates (out of scope until decided).

## 6. Decisions / inputs needed from the owner
1. Missing legacy DDL (unit, product_type, composition, roles/permissions, department, designation, bank, supplier/vendor, PO/LC/GRN/shipment/invoice/payment tables).
2. Build stages 22-23 here or integrate with the ERP?
3. Policy items 1-12 (authority limits, counting convention, SLAs, scoring weights...).
4. SMTP provider, PDF branding, retention period, MySQL vs SQLite for production.
5. Currencies beyond USD and the FX source of record.
