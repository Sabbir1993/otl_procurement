<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Services\CsService;
use Illuminate\Http\Request;

// Sidebar group: Sourcing / RFQ - comparative statement (+ its actions)
class ComparativeStatementController extends Controller
{
    public function index(Request $r, CsService $svc)
    {
        $c = $this->workingCase();
        if (!$c || empty($c->s['booking']['confirmed']) || empty($c->s['inv'])) {
            return view('sourcing.comparative-statement', ['c' => $c, 'ready' => false]);
        }
        $pids = array_column($c->s['booking']['lines'], 'pid');
        $pid = in_array($r->query('pid'), $pids, true) ? $r->query('pid') : ($pids[0] ?? null);
        $tab = in_array($r->query('tab'), ['product', 'tech', 'bulk'], true) ? $r->query('tab') : 'product';
        $strat = isset(CaseBook::STRAT[$r->query('strat')]) ? $r->query('strat') : 'cost';
        return view('sourcing.comparative-statement', [
            'c' => $c, 'ready' => true, 'pid' => $pid, 'tab' => $tab, 'strat' => $strat, 'pids' => $pids,
            'plan' => ($tab === 'product' && $r->boolean('preview')) ? $svc->bulkPlan($c, $strat) : null,
        ]);
    }

    public function allocate(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string', 'sid' => 'required|string', 'mode' => 'required|in:full,partial', 'qty' => 'nullable|numeric|min:0', 'waiver' => 'nullable|string|max:300', 'just' => 'nullable|string|max:1000']);
        $svc->allocate($id, $d);
        return $this->done('Award saved.', 'ok');
    }

    public function clear(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string', 'sid' => 'required|string']);
        $svc->clear($id, $d['pid'], $d['sid']);
        return $this->done('Allocation cleared.');
    }

    public function justify(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string', 'just' => 'nullable|string|max:1000']);
        $svc->justify($id, $d['pid'], (string) ($d['just'] ?? ''));
        return $this->done('Justification saved.', 'ok');
    }

    public function finRemark(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string', 'fin' => 'nullable|string|max:1000']);
        $svc->financeRemark($id, $d['pid'], (string) ($d['fin'] ?? ''));
        return $this->done('Finance remark saved.', 'ok');
    }

    public function negotiate(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string', 'sid' => 'required|string', 'np' => 'required|numeric|min:0', 'r' => 'required|string|max:500']);
        $svc->negotiateRound($id, $d['pid'], $d['sid'], (float) $d['np'], $d['r']);
        return $this->done('Negotiation round recorded.', 'ok');
    }

    public function exclude(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string', 'r' => 'required|string|max:300']);
        $svc->exclude($id, $d['pid'], $d['r']);
        return $this->done('Material marked for re-tender.', 'warn');
    }

    public function reinclude(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['pid' => 'required|string']);
        $svc->include($id, $d['pid']);
        return $this->done('Material re-included.');
    }

    public function bulk(Request $r, CsService $svc, string $id)
    {
        $d = $r->validate(['strat' => 'required|string']);
        $n = $svc->bulkApply($id, $d['strat']);
        return redirect()->route('src.cs')->with('toast', ['msg' => "{$n} product(s) allocated", 'kind' => 'ok']);
    }

    public function csv(CsService $svc, string $id)
    {
        $c = CaseBook::findOrFail($id);
        $out = "\xEF\xBB\xBF" . implode("\r\n", array_map(fn ($row) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row)), $svc->csvRows($c)));
        return response($out, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="bulk-cs-report-' . $c->id . '.csv"']);
    }
}
