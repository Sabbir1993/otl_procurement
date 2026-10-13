<?php

namespace App\Domain;

/** Static catalogues for the Administration module (prototype ACS / STAGES arrays). */
class AdminCatalog
{
    /** [code, given/when, expected, BRD stage, route key] */
    public const ACS = array (
  0 => 
  array (
    0 => 'AC-01',
    1 => 'Marketing submits complete PR',
    2 => 'Sequential PR ID created; Procurement notified',
    3 => '01',
    4 => 'pr',
  ),
  1 => 
  array (
    0 => 'AC-02',
    1 => 'Two compatible PRs consolidated',
    2 => 'Quantities trace back to originals; no double allocation',
    3 => '02',
    4 => 'rq.board',
  ),
  2 => 
  array (
    0 => 'AC-03',
    1 => 'RFQ published for three China vendors',
    2 => 'Three distinct, vendor-bound URLs sent and logged',
    3 => '06–07',
    4 => 'src.track',
  ),
  3 => 
  array (
    0 => 'AC-04',
    1 => 'Quote opened by vendor A',
    2 => 'Vendor A cannot access vendor B’s response',
    3 => '08',
    4 => 'portal',
  ),
  4 => 
  array (
    0 => 'AC-05',
    1 => 'RFQ crosses official China holiday',
    2 => 'Deadline adjusts according to approved calendar',
    3 => '09',
    4 => 'ad.cal',
  ),
  5 => 
  array (
    0 => 'AC-06',
    1 => 'RFQ reaches cutoff',
    2 => 'New final submissions rejected server-side; old submissions preserved',
    3 => '09',
    4 => 'portal',
  ),
  6 => 
  array (
    0 => 'AC-07',
    1 => 'Procurement approves deadline extension',
    2 => 'Prior deadline retained, new one communicated, audit entry created',
    3 => '09',
    4 => 'src.track',
  ),
  7 => 
  array (
    0 => 'AC-08',
    1 => 'Supplier leaves required GSM empty',
    2 => 'Final submission blocked with clear validation error',
    3 => '08',
    4 => 'portal',
  ),
  8 => 
  array (
    0 => 'AC-09',
    1 => 'Quote in alternate currency / UOM',
    2 => 'Comparison converts on controlled source / date and shows assumptions',
    3 => '10',
    4 => 'src.cs',
  ),
  9 => 
  array (
    0 => 'AC-10',
    1 => 'QA marks sample failed',
    2 => 'Supplier cannot be awarded without a documented authorised exception',
    3 => '12',
    4 => 'src.samples',
  ),
  10 => 
  array (
    0 => 'AC-11',
    1 => 'Marketing returns evaluation',
    2 => 'Finance stage remains unavailable until resubmission and ACK',
    3 => '15',
    4 => 'aw.appr',
  ),
  11 => 
  array (
    0 => 'AC-12',
    1 => 'Finance returns on cash / terms issue',
    2 => 'ED approval not available until Finance reapproves',
    3 => '16',
    4 => 'aw.appr',
  ),
  12 => 
  array (
    0 => 'AC-13',
    1 => 'ED finally approves valid package',
    2 => 'PO generated with correct locked approved data',
    3 => '17–18',
    4 => 'aw.appr',
  ),
  13 => 
  array (
    0 => 'AC-14',
    1 => 'PO missing a required Incoterms named place',
    2 => 'PO auto-email blocked, exception assigned',
    3 => '18',
    4 => 'po.reg',
  ),
  14 => 
  array (
    0 => 'AC-15',
    1 => 'PO passes validation',
    2 => 'Only selected supplier receives approved PDF and ACK link',
    3 => '19',
    4 => 'po.reg',
  ),
  15 => 
  array (
    0 => 'AC-16',
    1 => 'Supplier disputes price on PO',
    2 => 'Dispute recorded; updated terms require approved revision',
    3 => '20',
    4 => 'po.disp',
  ),
  16 => 
  array (
    0 => 'AC-17',
    1 => 'PI bank details change',
    2 => 'Independent validation task created; processing restricted',
    3 => '21',
    4 => 'imp.pi',
  ),
  17 => 
  array (
    0 => 'AC-19',
    1 => 'Email provider transiently fails',
    2 => 'Controlled retry without generating a second PO ID',
    3 => '19',
    4 => 'po.reg',
  ),
  18 => 
  array (
    0 => 'AC-20',
    1 => 'Manager reviews dashboard',
    2 => 'Role-permitted live stage, delays and audit trail visible',
    3 => '—',
    4 => 'home',
  ),
);

    /** [key, label] - used by the (integrator-wired) demo stage loader. */
    public const STAGES = array (
  0 => 
  array (
    0 => 'req',
    1 => '1 · Fresh case — verify requisitions',
  ),
  1 => 
  array (
    0 => 'booking',
    1 => '2 · Requisition frozen → confirm booking',
  ),
  2 => 
  array (
    0 => 'rfq',
    1 => '3 · Booking confirmed → create RFQ',
  ),
  3 => 
  array (
    0 => 'quotes',
    1 => '4 · Quotations received',
  ),
  4 => 
  array (
    0 => 'cs',
    1 => '5 · Comparison decided → submit award',
  ),
  5 => 
  array (
    0 => 'appr',
    1 => '6 · In approval (Finance next)',
  ),
  6 => 
  array (
    0 => 'po',
    1 => '7 · Approved → POs to dispatch',
  ),
  7 => 
  array (
    0 => 'ack',
    1 => '8 · POs sent, one accepted',
  ),
  8 => 
  array (
    0 => 'pi',
    1 => '9 · PI mismatch to resolve',
  ),
  9 => 
  array (
    0 => 'lc',
    1 => '10 · PI matched → LC approval',
  ),
  10 => 
  array (
    0 => 'done',
    1 => '11 · Completed (LC opened)',
  ),
);
}
