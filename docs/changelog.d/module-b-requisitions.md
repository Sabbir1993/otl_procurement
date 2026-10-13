# Module B - requisitions & ERP hand-off stubs

## Added / changed
- `app/Services/RequisitionService.php`: all rules ported 1:1 from `menu/rq/*.js`, `freezeCase`, `bookingSerial`. Contract methods `freeze(CaseBook, ?by)` and `confirmBooking(CaseBook, docRef, ?by, ?ts, array $extra = [])` (mutate `$c->s` + log, caller saves). Action wrappers (`accept, revert, delete, bulkAccept, bulkReject, saveLine, freezeAction, editBooking, addBooking, deleteBooking, importCsv, report, confirm`) do `Perm::need` + stage rules + `CaseBook::tx`.
- Read API `lines()/stats()/demandQty()`: controllers and views never touch `$c->s['demand']` directly (coordinator request: demand storage may move to the ERP tables later).
- `RequisitionController`, `ErpHandoffController` (EXTMOD + hand-off queue computed from real cases: PR lines of working case, LC-opened POs, accepted POs, all POs).
- Views: `requisitions/board`, `requisitions/booking`, `requisitions/erp-base` (+ `requisitions/pr`, `import/shipment|grn|payment` as one-line wrappers).
- Routes (`routes/actions/requisitions.php`): `rq.line.save|accept|revert|delete`, `rq.bulk.accept|reject`, `rq.freeze`, `rq.booking.report` (GET CSV download), `rq.bk.add|save|delete|csv|confirm`.
- `rq.board?new=1` opens the add-line dialog (`data-open-on-new`).
- Confirm: signed document file (pdf/jpg/png, 5 MB) AND reference are mandatory; file is stored on the private `local` disk under `booking-docs/<case>/` (random name), original name kept in `booking.doc`, path in `booking.docPath`. Serial `IMPBOK/<yyyy of case month>/<MON>/<nn>` via the `IMPBOK-<MON>` sequence. Audit + notification (role exec).

## Tests
`tests/Feature/RequisitionTest.php` (10 tests, 177 assertions, passing): pages for every role, accept/revert/delete, bulk accept/reject (reason from settings), add/edit, freeze rules, post-freeze lock, booking pct/qty maths, CSV, confirm rules/serial/audit/notification, service contracts, segregation of duties.

## Known gaps / notes
- Bulk selection only covers rows on the visible dtable page (checkboxes are per page).
- Single-line reject is only via the bulk dialog (as in the prototype).
- Uploaded signed documents are not virus-scanned and have no retention/PII policy yet (privacy flag: may contain signatory personal data; keep off public disk; Bangladesh Bank/PCI evidence retention to be decided).
- Existing failure outside this module: `SmokeTest::test_every_page_renders` errors on module D's route `cs.csv` (needs `{id}`; add to the smoke sample map or skip list).
