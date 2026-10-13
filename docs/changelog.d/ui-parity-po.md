# UI parity audit - PO / awards / PI / LC pages

Audited aw.neg, aw.rec, aw.appr, award.show, po.reg, po.show, po.ack, po.disp, imp.pi, pi.show, imp.lc against the prototype. All were already at parity (columns, KPIs, alerts, filters, dialogs, perms) except:

- po.disp: "Raise change request" header button now opens the prototype dialog (field + reason, posts to appr.change for the working case) instead of linking to the approval queue.
- pi.show: "Record supplier PI / PI revision" header button now shows (disabled) when the PO is not accepted or LC is open, like the prototype, instead of disappearing.

Intentional deviation kept: aw.neg "Change" is red when a revised price is higher (prototype is always green).
Not fixed: po.disp related-exception "Resolve" links to the Exceptions page rather than an inline dialog.
Tests: tests/Feature/UiParityPoTest.php.
