<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Exceptions\BusinessRule;
use App\Models\AuditLog;
use App\Models\ExceptionRecord;
use App\Models\PolicyItem;
use App\Support\ChartUi;
use App\Support\WorkingCase;
use Illuminate\Support\Facades\Route;

/** Command Center data (prototype tasksFor / spendBySup / PAGES.home) + the goCase action. */
class CommandCenterService
{
    /** PO status as far as module A needs it (full validation belongs to the PO module). */
    private function poStatus(array $p): string
    {
        return !empty($p['sent']) ? ($p['status'] ?? '') : (!empty($p['frozen']) ? 'Frozen' : (($p['status'] ?? '') === 'Scheduled' ? 'Scheduled' : (($p['status'] ?? '') === 'Correction queue' ? 'Correction queue' : 'Ready to Dispatch')));
    }

    /** Work queue of a role: list of [t, go, k, cid]. */
    public function tasksFor(string $role, array $cases): array
    {
        $T = [];
        $add = function ($t, $go, $k = '', $cid = null) use (&$T) {
            $T[] = ['t' => $t, 'go' => $go, 'k' => $k, 'cid' => $cid];
        };
        $openExc = ExceptionRecord::where('status', 'Open')->get();
        foreach ($cases as $c) {
            $i = $c->flowIdx();
            if ($i >= 8) {
                continue;
            }
            $s = $c->s;
            $cnt = $c->csCounts();
            $A = $s['appr'];
            $pend = count(array_filter($s['demand'], fn ($d) => $d['status'] === 'pending'));
            if ($role === 'exec' || $role === 'sup') {
                if (!$s['frozen'] && $pend) {
                    $add("Verify {$pend} pending requisition line(s)", 'rq.board', 'warn', $c->id);
                } elseif (!$s['frozen']) {
                    $add('Freeze the validated requisition', 'rq.board', 'ok', $c->id);
                }
                if ($s['frozen'] && !$s['booking']['confirmed']) {
                    $add('Confirm book qty — signed document required', 'rq.booking', 'warn', $c->id);
                }
                if ($s['booking']['confirmed'] && !$s['drafts']) {
                    $add('Create the RFQ from the confirmed booking', 'src.rfq', 'warn', $c->id);
                }
                if (array_filter($s['drafts'], fn ($d) => ($d['status'] ?? '') === 'Draft')) {
                    $add('Review and send RFQ drafts', 'src.rfq', 'warn', $c->id);
                }
                if (array_filter($s['drafts'], fn ($d) => ($d['status'] ?? '') === 'Blocked')) {
                    $add('RFQ draft blocked — supplier calendar not approved', 'ad.cal', 'bad', $c->id);
                }
                foreach ($openExc->where('type', 'No bid')->where('case_id', $c->id) as $e) {
                    $add("No bid for {$e->ref} — extend deadline or re-tender", 'src.track', 'bad', $c->id);
                }
                if ($s['inv'] && $cnt['pend'] && $c->rfqClosed()) {
                    $add("{$cnt['pend']} product(s) to decide in the comparative statement", 'src.cs', 'warn', $c->id);
                }
                if ($s['inv'] && $cnt['total'] && !$cnt['pend'] && empty($s['csLocked']) && $A['status'] !== 'approved') {
                    $add('Comparison complete — submit the award recommendation', 'aw.rec', 'ok', $c->id);
                }
                if (in_array($A['status'], ['returned', 'rejected'], true)) {
                    $add("{$A['id']} {$A['status']} — revise and resubmit", 'src.cs', 'bad', $c->id);
                }
                foreach ($s['pos'] as $p) {
                    if (empty($p['sent']) && empty($p['frozen'])) {
                        $ready = $this->poStatus($p) === 'Ready to Dispatch';
                        $add("{$p['no']}: " . ($ready ? 'dispatch to supplier' : 'fix correction-queue items'), 'po.reg', $ready ? 'warn' : 'bad', $c->id);
                    }
                }
                foreach ($s['pos'] as $p) {
                    if ($p['ack'] === 'Disputed') {
                        $add("{$p['no']} disputed — resolve", 'po.disp', 'bad', $c->id);
                    }
                }
                foreach ($s['pos'] as $p) {
                    if ($p['ack'] === 'Accepted' && empty($s['pis'][$p['no']])) {
                        $add("{$p['no']} accepted — record supplier PI", 'imp.pi', 'warn', $c->id);
                    }
                }
            }
            if ($role === 'mkt' && $A['status'] === 'mkt') {
                $add("Acknowledge {$A['id']} v{$A['version']}", 'aw.appr', 'warn', $c->id);
            }
            if ($role === 'fin') {
                if ($A['status'] === 'fin') {
                    $add("Finance approval for {$A['id']} v{$A['version']}", 'aw.appr', 'warn', $c->id);
                }
                foreach ($s['pos'] as $p) {
                    $l = $s['lc'][$p['no']] ?? null;
                    $pi = $s['pis'][$p['no']] ?? null;
                    if ($l && !empty($l['docsDone']) && empty($l['appr'])) {
                        $add("Approve LC for {$p['no']}", 'imp.lc', 'warn', $c->id);
                    }
                    if ($pi && !empty($pi['bankChanged']) && empty($pi['bankV']['fin'])) {
                        $add("Verify changed bank details ({$p['no']})", 'imp.pi', 'bad', $c->id);
                    }
                }
            }
            if ($role === 'ed' && $A['status'] === 'ed') {
                $add("Final bulk validation of {$A['id']} v{$A['version']}", 'aw.appr', 'warn', $c->id);
            }
            if ($role === 'qa') {
                $n = 0;
                foreach ($s['quotes'] as $q) {
                    foreach ($q as $x) {
                        if (in_array($x['sample'] ?? '', ['Pending', 'Conditional'], true)) {
                            $n++;
                        }
                    }
                }
                if ($n) {
                    $add("{$n} sample result(s) pending or conditional", 'src.samples', 'warn', $c->id);
                }
            }
            if ($role === 'imp') {
                foreach ($s['pos'] as $p) {
                    if ($p['ack'] === 'Accepted' && empty($s['pis'][$p['no']])) {
                        $add("{$p['no']}: record supplier PI", 'imp.pi', 'warn', $c->id);
                    }
                }
                foreach ($s['pos'] as $p) {
                    $pi = $s['pis'][$p['no']] ?? null;
                    $l = $s['lc'][$p['no']] ?? null;
                    if ($pi && !empty($pi['approved']) && !($l && !empty($l['docsDone']))) {
                        $add("{$p['no']}: complete import & banking checks", 'imp.pi', 'warn', $c->id);
                    }
                    if ($l && !empty($l['appr']) && empty($l['open'])) {
                        $add("{$p['no']}: open LC", 'imp.lc', 'ok', $c->id);
                    }
                }
            }
        }
        if ($role === 'admin') {
            $n = count(array_filter(Masters::calendars(), fn ($x) => empty($x['approved'])));
            if ($n) {
                $add("{$n} working-day calendar(s) awaiting approval", 'ad.cal', 'warn');
            }
            $add(PolicyItem::where('status', 'Open')->count() . ' open policy decisions', 'ad.policy');
        }
        return $T;
    }

    /** Awarded USD by supplier, descending: [[sid, amt], ...]. */
    public function spendBySup(array $cases): array
    {
        $m = [];
        foreach ($cases as $c) {
            foreach ($c->lineAwards() as $x) {
                $m[$x['sid']] = ($m[$x['sid']] ?? 0) + $x['amt'];
            }
        }
        arsort($m);
        return array_map(fn ($k, $v) => [$k, $v], array_keys($m), $m);
    }

    /** Everything the Command Center view needs. */
    public function overview(): array
    {
        $cases = CaseBook::all();
        $role = Perm::role();
        $c = WorkingCase::get();
        $dl = [];
        foreach ($cases as $x) {
            foreach ($x->s['inv'] as $i) {
                if (!$x->ivDone($i)) {
                    $dl[] = ['c' => $x, 'i' => $i];
                }
            }
        }
        usort($dl, fn ($a, $b) => $a['i']['deadline'] <=> $b['i']['deadline']);
        $pos = [];
        foreach ($cases as $x) {
            $pos = array_merge($pos, $x->pos());
        }
        return [
            'cases' => $cases, 'c' => $c, 'tasks' => $this->tasksFor($role, $cases),
            'open' => ExceptionRecord::where('status', 'Open')->count(),
            'active' => array_values(array_filter($cases, fn ($x) => $x->flowIdx() < 8)),
            'deadlines' => $dl, 'spend' => $this->spendBySup($cases),
            'poAwaiting' => count(array_filter($pos, fn ($p) => !empty($p['sent']) && $p['ack'] === 'Awaiting')),
            'rfqOpen' => count($dl),
            'recent' => AuditLog::orderByDesc('ts_ms')->orderByDesc('id')->limit(7)->get(),
        ];
    }

    /** "Open" button on a task card: make the case the working case, then go to the target page (prototype ACT.goCase). */
    public function goCase(?string $caseId, string $to): string
    {
        if (!Route::has($to) || !in_array($to, $this->targets(), true)) {
            throw new BusinessRule('Unknown destination.');
        }
        if (!ChartUi::canGo($to)) {
            throw new BusinessRule('Your role cannot open that page.');
        }
        if ($caseId) {
            if (!CaseBook::find($caseId)) {
                throw new BusinessRule("Case {$caseId} not found.");
            }
            WorkingCase::set($caseId);
        }
        return route($to);
    }

    /** Only sidebar pages (no detail pages / POST routes) may be a goCase target. */
    private function targets(): array
    {
        $o = [];
        foreach (config('menu') as $g) {
            foreach ($g['items'] as $i) {
                $o[] = $i['route'];
            }
        }
        return $o;
    }
}
