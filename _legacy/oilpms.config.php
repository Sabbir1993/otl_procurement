<?php

// Sidebar-menu-wise definition. Single source for: routes/menu/*.php, Blade views, the JS router and the JS bundle order.
// menu.<group>.pages = page key (prototype) => path ('' = root) + title; menu.<group>.detail = key => title (URL /{key}/{arg?}).
$menu = [
    'cc' => [
        'title' => 'Command Center',
        'pages' => [
            'home' => ['path' => '', 'title' => 'Command Center'],
        ],
        'detail' => [],
    ],
    'cs' => [
        'title' => 'Purchase Cases',
        'pages' => [
            'cases' => ['path' => 'cases', 'title' => 'Purchase Cases'],
        ],
        'detail' => ['case' => 'Purchase Case'],
    ],
    'rq' => [
        'title' => 'Requisitions',
        'pages' => [
            'pr' => ['path' => 'requisitions/pr', 'title' => 'Create / Edit PR (ERP)'],
            'rq.board' => ['path' => 'requisitions/board', 'title' => 'Requisition Board'],
            'rq.booking' => ['path' => 'requisitions/booking', 'title' => 'Confirmed Book Qty'],
        ],
        'detail' => [],
    ],
    'src' => [
        'title' => 'Sourcing / RFQ',
        'pages' => [
            'src.supp' => ['path' => 'sourcing/suppliers', 'title' => 'Supplier Shortlist'],
            'src.rfq' => ['path' => 'sourcing/rfqs', 'title' => 'RFQs'],
            'src.track' => ['path' => 'sourcing/tracking', 'title' => 'Link & Deadline Admin'],
            'src.quotes' => ['path' => 'sourcing/quotes', 'title' => 'Submitted Quotations'],
            'src.cs' => ['path' => 'sourcing/comparative-statement', 'title' => 'Comparative Statement'],
            'src.samples' => ['path' => 'sourcing/samples', 'title' => 'Samples & QA'],
        ],
        'detail' => ['rfq' => 'RFQ', 'quote' => 'Quotation'],
    ],
    'aw' => [
        'title' => 'Negotiation & Awards',
        'pages' => [
            'aw.neg' => ['path' => 'awards/negotiation', 'title' => 'Negotiation History'],
            'aw.rec' => ['path' => 'awards/recommendation', 'title' => 'Final Recommendation'],
            'aw.appr' => ['path' => 'awards/approvals', 'title' => 'Approval Queue'],
        ],
        'detail' => ['award' => 'Award Package'],
    ],
    'po' => [
        'title' => 'Purchase Orders',
        'pages' => [
            'po.reg' => ['path' => 'po/register', 'title' => 'PO Register'],
            'po.ack' => ['path' => 'po/acknowledgments', 'title' => 'Acknowledgments'],
            'po.disp' => ['path' => 'po/disputes', 'title' => 'Amendments & Disputes'],
        ],
        'detail' => ['po' => 'Purchase Order'],
    ],
    'imp' => [
        'title' => 'Import & Warehouse',
        'pages' => [
            'imp.pi' => ['path' => 'import/pi', 'title' => 'PI & Banking Documents'],
            'imp.lc' => ['path' => 'import/lc', 'title' => 'LC Register'],
            'ship' => ['path' => 'erp/shipment', 'title' => 'Shipment Tracking (ERP)'],
            'grn' => ['path' => 'erp/grn', 'title' => 'GRN / Inspection (ERP)'],
            'pay' => ['path' => 'erp/payment', 'title' => 'Invoice & Payment (ERP)'],
        ],
        'detail' => ['pi' => 'PI & LC'],
    ],
    'sd' => [
        'title' => 'Suppliers',
        'pages' => [
            'sup.dir' => ['path' => 'suppliers', 'title' => 'Supplier Directory'],
            'portal' => ['path' => 'portal', 'title' => 'Supplier Portal Preview'],
        ],
        'detail' => ['sup' => 'Supplier'],
    ],
    'an' => [
        'title' => 'Reports',
        'pages' => [
            'an.perf' => ['path' => 'reports/performance', 'title' => 'Savings & Performance'],
            'an.pipe' => ['path' => 'reports/pipeline', 'title' => 'Pipeline & Cycle Time'],
        ],
        'detail' => [],
    ],
    'ctl' => [
        'title' => 'Control',
        'pages' => [
            'exc' => ['path' => 'control/exceptions', 'title' => 'Exceptions'],
            'notif' => ['path' => 'control/notifications', 'title' => 'Notifications'],
            'audit' => ['path' => 'control/audit', 'title' => 'Audit Trail'],
        ],
        'detail' => [],
    ],
    'adm' => [
        'title' => 'Administration',
        'pages' => [
            'ad.roles' => ['path' => 'admin/roles', 'title' => 'Users & Roles'],
            'ad.master' => ['path' => 'admin/masters', 'title' => 'Materials & Masters'],
            'ad.cal' => ['path' => 'admin/calendars', 'title' => 'Working-Day Calendars'],
            'ad.tpl' => ['path' => 'admin/templates', 'title' => 'Email Templates'],
            'ad.policy' => ['path' => 'admin/policy', 'title' => 'Policy & Approval Authority'],
            'ad.tests' => ['path' => 'admin/tests', 'title' => 'Acceptance Tests'],
            'ad.demo' => ['path' => 'admin/demo', 'title' => 'Demo Utilities'],
        ],
        'detail' => [],
    ],
];

// JS bundle load order (served as one script by AssetController). Core first, menu modules in original order, init last.
$js = [
    '00-foundation.js',
    '01-data.js',
    '02-ui-engine.js',
    '03-router.js',
    '04-widgets-logic.js',
    'menu/cc/command-center.js',
    'menu/cs/cases.js',
    'menu/rq/requisition-board.js',
    'menu/rq/booking.js',
    'menu/src/supplier-shortlist.js',
    'menu/sd/supplier-directory.js',
    'menu/shared/erp-handoff.js',
    'menu/src/rfqs.js',
    'menu/src/link-deadline.js',
    'menu/src/quotations.js',
    'menu/src/samples-qa.js',
    'menu/sd/supplier-portal.js',
    'menu/src/comparative-statement.js',
    'menu/aw/negotiation.js',
    'menu/aw/recommendation.js',
    'menu/aw/approvals.js',
    'menu/po/po-register.js',
    'menu/po/acknowledgments-disputes.js',
    'menu/imp/pi-banking.js',
    'menu/imp/lc-register.js',
    'menu/an/savings-performance.js',
    'menu/an/pipeline-cycle-time.js',
    'menu/ctl/exceptions.js',
    'menu/ctl/notifications.js',
    'menu/ctl/audit-trail.js',
    'menu/adm/administration.js',
    'menu/adm/demo-utilities.js',
    '99-init.js',
];

return [
    'menu' => $menu,
    'pages' => collect($menu)->flatMap(fn ($m) => $m['pages'])->all(),
    'detail' => collect($menu)->flatMap(fn ($m) => $m['detail'])->all(),
    'js' => $js,
];
