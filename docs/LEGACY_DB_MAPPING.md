# Legacy database alignment

Source: `_src/otl_db1.sql` (MySQL 8, schema `otl_erp_uat_v2`, tables: `brand, customer, export_ci, export_pi, marketing_person, mktp_target, product, product_req_from_mktp, product_req_from_mktp_dtl, users`).
Rule from the owner: **if a table we create is relevant to a legacy table, use the legacy table pattern.** This file is the mapping + the conventions every new table must follow. Status column: ✅ matches today · 🔁 to be realigned (Phase L, see MODULE_PLAN.md) · ➕ new, follows conventions.

## 1. Legacy conventions (observed) -> our rules
| Legacy pattern | Our rule for every relational table |
|---|---|
| Singular snake_case table names (`brand`, `customer`, `product`, `export_pi`), module prefix for module-owned tables (`export_*`, `exp_*` lookups, `mktp_*`) | Singular names. Shared masters keep the legacy name unprefixed (`product`, `customer`, `brand`, `marketing_person`, `users`). Import-purchase tables are prefixed **`imp_`** (`imp_supplier`, `imp_purchase_case` ...). Lookups: `imp_<thing>_status`. |
| `id` INT AUTO_INCREMENT primary key (never string PKs) | `$table->increments('id')` (int unsigned). Human codes (`SUP-00001`, `IC-2026-10`, `PO-2026-00008`) live in a separate unique `*_code` / `*_no` column. |
| Foreign keys named `<entity>_id`, status via `<entity>_status_id` lookup, flags as `tinyint` (`active`, `status`, `is_*`) | Same. Booleans = `tinyInteger` 0/1. |
| Audit columns `created_at, created_by, updated_at, updated_by` (+ `deleted_at, deleted_by` soft delete on transactional tables) | **All** new tables get the six columns; Eloquent `SoftDeletes` + a `Userstamps` trait fills `*_by` with the auth user id. |
| `datetime` / `timestamp` columns, `double(15,4)` money/qty, `varchar` with utf8mb4 | `datetime` (not epoch ms) for relational tables; money/qty `decimal(15,4)`; text utf8. SQLite stores them dynamically - keep the declared types so a later MySQL move is a pure DDL copy. |
| `is_bulk` flag marks bulk-loaded rows | Not needed (omit). |
| Free-text snapshots kept in text columns (`terms_and_condition`, `note`) | Same. |
| Money/exchange: `bdt_rate`, `total_*_value` | Reuse the names where we store BDT conversion later. |

SQLite note: engine stays SQLite (project constraint); DDL we write must remain MySQL-portable (no SQLite-only types, no JSON-only logic in queries).

## 2. Table-by-table mapping
| Legacy table | Relevance | Our table now | Target (Phase L) |
|---|---|---|---|
| `product` (id, `article` unique, `product_name`, `product_meta_name`, `composition_id`, `product_type_id`, `unit_id`, `hs_code`, `gsm`, `width_*`, `purchase`/`sell`/`produce` scope flags, `active`, audit cols) | **Materials are raw-material products** (`purchase = 1`) | 🔁 `materials` (string id `P-1001`, name, meta, comp, uom, type) | Use `product` itself: seed the 8 prototype materials as rows with `purchase=1`, `article` = prototype code (`P-1001`...), `product_name`, `product_meta_name`, `hs_code`, `unit_id`, `composition_id`, `product_type_id`. The case aggregate keeps referencing the **article** string, so no JSON change. Lookups `composition`, `unit`, `product_type` are referenced by FKs but their DDL is not in the dump - create minimal tables (`id`, `name`/`unit_name`) as a stand-in and replace when the real DDL is supplied. |
| `users` (name, phone, email, `status` tinyint, `force_password`, `password_history`, otp / trusted-device columns, `department_id`, `designation_id`, `last_login_*`, audit cols) | Same login table | ✅ `users` (Laravel default + `role`, `status` string) -> 🔁 | Add the legacy columns we use (phone, department_id, designation_id, force_password, password_changed_at, password_history, last_login_at/ip, otp_*, trusted_device_*, created_by/updated_by); `status` becomes tinyint(1) (1 = active). Role: legacy role/permission tables are **not in the dump** - keep our `role` text column (or map to the ERP role tables once supplied). Adopt force-password-change + OTP + password history (security-first). |
| `product_req_from_mktp` (header: `marketing_person_id`, date, `projection_year`, `projection_month`) + `product_req_from_mktp_dtl` (`customer_id`, `brand_id`, `product_id`, `unit_id`, `req_qty`, `req_qty_mtr`, `req_qty_yrd`, `order_exp_date`, `export_pi_id`, `mktp_target_id`, soft delete) | **This is Marketing's PR** = our demand/requisition lines (prototype "Create/Edit PR (ERP)" is exactly this module) | ➕ `purchase_cases.state.demand[]` (JSON): {id, buyer, customer, pid, qty, mkt, need, status, reason, remark} | Demand lines are **read from / written to the legacy tables** (buyer = `brand`, customer = `customer`, mkt = `marketing_person`, pid = `product_id`). Procurement's own decisions (accepted / rejected / pending, reason, remark, case) are new facts -> new table `imp_requisition_line` (`id`, `product_req_from_mktp_dtl_id` FK, `imp_purchase_case_id`, `status_id`, `reject_reason_id`, `remark`, audit cols). Legacy tables are never altered. |
| `customer` (customer_name, short name, address, `customer_type_id`, email, division/zone ids, bank_id, tax numbers...) | Garment customer of the demand line | Names as strings in demand JSON | Use `customer` rows (seed the 4 demo customers with the NOT NULL legacy columns filled with placeholders like `[CUSTOMER-ADDRESS]`). |
| `brand` (brand_name unique) | "Buyer" in the prototype = brand | Names as strings | Use `brand` rows (Nordic Apparel Group ...). |
| `marketing_person` (person_name, nick/short name, employee_id, designation_id, email, status) | "Marketing" requester / approver | Names as strings (`MKTS`) | Use `marketing_person` rows; marketing approvers (role `mkt`) link to a marketing_person via `users.email`. |
| `export_pi`, `export_ci`, `mktp_target` | Export-sales module (customer PI/CI, sales targets). Referenced from requisition detail (`export_pi_id`, `mktp_target_id`) | not used | Reference only: keep `export_pi_id` / `mktp_target_id` readable on the requisition line (show "linked order PI"), no writes. Our supplier "PI" (import) is unrelated - name ours `imp_supplier_pi` to avoid confusion. |

## 3. New (import-purchase) tables - naming under the legacy pattern
Everything the legacy dump does not cover is new and follows section 1. Final names (Phase L renames the current ones):
| Today | Becomes | Notes |
|---|---|---|
| `suppliers` | `imp_supplier` (`id` int, `supplier_code` unique `SUP-00001`, name, short tag, `country_code`, `calendar_id`, contact fields, `email_verified` tinyint, `status` tinyint, `terms` json) | Ask owner whether the ERP already has a supplier/vendor master (not in the dump) - if so, reuse it. |
| `supplier_products` | `imp_supplier_product` (`supplier_id`, `product_id`, `supplier_article`, `status`) | |
| `calendars` | `imp_calendar` (+ `imp_calendar_holiday` rows instead of JSON, `year_verified`) | |
| `purchase_cases` | `imp_purchase_case` (`id` int, `case_code` `IC-2026-10`, name, month, status_id, audit cols) + child tables below | JSON `state` is promoted table by table (see plan Phase P). |
| (JSON) rfq / invitations / quotes / CS / approvals / POs / PI / LC | `imp_rfq`, `imp_rfq_invitation`, `imp_quotation`, `imp_quotation_line`, `imp_cs_allocation`, `imp_award`, `imp_award_step`, `imp_purchase_order`, `imp_purchase_order_line`, `imp_supplier_pi`, `imp_lc` ... | each with audit + soft-delete columns, `*_status_id` lookups. |
| `exceptions`, `audit_logs`, `app_notifications`, `mails`, `sequences`, `settings`, `email_templates`, `policy_items`, `acceptance_tests`, `saved_views` | `imp_exception`, `imp_audit_log`, `imp_notification`, `imp_mail`, `imp_sequence`, `imp_setting`, `imp_email_template`, `imp_policy_item`, `imp_acceptance_test`, `imp_saved_view` | int ids; audit cols; datetimes instead of `*_ms`. |

## 4. How the swap stays safe
- All persistence is behind `Masters`, `CaseBook` and the module services - controllers/views never query tables directly (modules B and F were told to keep it that way). Phase L changes the **repositories**, not the pages.
- The case aggregate references masters by stable codes (`article`, supplier code, case code), so moving masters to `product` / `imp_supplier` does not rewrite JSON.
- Seed data stays the prototype demo set; legacy-shaped seed rows use placeholders for any sensitive/mandatory legacy column.

## 5. Needed from the owner (not in `otl_db1.sql`)
`unit`, `product_type`, `composition`, `product_req_from_mktp` helper lookups; roles/permissions tables; `department`, `designation`; bank / bank_account; any existing **supplier/vendor, purchase order, LC, GRN, shipment, invoice/payment** tables of the legacy ERP (the "ERP hand-off" screens `ship`, `grn`, `pay` should read these). Send those DDLs and the mapping above is finalised without guessing.
