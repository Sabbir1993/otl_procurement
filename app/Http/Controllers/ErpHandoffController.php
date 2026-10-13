<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Masters;
use App\Services\RequisitionService;

// Sidebar group: ERP hand-off pages (existing ERP modules). Port of menu/shared/erp-handoff.js (EXTMOD).
class ErpHandoffController extends Controller
{
    public const EXTMOD = [
        'pr' => ['t' => 'Create / Edit PR', 's' => '01', 'd' => 'Marketing and Store raise product requisitions in the existing ERP screen. Submitted requisitions arrive in this module as requisition lines.',
            'give' => 'Submitted PR lines → Requisition Board', 'want' => 'Purchase requisition with item, qty, required date, reason and attachment', 'to' => 'rq.board', 'tl' => 'Open requisition board'],
        'ship' => ['t' => 'Shipment Tracking', 's' => '22', 'd' => 'Existing Import & Warehouse module. After LC opening it tracks supplier dispatch, packing list, transport documents, ETA and customs clearance.',
            'give' => 'Approved PO + validated PI + LC reference', 'want' => 'Shipment status, ETA changes, customs clearance records', 'to' => 'imp.lc', 'tl' => 'Open LC register'],
        'grn' => ['t' => 'GRN / Inspection / Claims', 's' => '22', 'd' => 'Existing Import & Warehouse module. Records quantity received, QA inspection, discrepancies and claims, and updates stock.',
            'give' => 'PO lines and accepted quantities', 'want' => 'GRN, receiving discrepancy records, stock and liability update', 'to' => 'po.reg', 'tl' => 'Open PO register'],
        'pay' => ['t' => 'Invoice & Payment', 's' => '23', 'd' => 'Existing Invoice & Payment module. Runs the three-way match (PO ↔ GRN ↔ invoice), payment authorisation and closure.',
            'give' => 'PO commitments and LC / advance terms', 'want' => 'Payment reference, reconciled supplier balance, PO closure', 'to' => 'po.reg', 'tl' => 'Open PO register'],
    ];

    public function __construct(private RequisitionService $svc)
    {
    }

    public function pr()
    {
        return $this->page('pr', 'requisitions.pr');
    }

    public function shipment()
    {
        return $this->page('ship', 'import.shipment');
    }

    public function grn()
    {
        return $this->page('grn', 'import.grn');
    }

    public function payment()
    {
        return $this->page('pay', 'import.payment');
    }

    /** Hand-off queue computed from real case data: [summary text, column titles, rows]. */
    private function queue(string $k): array
    {
        $cases = CaseBook::all();
        $pos = array_merge(...array_map(fn ($c) => $c->pos(), $cases) ?: [[]]);
        $poRow = fn ($p, $extra) => [$p['no'], $p['_cid'], Masters::supplier($p['sid'])['name'], $extra];
        switch ($k) {
            case 'pr':
                $c = $this->workingCase();
                $L = $c ? $this->svc->lines($c) : [];
                return [count($L) . ' requisition lines in the working case', ['Line', 'Material', 'Buyer', 'Qty · status'],
                    array_map(fn ($d) => [$d['id'], $d['pid'] . ' · ' . Masters::material($d['pid'])['name'], $d['buyer'], number_format($d['qty']) . ' · ' . $d['status']], $L)];
            case 'ship':
                $rows = [];
                foreach ($cases as $c) {
                    foreach ($c->s['lc'] as $no => $l) {
                        if (!empty($l['open'])) {
                            $p = collect($c->s['pos'])->firstWhere('no', $no);
                            $rows[] = [$no, $c->id, $p ? Masters::supplier($p['sid'])['name'] : '—', 'LC ' . ($l['open']['ref'] ?? '')];
                        }
                    }
                }
                return [count($rows) . ' PO(s) with LC opened, ready for shipment tracking', ['PO', 'Case', 'Supplier', 'LC'], $rows];
            case 'grn':
                $a = array_values(array_filter($pos, fn ($p) => $p['ack'] === 'Accepted'));
                return [count($a) . ' accepted PO(s) in the pipeline', ['PO', 'Case', 'Supplier', 'Value (USD)'], array_map(fn ($p) => $poRow($p, number_format($p['total'])), $a)];
            default:
                return [count($pos) . ' PO(s) become payable commitments', ['PO', 'Case', 'Supplier', 'Value (USD)'], array_map(fn ($p) => $poRow($p, number_format($p['total'])), $pos)];
        }
    }

    private function page(string $k, string $view)
    {
        [$summary, $heads, $rows] = $this->queue($k);
        return view($view, ['k' => $k, 'e' => self::EXTMOD[$k], 'summary' => $summary, 'heads' => $heads, 'rows' => $rows]);
    }
}
