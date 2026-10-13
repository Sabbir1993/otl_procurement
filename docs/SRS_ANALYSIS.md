# SRS Analysis - source-document delta and gap list

Prepared 13 Oct 2026. Baseline = `_src/OIL_Comprehensive_Procurement_Process_Specification.md` (BRD OIL-PMS-BRD-001 v1.0, 11 Oct 2026, "Proposed / For Review"), cited as **BRD §n**. Others: **MAN** = manual (`_legacy/manual.txt`), **XLS** = workbook (`_legacy/sheet_strings.txt` + sheet XML in `_src/*.xlsx`), **DECK** = `_legacy/deck.txt` (slide n), **PRD/RULES/BG/PH** = `docs/*.md`. No document fixes any approval threshold, SLA or BDT amount. Nothing below is invented; where a point could not be verified it says "verify".

## 1. What the four documents are and how they relate

- **BRD (md)** - the full target-state specification: 23 stages (01 requisition to 23 invoice/payment closure), 20 sections incl. data dictionary, China deadline engine, scoring, approval matrix, PO/PI rules, import/payment lifecycle, event matrix, templates A-H, entity IDs, RBAC/SoD, KPIs, NFRs, 16 exception scenarios, 12 open policy items, 20 acceptance tests, screen map.
- **MAN (docx)** - a verbatim Word rendering of the BRD: same ID, version, date, section numbering, tables and text (checked line by line against BRD §1-20; only "&amp;" entity encoding and layout differ; "Figure 1-5" captions for the diagrams exist only in the docx). **No content delta.** Treat as the same document.
- **XLS (workbook, 18 sheets)** - an explicitly derived management/analyst view of the BRD ("Source: ... OIL-PMS-BRD-001 v1.0", Read Me). It is **narrower in scope (stages 01-22, ends at LC opening)**, re-cuts content into RACI map, decision gates, 14 event notifications, 6 KPIs, etc., adds a few clarifications (below), and leaves policy/test result cells empty. Read Me line: "stage 22 covers LC opening ... Shipment, customs clearance, GRN, invoice matching and payment are outside this workbook."
- **DECK (pptx, "V2", 10 slides, 11 Oct 2026)** - management presentation to the "Honorable Managing Director"; summarises XLS scope (7 process heads, **22 stages**, 8 gates, 3 approvals) and ends with 5 decisions for management. No speaker notes (notes slides contain only slide numbers).
- **Authority:** all four carry the same date (11 Oct 2026) and are "Proposed / For Review"; there is no later version. The BRD is the authoritative requirements text; XLS/DECK are subordinate summaries and where they differ in scope (22 vs 23 stages) the BRD wins for the full lifecycle, XLS/DECK win only as the record of what management has been shown. The DECK names the companion workbook `OIL_Procurement_Process_Flow.xlsx` (slide 10) while the supplied file is `OIL_Procurement_Process_Automation.xlsx` - filename mismatch, same content lineage (XLS title: "Procurement Process Flow Workbook").

## 2. Delta table (BRD vs MAN / XLS / DECK)

MAN rows are omitted because MAN = BRD (see §1). Impact column: S=screen, T=table/entity, R=rule/config.

### 2.1 Scope, stages, terminology

| # | Topic | BRD says | Other doc says | Impact |
|---|---|---|---|---|
| D1 | Number of stages | 23 stages; 22 = Import/shipment/GRN, 23 = Invoice match & payment closure (BRD §3) | XLS Read Me/Process Map/Process Flow: **22 stages**, stage 22 = **LC opening** (after validated PI); shipment, customs, GRN, invoice, payment "outside this workbook". DECK slides 1-2, 9: 22 stages, 7 heads, last head "PI & LC opening" | Decide numbering for the software: BRD 22/23 vs XLS "LC opening". Prototype's 01-21 + LC register (PRD §4.7) matches XLS. See Q1 |
| D2 | Stage 22 content | Import, shipment and GRN (BRD §4-22) | XLS Process Flow stage 22 relabels it "LC opening (after validated PI)" with sub-steps: import documentation/banking checks -> LC approval (Finance control, approver not named) -> LC opening by Import/Logistics; BRD "22 Import, shipment & GRN" appears only as a lookup label in the 21 row | S: LC register/approve/open (exists in prototype); T: LC record - **no ID pattern defined** (XLS Process Flow: "No LC record ID defined in the specification (to confirm)") |
| D3 | Process heads / phases | Not defined | XLS (phases A-G) and DECK slide 2: Requisition 01-03, Sourcing & RFQ 04-09, Evaluation 10-12, Negotiation & award 13-14, Approvals 15-17, Purchase order 18-20, PI & LC opening 21-22 | S: pipeline/flow bar grouping labels on dashboard and case detail (R: group map) |
| D4 | Decision gates | Gates drawn in flowchart: 03, 09, 12, 15, 16, 17, 20, 21 (BRD §3 diagram) plus "Gate 1-5" selection logic (§7) | XLS Decision Gates + DECK slide 2: "**8 decision gates**" list = 03, 09, 12, 15, 16, 17, 20, 21 with "Decided by" column. Two different "gate" concepts (8 workflow gates vs 5 selection gates) | R: keep two separate concepts in naming |
| D5 | ACK terminology | ACK = Marketing acknowledgment (§1); supplier "PO acknowledgment" | XLS Glossary: "ACK - Acknowledgment (Marketing's review step; **also the supplier's PO acceptance**)"; APP = Finance/ED approval | Wording only; keep "ACK" overloaded in UI labels |
| D6 | Audience / final approver | "ED / Delegated Final Approver"; title open (policy item 1) | DECK slide 1: presented to "the Honorable **Managing Director**"; DECK slide 10 and XLS also list "ED, MD or delegate" as open | R: approver title configurable (see Q3) |
| D7 | Glossary | none | XLS Read Me glossary (ACK, APP, AWB, BL, CBM, COA, CS, ETA, FX, GSM, HS, LC/TT, MOQ, OTP, SDS, SLA, TDS, UOM...) | S: help/glossary page (optional) |

### 2.2 Flow, returns and approvals

| # | Topic | BRD says | Other doc says | Impact |
|---|---|---|---|---|
| D8 | Return path from approvals | Flowchart: Marketing return -> 13 Negotiation; Finance/ED return/reject -> 13 Negotiation. Approval table §8 return column: Marketing -> "Procurement negotiation / QA"; Finance -> "Procurement for revision"; ED -> "Procurement / Finance as relevant" | XLS Decision Gates: all three return to **13 Negotiation**. DECK slide 7: "A return at any level goes back to Procurement for negotiation". XLS Approval Matrix repeats BRD §8 wording. Ambiguity: ED returning "to Finance" (§8) is not representable in the gate list | R: approval state machine; prototype restarts a returned/rejected pack at Marketing as new version (RULES C) - confirm vs "back to 13" (Q6) |
| D9 | Technical fail path | Fail -> back to 10 CS (diagram) | Same in XLS Decision Gates; XLS Process Flow adds "quote ineligible; clarification / re-sourcing" | none new |
| D10 | PO dispute path | Dispute -> 13 Negotiation (diagram); §4-20 "change-request task, with possible reapproval" | XLS Decision Gates: "Dispute -> 13 Negotiation (change request)"; XLS PO sheet: "Stop PI progression until resolution and relevant reapproval" | R: PO amendment flow |
| D11 | Who may extend a deadline | §17: "Authorized Procurement manager" (late-submission) / "Procurement approver" (no-bid). §4-09 names no role | XLS Decision Gates + Process Flow stage 09: extensions by "**Authorized Procurement manager**"; Process Map: Procurement = **A** at stage 09 | R: `rfq.extend` permission (prototype: supervisor only - consistent) |
| D12 | Stage 03 decider | "Procurement reviewer" | XLS Process Map: Procurement R/A at 03, Marketing and Store "I" (informed) | S: notification to requesters on validate/return |
| D13 | Stage 12 deciders | QA / Marketing | XLS Process Map: **both QA and Marketing are R/A** at 12; Marketing also R at 11 (sampling) | R: either role can record technical decision? (Q7) |
| D14 | Stage 22 deciders | Import / Store / QA (GRN) | XLS Process Map stage 22: Import R, **Finance A** (LC approval) | R: LC approve = Finance (matches PRD `lc.approve`) |
| D15 | Stage 21 owner(s) | Procurement / Import / Finance | XLS Process Map: Procurement R, Import R, Finance R, Supplier C | R: PI match permitted for exec, imp, fin (PRD has this) |
| D16 | RACI per stage | none | XLS Process Map: full R/A/C/I matrix 22 stages x 9 roles (Marketing, Store, Procurement, System, Supplier, QA, Finance, ED, Import) with counts | T: optional `stage_roles` config driving "Informed" notifications |
| D17 | Cancel state | "Cancel is separately authorized end state" (§3) | Repeated XLS Decision Gates; no authorizer named | S/R: cancel case/PO action + authority (Q9) |

### 2.3 Rules, thresholds, SLAs

| # | Topic | BRD says | Other doc says | Impact |
|---|---|---|---|---|
| D18 | RFQ window defaults | 3 China working days (std) / 2 (urgent), count from next working day, close 17:00 CST, reminder 1 working day before (§6) | XLS RFQ & Deadlines + DECK slide 4: identical; XLS adds "**Default supplier calendar: CN**" and a settings table labelled "configuration, not hard-coded"; example Mon->Thu 17:00 = 15:00 BD | none new (already in PRD/RULES) |
| D19 | PI match points | PI must match: buyer/seller identities, description/spec, quantities, price, totals, currency, shipping window, Incoterms place, payment terms, beneficiary bank (§9); §4-21 also lists **HS code** where available and **shipping schedule** | XLS PO,PI&LC: numbered list of **exactly 10** match points (the 10 above, **no HS code**); step text "compare on the ten match points" | R: prototype 10-point match consistent with XLS; HS code check not a match point (Q10) |
| D20 | PO content checklist | one paragraph (§9) | XLS: numbered **18** items | S: PO PDF/validation checklist (18 items) |
| D21 | Auto-dispatch conditions | 5 conditions (§9) | XLS: same 5 + "Then" 4 actions; adds "Any failed check sends the PO to a **correction queue**" (BRD §4-18 also says correction queue) | S: PO correction queue view (prototype uses exception + blocked state) |
| D22 | Scoring weights | 35/30/20/10/5, illustrative, cannot override QA reject (§7) | XLS + DECK slide 5: same; DECK shows "5 parameters"; XLS validates total=100% | none new; policy item 7 |
| D23 | Approval limits | TBD, configurable, no BDT assumed (§8) | XLS/DECK: same | R: `approval_authority` config (rules) |
| D24 | LC approver / procedure | Not detailed (BRD says "LC/TT/advance approval per policy" §10) | XLS PO,PI&LC and Process Flow: LC procedure "for OIL to confirm"; approver = "Finance control - approver per OIL policy". DECK slide 10 adds **"LC-opening procedure: detailed LC steps and the LC approver"** as a management decision | **New open item** beyond BRD's 12 (see §4) |
| D25 | Material changes list | listed inside §8 sentence | XLS Approval Matrix: numbered 8 items (Supplier, unit price, currency, ordered qty, Incoterms, payment terms, delivery commitment, significant quality deviations) | R: change-detection field list |
| D26 | Approval principles | one paragraph | XLS: numbered 8 principles (adds item 8 "limits TBD") | none |

### 2.4 Roles, fields, documents, notifications, KPIs

| # | Topic | BRD says | Other doc says | Impact |
|---|---|---|---|---|
| D27 | Open policy items | 12 (§17) | XLS Open Policy Items: same 12 + columns Decision owner / Outcome / Target date / Status (all empty, "Open"); DECK slide 10 shows 5 headline decisions (authority, RFQ deadline rules, sample testing/waivers, supplier scoring, **LC-opening procedure**) | T: `policy_items` needs owner, outcome, target_date, status (check prototype) |
| D28 | Acceptance tests | **20** (AC-01...AC-20) | XLS Acceptance Tests: **19** - **AC-18 omitted** (three-way match, "GRN accepted qty below invoiced qty"); adds "Result" column and a stage mapping (AC-03 -> 06-07, AC-13 -> 17-18, AC-15 -> 19) | Prototype/PRD follow XLS (AC-18 absent). AC-18 is out of scope until stage 23 is built |
| D29 | Notification matrix | **16** events (§11) | XLS Notifications: **14** events - omits "Shipment delayed / ETA changed" and "GRN completed / invoice mismatch"; adds a Lifecycle column and template letter mapping (Deadline approaching -> B, Deadline reached -> E, Extended -> D, Approval -> H, PO sent -> F, PO ack overdue -> G); no template for "Supplier submits" in this column although BRD has Template C | T: `notification_rules` with template key; Template C mapping missing in XLS (supplier-submit confirmation) |
| D30 | KPIs | **8** formulas: adds *On-time delivery* and *Three-way-match exception rate* (§15) | XLS KPIs: **6** formulas, adds **Baseline** and **Target** columns, all "Not supplied" | S: reports page; do not show invented numbers; baseline/target fields empty |
| D31 | Dashboard lists | operational + management lists (§15) | XLS KPIs sheet: same two lists in two columns (operational list omits late shipments/open GRNs/due invoices - 3rd-party stages) | none |
| D32 | Record entities | **16** (incl. SHP, GRN, INV, PAY) (§13) | XLS Records & IDs: **12** (no Shipment, GRN, Supplier invoice, Payment); adds column "**Created at stage**" per record (PR 01, CB 02, RFQ 04, SUP 05, QUO 08, CS 10, SMP 11, NEG 13, AWD 14, PO 18, PI 21...) | T: `created_stage` metadata; SHP/GRN/INV/PAY not needed for 01-21 |
| D33 | Exceptions | **16** scenarios (§17) | XLS Exceptions: **13** - omits goods damaged/short/failed QC, duplicate vendor invoice, advance paid but order cancelled (all post-GRN); XLS PO sheet repeats 4 PO-to-LC exceptions | R: exception catalogue limited to 01-21 for now |
| D34 | Screen map | Import & Warehouse has 4 screens: PI/Banking, Shipment Tracking, GRN/Inspection/Claims, Invoice & Payment (§19) | XLS Screen Map: "Import (in scope up to LC opening)" with **only PI / Banking Documents**; adds footer pointing integration targets to policy item 12 | S: matches prototype (PI/LC real; ship/grn/pay = stubs). BRD menu includes them |
| D35 | Template catalogue | Templates A-H (§12) | XLS Email Templates: same 8, with a code/purpose column; text identical to BRD | none |
| D36 | Role set | 11 roles in §14 (Marketing requester, Store requester/receiver, Procurement executive, supervisor, Supplier, QA, Finance, ED, Import, SysAdmin, Internal audit/MD viewer) | XLS Process Map uses 9 actor columns (no supervisor, no sysadmin, no audit; adds "System (OIL-PMS)" as an actor). PRD RBAC (exec, sup, mkt, qa, fin, ed, imp, admin, audit) has no Store role | T: `users.role`; Store role missing in prototype RBAC (see §3) |
| D37 | Supplier fields | ~45 field rows in §5 | XLS Supplier Portal Fields: same rows, numbered, with live counts by R / R-where-applicable / C | none (confirm the prototype's `config/quote_fields.php` covers all rows - verify) |
| D38 | Retention / regulatory | "OIL-defined retention", regulatory subject to law (§16) | none in XLS/DECK (PRD adds Bangladesh Bank/PCI-aligned rule from org policy; not a source-doc requirement) | NFR only (Q14) |

### 2.5 Items present in the BRD only (not carried to XLS/DECK) - for traceability
Stage 23 and the whole import/receiving/payment lifecycle (§10), three-way match, duplicate invoice, advance recovery, 8 KPI + 2 KPIs above, MFA for internal approvers and other NFRs (§16), data-quality rules, services/capital purchase branches (§2), domestic-purchase bypass of LC/TT (XLS repeats the domestic bypass in one sentence on PO,PI&LC), AC-18, shipment/ETA and GRN/invoice notifications, SHP/GRN/INV/PAY entities.

## 3. Documented requirements not implemented in the current prototype

Prototype scope per PRD §2-§4 and BG: stages 01-21 plus LC register; stages 22-23 are ERP hand-off stubs (`ship`, `grn`, `pay`) and PR creation is an ERP link (`pr`). "Not implemented" below = not listed in PRD/RULES/BG as implemented; items marked (verify) should be checked against the prototype HTML.

**A. Whole stages / modules (BRD only)**
1. Stage 22 shipment tracking (milestones, ETA, partial shipments, packing list, commercial invoice, BL/AWB, customs, insurance) - BRD §4-22, §10; entity SHP.
2. GRN, QA receiving inspection, quarantine, short/over/damaged handling, claims, supplier debit/credit notes - BRD §4-22, §10; entity GRN.
3. Stage 23 supplier invoice entry, three-way match (PO/GRN/invoice), duplicate-invoice block, advance adjustment, payment authorization, bank reconciliation, PO closure - BRD §4-23; entities INV, PAY; KPIs three-way-match exception rate, payment aging; AC-18.
4. Exceptions tied to the above (damaged/short/failed QC, duplicate invoice, advance paid then cancelled, demurrage, FX change, missing shipping papers) - BRD §10, §17.
5. PR create/edit with duplication warning, stock/pending-PO visibility, new-item proposal, Store/Marketing requester roles - BRD §4-01, §14 (PRD: PR is ERP; no requester role in RBAC).
6. Domestic-purchase, repeat-material test-waiver, single-source/emergency, services/capital branches - BRD §2 (only partially: MOQ waiver and award justification exist; formal branch selection not listed).

**B. Controls and cross-cutting features**
7. MFA for internal approvers; session protections; backup/recovery - BRD §16 (not in PRD).
8. Optional supplier email OTP on the secure link; rate limiting of token endpoint (PRD mentions hashing/expiry; OTP not listed) - BRD §4-06.
9. Antivirus/quarantine scanning of uploads, allowed file types and size limits, download authorization, quarantine log - BRD §5 file controls (not in PRD; verify).
10. Approval overdue escalation, PO-acknowledgment overdue reminder (Template G) and escalation, PO ack SLA - BRD §11, §17 policy item 8 (notification rules exist; overdue automation not listed - verify).
11. Delegation matrix with alternate approvers and threshold-driven routing - BRD §8 (PRD: "policy & approval authority" admin page, thresholds not enforced since none supplied).
12. Cancel as an authorized end state for case/RFQ/PO and "PO cancellation / amendment authority" - BRD §3, policy item 8.
13. Document library (searchable) and document integrity snapshots/checksum for PO PDF; versioned PO PDF revisions (PRD lists a checksum for the award pack only) - BRD §4-18, §16.
14. FX rate master with source/date, UOM conversion master, Incoterms and port masters - BRD §16 (PRD "materials & masters" - verify coverage); controlled FX shown in CS (AC-09).
15. Supplier prequalification, sanction/tax-registration checks, supplier performance history feeding the score (history is a manual input in the engine - verify), bid-opening/confidentiality policy - BRD §4-05, §7, policy items 5 and 7.
16. Quotation revision after submission with revision history/receipt printout; supplier language/timezone labels; mobile layout (portal has 6 tabs; printable receipt and multi-language labels not listed) - BRD §5.
17. Missing KPIs: RFQ response rate (maybe via reports - verify), on-time delivery, sample first-pass/acceptance rate, rejection/claim rate, payment aging, import delays, negotiated savings like-for-like display - BRD §15.
18. Integrations: ERP/inventory, AP/accounting, warehouse, bank/import register, BI, document storage - BRD §16 (all TBD). Email provider is simulated by the `mails` archive.
19. Record retention/archiving/deletion schedule - BRD §16, policy item 12.
20. Per-entity audit fields `created_by/at, updated_by/at, status, revision` and "physical overwrite never" for all entities - BRD §13 (case state is a JSON aggregate; per-entity revisions for quotation/PO exist; others verify).

**C. Documented but intentionally deferred (open policy)**: approval thresholds, sample-waiver criteria, tolerance/three-way-match values, standard PO clauses, SLA values - cannot be built until Q-list answered.

## 4. Ambiguities and questions for the owner

Scope / numbering
1. Is the software's stage model the BRD's 23 stages or the workbook/deck's 22 (ending at LC opening)? Do you want stages 22-23 built later or kept as ERP stubs? (D1, D2)
2. The prototype is organised by a monthly import "Purchase Case" (`IC-YYYY-MM`) with Confirmed Book Qty (PRD §1, §4.3). The BRD works per PR -> consolidation batch CB -> RFQ -> PO and defines no case or booking-freeze concept. Confirm the case/booking model replaces PR-level flow, and how PR/CB IDs map to it.

Approval and authority
3. Who is the final approver: ED, MD, or delegate (policy item 1; DECK slide 1 addressed to MD)? Value thresholds, alternate approvers, delegation rules? Until then, one fixed 3-step chain.
4. Does Finance or Marketing approval vary by amount/material (BRD says "where approval thresholds apply")?
5. Who authorises Cancel (case, RFQ, PO)?
6. On return/reject: BRD/XLS/DECK say the case goes back to **13 Negotiation** (ED return "to Procurement / Finance as relevant"). The prototype restarts the pack at Marketing as a new version, and disallows Reject at Marketing. Which is intended? Can ED return to Finance without going through Marketing again (AC-11, AC-12)?
7. Technical acceptance (stage 12): may QA alone decide, or must Marketing also concur (XLS Process Map has both R/A)? Who authorises a Conditional Pass deviation and a sample waiver?

RFQ / deadline
8. Is the invitation day excluded in every case (policy item 2)? Closing hour fixed at 17:00 local for all countries (the prototype also has Korea) and calendar per supplier or per RFQ (policy item 3)? Is the reminder one *supplier-country* working day before close (BRD §11) or China only (§6)?
9. Late-submission and extension approval: only "Procurement manager" (XLS) - is that the Supervisor role? Is there a limit on number of extensions? Re-tender rights (policy item 6)?

PI / LC / import
10. XLS defines 10 PI match points without HS code; BRD §4-21 also mentions HS code and shipping schedule. Should HS code mismatch be a blocking, warning or informational check? What PI tolerance applies (price/qty %)?
11. LC opening procedure, LC approver and LC record ID format are undefined (XLS, DECK slide 10). Also TT/advance payment path - needs its own approval/record? Domestic purchases: should the system provide a non-LC path now?
12. Who maintains Bangladesh Bank / import permit / customs document checklist and per-method mandatory documents (policy item 11)?

Scoring and policy values
13. Are the illustrative score weights (35/30/20/10/5) accepted, and how are Delivery/Terms/History sub-scores derived (all manual in the prototype)? Tie-break logic (policy item 7)? Is the index only advisory?
14. Landed-cost standard assumptions: freight, insurance, duty, bank charges - supplier-quoted vs OIL standard rates; FX source and rate date (policy item 9). Retention period and legal hold rules (policy item 12).
15. Acknowledgment SLA, PO amendment/cancellation authority, standard clauses for the PO PDF (policy items 8, 11).
16. Tolerances for quantity (+/- %) on PO, three-way match, advance policy (policy item 10) - needed before stage 23.

Roles and data
17. Add a Store requester/receiver role and a Marketing requester role (BRD §14) - or does PR stay entirely in the ERP? Who receives "Informed" notices at each stage (XLS RACI)?
18. Who is the "independent verifier" pair for bank-detail change? The prototype uses Finance then Supervisor (two-person); BRD says "independent verification and dual control" without naming roles.
19. Are KPI baselines/targets available? XLS shows "Not supplied" - the system must show no invented percentages until filled.
20. XLS omits AC-18 and the DECK/XLS count 22 stages; confirm AC-18 stays deferred and which acceptance list (19 vs 20) is the UAT baseline.
21. Supplier-submit confirmation (Template C) has no row in the XLS notification template column - confirm it is sent automatically as BRD §11 states.
22. Companion workbook filename in DECK slide 10 differs from the supplied file - confirm this is the same workbook and which one is the current master.

Compliance flags (org policy)
- Supplier contact data, bank details, and PI beneficiary data are sensitive - confirm encryption-at-rest, masking and role-restricted export (BRD §5, §9, §16 do not specify; PRD §8 does).
- LC/TT/FX records fall under Bangladesh Bank import-payment rules; retention and audit access need Compliance sign-off before go-live.
