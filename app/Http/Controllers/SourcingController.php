<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Masters;
use App\Services\RfqService;
use Illuminate\Http\Request;

// Sidebar group: Sourcing / RFQ - supplier shortlist, RFQs, link & deadline admin, submitted quotations.
// Thin controller: business rules + audit live in RfqService / QuotationService.
class SourcingController extends Controller
{
    // ---------------------------------------------------------------- pages
    public function shortlist()
    {
        $c = $this->workingCase();
        $single = [];
        if ($c) {
            $sp = Masters::supplierProducts();
            foreach ($c->s['booking']['lines'] as $l) {
                if (count(array_filter($sp, fn ($x) => $x['pid'] === $l['pid'] && $x['active'])) === 1) {
                    $single[] = $l['pid'];
                }
            }
        }
        return view('sourcing.shortlist', ['single' => $single, 'rows' => Masters::supplierProducts()]);
    }

    public function rfqs()
    {
        return view('sourcing.rfqs', [
            'c' => $this->workingCase(),
            'cases' => array_values(array_filter(CaseBook::all(), fn ($c) => !empty($c->s['rfq']['no']))),
        ]);
    }

    public function showRfq(Request $r, string $id)
    {
        $c = CaseBook::findOrFail($id);
        return view('sourcing.rfq-show', ['c' => $c, 'preview' => $r->query('preview')]);
    }

    public function tracking()
    {
        $rows = [];
        foreach (CaseBook::all() as $x) {
            foreach ($x->s['inv'] as $i) {
                $rows[] = ['c' => $x, 'i' => $i, 'id' => $x->id . '|' . $i['sid']];
            }
        }
        return view('sourcing.tracking', ['c' => $this->workingCase(), 'rows' => $rows, 'cases' => CaseBook::all()]);
    }

    public function quotes()
    {
        $rows = [];
        foreach (CaseBook::all() as $x) {
            foreach ($x->s['inv'] as $i) {
                if (!empty($i['submitted'])) {
                    $rows[] = ['c' => $x, 'i' => $i, 'id' => $x->id . '|' . $i['sid']];
                }
            }
        }
        return view('sourcing.quotes', ['c' => $this->workingCase(), 'rows' => $rows, 'cases' => CaseBook::all()]);
    }

    public function showQuote(string $id)
    {
        [$cid, $sid] = array_pad(explode('|', $id, 2), 2, '');
        $c = CaseBook::findOrFail($cid);
        $iv = $c->invOf($sid);
        abort_unless($iv && !empty($iv['submitted']), 404);
        return view('sourcing.quote-show', ['c' => $c, 'sid' => $sid, 'iv' => $iv]);
    }

    // ---------------------------------------------------------------- supplier shortlist actions
    public function spToggle(int $id, RfqService $svc)
    {
        $on = $svc->toggleMapping($id);
        return $this->done('Mapping ' . ($on ? 'activated' : 'deactivated'), 'ok');
    }

    public function spDelete(int $id, RfqService $svc)
    {
        $svc->deleteMapping($id);
        return $this->done('Mapping removed', 'ok');
    }

    public function spSave(Request $r, RfqService $svc)
    {
        $d = $r->validate(['id' => 'nullable|integer', 'pid' => 'required|string', 'sid' => 'required|string', 'name' => 'required|string|max:120', 'active' => 'required|in:Active,Inactive']);
        $svc->saveMapping($d);
        return $this->done('Mapping saved', 'ok');
    }

    // ---------------------------------------------------------------- RFQ actions
    public function rfqSave(Request $r, string $id, RfqService $svc)
    {
        $d = $r->validate([
            'urgent' => 'nullable|in:0,1', 'reason' => 'nullable|string|max:200', 'sender' => 'nullable|string|max:120', 'sample' => 'nullable|string|max:40',
            'docs' => 'nullable|string|max:200', 'notes' => 'nullable|string|max:1000', 'rf' => 'nullable|array', 'rf.*' => 'string', 'sup' => 'nullable|array', 'sup.*' => 'string',
        ]);
        $c = $svc->saveRfq($id, $d);
        return $this->doneTo(route('rfq.show', $c->id), 'RFQ drafts created — review and send', 'ok');
    }

    public function drSend(Request $r, string $id, RfqService $svc)
    {
        $ids = (array) $r->input('ids', []);
        $x = $svc->sendDrafts($id, $ids);
        if (!$x['n']) {
            return $this->done('Select draft(s) in Draft status first', 'warn');
        }
        return $this->done("{$x['ok']} RFQ(s) sent with individual secure links" . ($x['ok'] < $x['n'] ? ' — ' . ($x['n'] - $x['ok']) . ' blocked (calendar not approved)' : ''), $x['ok'] ? 'ok' : 'warn');
    }

    public function drSchedule(Request $r, string $id, RfqService $svc)
    {
        $d = $r->validate(['t' => 'required|date_format:Y-m-d\TH:i', 'ids' => 'required|array|min:1'], ['ids.required' => 'Select draft(s) in Draft status first', 't.required' => 'Choose a send time', 't.date_format' => 'Choose a valid send time']);
        $n = $svc->schedule($id, $d['ids'], $d['t']);
        return $this->done("{$n} RFQ draft(s) scheduled", 'ok');
    }

    public function drRun(Request $r, string $id, RfqService $svc)
    {
        $n = $svc->runDue($id, $r->boolean('all'));
        return $n ? $this->done("{$n} scheduled RFQ(s) dispatched", 'ok') : $this->done($r->boolean('all') ? 'Nothing scheduled' : 'Nothing is due yet', 'warn');
    }

    public function ivRemind(Request $r, string $id, RfqService $svc)
    {
        $svc->remind($id, $r->validate(['sid' => 'required|string'])['sid']);
        return $this->done('Reminder (template B) sent', 'ok');
    }

    public function ivRevoke(Request $r, string $id, RfqService $svc)
    {
        $svc->revoke($id, $r->validate(['sid' => 'required|string'])['sid']);
        return $this->done('Supplier link revoked', 'ok');
    }

    public function ivExtend(Request $r, string $id, RfqService $svc)
    {
        $d = $r->validate(['sid' => 'required|string', 'd' => 'required|date_format:Y-m-d', 'r' => 'required|string|max:300'], ['r.required' => 'A reason for the extension is required.']);
        $svc->extend($id, $d['sid'], $d['d'], $d['r']);
        return $this->done('Deadline extended; supplier notified', 'ok');
    }

    public function rfqClose(string $id, RfqService $svc)
    {
        return $svc->close($id) ? $this->done('RFQ closed — open links revoked', 'ok') : $this->done('Nothing to close', 'warn');
    }
}
