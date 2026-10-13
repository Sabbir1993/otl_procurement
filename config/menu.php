<?php

// Sidebar definition (from the prototype NAV). route = route name in routes/web.php; also = extra route names that keep the item active.
return [
    ['key' => 'cc', 'title' => 'Command Center', 'icon' => 'home', 'items' => [
        ['route' => 'home', 'label' => 'Command Center'],
    ]],
    ['key' => 'cs', 'title' => 'Purchase Cases', 'icon' => 'layers', 'items' => [
        ['route' => 'cases', 'label' => 'All Cases', 'also' => ['case.show']],
    ]],
    ['key' => 'rq', 'title' => 'Requisitions', 'icon' => 'clip', 'items' => [
        ['route' => 'pr', 'label' => 'Create / Edit PR (ERP)'],
        ['route' => 'rq.board', 'label' => 'Requisition Board'],
        ['route' => 'rq.booking', 'label' => 'Confirmed Book Qty'],
    ]],
    ['key' => 'src', 'title' => 'Sourcing / RFQ', 'icon' => 'send', 'items' => [
        ['route' => 'src.supp', 'label' => 'Supplier Shortlist'],
        ['route' => 'src.rfq', 'label' => 'RFQs', 'also' => ['rfq.show']],
        ['route' => 'src.track', 'label' => 'Link & Deadline Admin'],
        ['route' => 'src.quotes', 'label' => 'Submitted Quotations', 'also' => ['quote.show']],
        ['route' => 'src.cs', 'label' => 'Comparative Statement'],
        ['route' => 'src.samples', 'label' => 'Samples & QA'],
    ]],
    ['key' => 'aw', 'title' => 'Negotiation & Awards', 'icon' => 'flag', 'items' => [
        ['route' => 'aw.neg', 'label' => 'Negotiation History'],
        ['route' => 'aw.rec', 'label' => 'Final Recommendation'],
        ['route' => 'aw.appr', 'label' => 'Approval Queue', 'also' => ['award.show']],
    ]],
    ['key' => 'po', 'title' => 'Purchase Orders', 'icon' => 'doc', 'items' => [
        ['route' => 'po.reg', 'label' => 'PO Register', 'also' => ['po.show']],
        ['route' => 'po.ack', 'label' => 'Acknowledgments'],
        ['route' => 'po.disp', 'label' => 'Amendments & Disputes'],
    ]],
    ['key' => 'imp', 'title' => 'Import & Warehouse', 'icon' => 'bank', 'items' => [
        ['route' => 'imp.pi', 'label' => 'PI & Banking Documents', 'also' => ['pi.show']],
        ['route' => 'imp.lc', 'label' => 'LC Register'],
        ['route' => 'ship', 'label' => 'Shipment Tracking (ERP)'],
        ['route' => 'grn', 'label' => 'GRN / Inspection (ERP)'],
        ['route' => 'pay', 'label' => 'Invoice & Payment (ERP)'],
    ]],
    ['key' => 'sd', 'title' => 'Suppliers', 'icon' => 'users', 'items' => [
        ['route' => 'sup.dir', 'label' => 'Supplier Directory', 'also' => ['sup.show']],
        ['route' => 'portal', 'label' => 'Supplier Portal Preview'],
    ]],
    ['key' => 'an', 'title' => 'Reports', 'icon' => 'chart', 'items' => [
        ['route' => 'an.perf', 'label' => 'Savings & Performance'],
        ['route' => 'an.pipe', 'label' => 'Pipeline & Cycle Time'],
    ]],
    ['key' => 'ctl', 'title' => 'Control', 'icon' => 'alert', 'items' => [
        ['route' => 'exc', 'label' => 'Exceptions'],
        ['route' => 'notif', 'label' => 'Notifications'],
        ['route' => 'audit', 'label' => 'Audit Trail'],
    ]],
    ['key' => 'adm', 'title' => 'Administration', 'icon' => 'cfg', 'items' => [
        ['route' => 'ad.roles', 'label' => 'Users & Roles'],
        ['route' => 'ad.master', 'label' => 'Materials & Masters'],
        ['route' => 'ad.cal', 'label' => 'Working-Day Calendars'],
        ['route' => 'ad.tpl', 'label' => 'Email Templates'],
        ['route' => 'ad.policy', 'label' => 'Policy & Approval Authority'],
        ['route' => 'ad.tests', 'label' => 'Acceptance Tests'],
        ['route' => 'ad.demo', 'label' => 'Demo Utilities'],
    ]],
];
