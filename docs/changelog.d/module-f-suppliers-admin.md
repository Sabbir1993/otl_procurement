## Module F - suppliers & administration

**Added**
- `app/Services/SupplierService.php` (save, verify, masking, reads `all/find/products`), `app/Services/AdminService.php` (users, materials, reasons, PO defaults, calendars, templates, policy, authority, acceptance results, failMail, export, reset; reads `users/materials`). All user/material/supplier persistence sits behind these two services (controllers/views do not query those models) so storage can be aligned with the legacy ERP tables later.
- `app/Domain/AdminCatalog.php` (ACS + STAGES catalogues from the prototype).
- `routes/actions/suppliers-admin.php`; dynamic views `suppliers/index|show|_form`, `admin/roles|masters|calendars|templates|policy|tests|demo`; `tests/Feature/SuppliersAdminTest.php` (12 tests).
- Controllers `SupplierController`, `AdminController` rewritten (thin).

**Routes added (POST)**: `sup.store`, `sup.verify`, `ad.user.save`, `ad.user.pw`, `ad.mat.save`, `ad.reason.add`, `ad.reason.del`, `ad.settings.save`, `ad.cal.on|off|day|year`, `ad.tpl.save`, `ad.policy.save`, `ad.auth.save`, `ad.test.set`, `ad.demo.mail|export|reset`.

**Rules / decisions**
- Supplier edit (`sup.edit`): name/country change -> Unverified + exception "Supplier identity changed"; email/contact/phone change -> Unverified + "Supplier contact changed". Verification needs `po.verify` (as in the prototype JS; Procurement Supervisor), requires a callback reference, resolves both exception types.
- Phone/address/full email shown only with `sup.edit`; masked otherwise. Lists always mask email.
- Users: temp password = random, stored hashed only, flashed once (`temp_password` session flash), never audited/logged. Cannot demote/deactivate yourself or the last active admin. Duplicate e-mail rejected. Extra: "Reset password".
- Calendars: approve (demo, adds 2026; KR gets prototype holidays), revoke, add holiday/make-up day (its year becomes verified; duplicates rejected), extra verify/un-verify year. Approve resolves "Calendar unavailable" exceptions.
- Templates: edit warns (toast) when placeholders were removed.
- All admin actions need `admin.cfg`; admin pages redirect with an SoD toast for roles without the `adm` menu.
- Export: all tables as JSON; users limited to id/name/email/role/status; keys matching tok/token/secret/passw/hash/remember redacted in case state; link tokens removed from mail bodies. NOTE: supplier phone/address are exported as stored (admin only, audited).
- Reset: `admin.cfg` + `config('oilpms.demo_login')` + typing RESET; re-runs `DatabaseSeeder` (demo users reset to demo credentials; custom users are kept).

**Frozen-file fix**: `resources/views/errors/404.blade.php` had `@endsection` glued to text (left an output buffer open, made 404 tests risky); fixed.

**Known gaps**: `stageLoad` form is visible but disabled (integrator). Temp password has no forced-change-on-first-login (no column yet). Supplier-product mapping (add/remove materials per supplier) not in the prototype, not built. `SmokeTest::test_every_page_renders` currently errors on module D's `cs.csv` route (needs a sample id) - not module F.
