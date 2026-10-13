<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Masters;
use App\Services\QuotationService;
use Illuminate\Http\Request;

// Sidebar group: Suppliers - "Supplier Portal Preview" (behind login). Renders the SAME supplier-facing screens as the public
// token portal (SupplierPortalController) but read-only: staff can never submit on behalf of a supplier.
// Query string: ?pCase=IC-2026-10&pSup=SUP-00001&pMode=rfq|po&pTab=general|commercial|technical|delivery|sample|review
class PortalController extends Controller
{
    public function portal(Request $r, QuotationService $q)
    {
        $cases = CaseBook::all();
        $c = ($r->query('pCase') ? CaseBook::find((string) $r->query('pCase')) : null) ?? $this->workingCase() ?? ($cases[0] ?? null);
        abort_unless($c, 404);
        $sups = Masters::suppliers();
        $sid = (string) $r->query('pSup', '');
        if (!isset($sups[$sid])) {
            $sid = $c->s['inv'][0]['sid'] ?? array_key_first($sups);
        }
        if (!$c->invOf($sid)) {                          // prototype pctx(): fall back to a case where this supplier was invited
            foreach ($cases as $x) {
                if ($x->invOf($sid)) {
                    $c = $x;
                    break;
                }
            }
        }
        $mode = $r->query('pMode') === 'po' ? 'po' : 'rfq';
        return view('suppliers.portal', [
            'c' => $c, 'cases' => $cases, 'sid' => $sid, 'sups' => $sups, 'mode' => $mode,
            'ctx' => $mode === 'rfq' ? $q->context($c, $sid, (string) $r->query('pTab', 'general')) : null,
        ]);
    }
}
