<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Exceptions\BusinessRule;
use App\Services\QuotationService;
use App\Services\RfqService;
use Illuminate\Http\Request;

/**
 * PUBLIC supplier portal - tokenised, no login (routes/portal.php, outside the auth group, throttled).
 * A token resolves to exactly one invitation (case + supplier); every read/write is scoped to it, so a supplier can never see or touch
 * another supplier's data or another RFQ (AC-04). Unknown / malformed tokens are a plain 404 (no hint whether a token ever existed).
 * Deadline / revocation / required-field rules are enforced in QuotationService (AC-06, AC-08), never trusted from the browser.
 */
class SupplierPortalController extends Controller
{
    public const DISPUTE_TERMS = ['Unit price', 'Quantity', 'Incoterms / place', 'Payment terms', 'Delivery date', 'Specification', 'Claims / warranty clause', 'Other'];

    public function __construct(private RfqService $rfq, private QuotationService $q)
    {
    }

    /** @return array{0:CaseBook,1:array} */
    private function resolve(string $token): array
    {
        return $this->rfq->findByToken($token) ?? abort(404);
    }

    private function back(string $token, array $q = [])
    {
        return redirect()->route('supplier.portal', ['token' => $token] + $q);
    }

    private function toast(string $msg, string $kind = '')
    {
        return ['msg' => $msg, 'kind' => $kind];
    }

    public function show(Request $r, string $token)
    {
        [$c, $iv] = $this->resolve($token);
        $this->q->markViewed($c->id, $iv['sid'], $r->ip());
        $c = CaseBook::findOrFail($c->id);
        return view('suppliers.portal-public', [
            'token' => $token, 'c' => $c, 'sid' => $iv['sid'], 'mode' => 'rfq', 'ctx' => $this->q->context($c, $iv['sid'], (string) $r->query('tab', 'general')),
            'pos' => QuotationService::portalPos($c, $iv['sid']),
        ]);
    }

    public function po(Request $r, string $token)
    {
        [$c, $iv] = $this->resolve($token);
        abort_if(!empty($iv['revoked']), 404);
        return view('suppliers.portal-public', ['token' => $token, 'c' => $c, 'sid' => $iv['sid'], 'mode' => 'po', 'ctx' => null, 'pos' => QuotationService::portalPos($c, $iv['sid'])]);
    }

    /** Save the posted tab into the draft. "Save draft" button = explicit (audit); tab navigation = silent autosave. */
    public function save(Request $r, string $token)
    {
        [$c, $iv] = $this->resolve($token);
        $to = (string) ($r->input('goto') ?: $r->input('tab', 'general'));
        try {
            $this->q->saveForm($c->id, $iv['sid'], $this->formInput($r), $this->formFiles($r), $r->input('act') === 'draft');
        } catch (BusinessRule $e) {
            return $this->back($token, ['tab' => (string) $r->input('tab', 'general')])->withInput($r->input())->with('toast', $this->toast($e->getMessage(), 'bad'));
        }
        $msg = $r->input('act') === 'draft' ? $this->toast('Draft saved (not evaluated)', 'ok') : null;
        return $this->back($token, ['tab' => $to])->with('toast', $msg);
    }

    public function submit(Request $r, string $token)
    {
        [$c, $iv] = $this->resolve($token);
        try {
            $this->q->saveForm($c->id, $iv['sid'], $this->formInput($r), $this->formFiles($r));
            $res = $this->q->submit($c->id, $iv['sid']);
        } catch (BusinessRule $e) {
            $keep = $r->input();
            $c2 = CaseBook::findOrFail($c->id);
            $errs = $c2->invOf($iv['sid']) ? $this->q->errors($c2, $iv['sid']) : [];
            $blocked = $errs && !str_contains($e->getMessage(), 'deadline');
            return $this->back($token, $blocked ? ['tab' => $errs[0]['tab']] : [])->withInput($keep)->with('toast', $this->toast($e->getMessage(), 'bad'));
        }
        return $this->back($token)->with('toast', $this->toast("Submitted — reference {$res['ref']}. Confirmation email sent.", 'ok'));
    }

    public function revise(string $token)
    {
        [$c, $iv] = $this->resolve($token);
        $rev = $this->q->revise($c->id, $iv['sid']);
        return $this->back($token)->with('toast', $this->toast("Editing creates revision {$rev}"));
    }

    public function cancelRevision(string $token)
    {
        [$c, $iv] = $this->resolve($token);
        $this->q->cancelRevision($c->id, $iv['sid']);
        return $this->back($token)->with('toast', $this->toast('Revision cancelled — your submitted quotation is unchanged'));
    }

    public function accept(string $token, string $no)
    {
        [$c, $iv] = $this->resolve($token);
        $this->q->acknowledgePo($c->id, $iv['sid'], $no, 'ok');
        return redirect()->route('supplier.portal.po', $token)->with('toast', $this->toast('PO accepted', 'ok'));
    }

    public function dispute(Request $r, string $token, string $no)
    {
        [$c, $iv] = $this->resolve($token);
        $d = $r->validate(['t' => 'required|in:' . implode(',', self::DISPUTE_TERMS), 'r' => 'required|string|max:1000'], ['r.required' => 'Please describe the discrepancy.']);
        $this->q->acknowledgePo($c->id, $iv['sid'], $no, 'no', "{$d['t']}: {$d['r']}");
        return redirect()->route('supplier.portal.po', $token)->with('toast', $this->toast('Dispute submitted — OIL Procurement has been notified', 'warn'));
    }

    private function formInput(Request $r): array
    {
        return ['h' => (array) $r->input('h', []), 'l' => (array) $r->input('l', [])] + ($r->has('decl') ? ['decl' => (string) $r->input('decl')] : []);
    }

    private function formFiles(Request $r): array
    {
        return ['h' => (array) ($r->file('h') ?? []), 'l' => (array) ($r->file('l') ?? [])];
    }
}
