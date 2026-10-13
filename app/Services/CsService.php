<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;

/**
 * Comparative statement, negotiation, exclusion, bulk select and sample results
 * (prototype ACT.cs* / smpSave / setFull / negotiate). setFull() and negotiate() are the low-level
 * prototype functions (no permission check, caller saves); the other public methods are the
 * permission-checked actions that open their own CaseBook::tx().
 */
class CsService
{
    public const SAMPLE_RESULTS = ['Pass', 'Conditional', 'Fail', 'Waived', 'Pending'];

    // ---------- prototype functions (mutate $c->s, caller saves) ----------

    /** Award the full required quantity to one supplier (prototype setFull). */
    public function setFull(CaseBook $c, string $pid, string $sid, ?string $by = null): void
    {
        $x = $c->csOf($pid);
        $rq = $c->reqQty($pid);
        $x['alloc'] = [$sid => $rq];
        $x['waiver'] = [];
        if ($c->s['quotes'][$sid][$pid]['moq'] > $rq) {
            $x['waiver'][$sid] = '';
        }
        $c->csSet($pid, $x);
        Workflow::log('CS selected (full)', $pid, Masters::supplier($sid)['name'] . ' · ' . Fmt::n0($rq), ['cid' => $c->id] + ($by ? ['by' => $by] : []));
    }

    /** Record a negotiation round and re-price the quote line (prototype negotiate). */
    public function negotiate(CaseBook $c, string $pid, string $sid, float $newPrice, string $reason, string $by): void
    {
        $q = &$c->s['quotes'][$sid][$pid];
        $k = $newPrice / $q['price'];
        $q['listPrice'] = round(($q['listPrice'] ?? $q['price']) * $k, 4);
        $q['tiers'] = array_map(fn ($t) => ['price' => round($t['price'] * $k, 4)] + $t, $q['tiers'] ?? []);
        $x = $c->csOf($pid);
        $x['neg'][] = ['id' => Workflow::nid('NEG'), 'sid' => $sid, 'from' => $q['price'], 'to' => $newPrice, 'reason' => $reason, 'by' => $by, 'at' => Clock::ms()];
        $c->csSet($pid, $x);
        $q['ins'] = (int) round($q['ins'] * $k);
        $q['duty'] = (int) round($q['duty'] * $k);
        $q['price'] = $newPrice;
        $q['rev']++;
        unset($q);
        Workflow::log('Negotiation round', $pid, Masters::supplier($sid)['name'] . ' → ' . Fmt::usd2($newPrice), ['cid' => $c->id, 'by' => 'System']);
    }

    // ---------- helpers ----------

    private function unlocked(CaseBook $c): void
    {
        if (!empty($c->s['csLocked'])) {
            throw new BusinessRule('Comparison is locked while the award package is in approval. Use “Raise change request” on the approval page.');
        }
    }

    private function line(CaseBook $c, string $pid): void
    {
        if (!in_array($pid, array_column($c->s['booking']['lines'], 'pid'), true)) {
            throw new BusinessRule("{$pid} is not part of this booking.");
        }
    }

    private function bid(CaseBook $c, string $pid, string $sid): array
    {
        foreach ($c->bids($pid) as $b) {
            if ($b['sid'] === $sid) {
                return $b;
            }
        }
        throw new BusinessRule('No valid quotation from this supplier for ' . $pid . '.');
    }

    // ---------- actions ----------

    /** Award full / partial quantity to a supplier (ACT.csAllocSave). $d: pid, sid, mode(full|partial), qty, waiver, just */
    public function allocate(string $cid, array $d): void
    {
        Perm::need('cs.select');
        CaseBook::tx($cid, function (CaseBook $c) use ($d) {
            $this->unlocked($c);
            $pid = $d['pid'];
            $sid = $d['sid'];
            $this->line($c, $pid);
            $b = $this->bid($c, $pid, $sid);
            $e = $c->elig($b, $pid);
            if (!$e['ok']) {
                throw new BusinessRule($e['why']);
            }
            $rq = $c->reqQty($pid);
            $x = $c->csOf($pid);
            $mode = ($d['mode'] ?? 'full') === 'partial' ? 'partial' : 'full';
            $qty = $mode === 'full' ? $rq : (float) ($d['qty'] ?? 0);
            if (!($qty > 0)) {
                throw new BusinessRule('Enter a valid quantity.');
            }
            $qty = Fmt::jsNum($qty);
            $others = array_sum(array_diff_key($x['alloc'], [$sid => 0]));
            if ($mode === 'partial' && $others + $qty > $rq) {
                throw new BusinessRule('Allocation exceeds the required quantity (' . Fmt::n0($rq) . '). Already awarded to others: ' . Fmt::n0($others) . '.');
            }
            $moq = $b['q']['moq'];
            $below = $qty < $moq;
            $why = trim((string) ($d['waiver'] ?? ''));
            if ($below) {
                if (!Perm::can('cs.waiver')) {
                    throw new BusinessRule('Awarding ' . Fmt::n0($qty) . ' is below the supplier MOQ ' . Fmt::n0($moq) . '. A Procurement Supervisor must record the waiver.');
                }
                if ($why === '') {
                    throw new BusinessRule('Record the MOQ waiver reason — supplier written acceptance is needed.');
                }
            }
            if ($mode === 'full') {
                $x['alloc'] = [];
                $x['waiver'] = [];
            }
            $x['alloc'][$sid] = $qty;
            if ($below) {
                $x['waiver'][$sid] = $why;
                Workflow::raise('MOQ waiver', $pid, Masters::supplier($sid)['name'] . ' MOQ ' . Fmt::n0($moq) . ' > awarded ' . Fmt::n0($qty), ['cid' => $c->id]);
            } else {
                unset($x['waiver'][$sid]);
            }
            if (!empty($d['just'])) {
                $x['just'] = $d['just'];
            }
            $c->csSet($pid, $x);
            Workflow::log('CS awarded', $pid, Masters::supplier($sid)['name'] . ' · ' . Fmt::n0($qty), ['cid' => $c->id]);
        });
    }

    public function clear(string $cid, string $pid, string $sid): void
    {
        Perm::need('cs.select');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $sid) {
            $this->unlocked($c);
            $this->line($c, $pid);
            $x = $c->csOf($pid);
            unset($x['alloc'][$sid], $x['waiver'][$sid]);
            $c->csSet($pid, $x);
            Workflow::log('CS allocation cleared', $pid, Masters::supplier($sid)['name'], ['cid' => $c->id]);
        });
    }

    /** Award rationale / justification text (ACT.csJust). */
    public function justify(string $cid, string $pid, string $text): void
    {
        Perm::need('cs.select');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $text) {
            $this->unlocked($c);
            $this->line($c, $pid);
            $x = $c->csOf($pid);
            $x['just'] = $text;
            $c->csSet($pid, $x);
        });
    }

    /** Finance product-wise remark (ACT.csFin) - only while the package is at Finance / ED stage. */
    public function financeRemark(string $cid, string $pid, string $text): void
    {
        Perm::need('fin.remark');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $text) {
            if (!in_array($c->s['appr']['status'], ['fin', 'ed'], true)) {
                throw new BusinessRule('Finance remarks can be added while the package is at Finance / ED stage.');
            }
            $this->line($c, $pid);
            $x = $c->csOf($pid);
            $x['fin'] = $text;
            $c->csSet($pid, $x);
            Workflow::log('Finance remark', $pid, mb_substr($text, 0, 60), ['cid' => $c->id]);
        });
    }

    /** Negotiation round (ACT.csNegSave). */
    public function negotiateRound(string $cid, string $pid, string $sid, float $np, string $reason): void
    {
        Perm::need('cs.negotiate');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $sid, $np, $reason) {
            $this->unlocked($c);
            $this->line($c, $pid);
            $q = $c->s['quotes'][$sid][$pid] ?? null;
            if (!$q) {
                throw new BusinessRule('No quotation from this supplier for ' . $pid . '.');
            }
            if (!($np > 0) || $np === (float) $q['price']) {
                throw new BusinessRule('Enter a revised price different from the current price.');
            }
            if (trim($reason) === '') {
                throw new BusinessRule('Record the round notes.');
            }
            $this->negotiate($c, $pid, $sid, $np, trim($reason), Perm::user());
        });
    }

    /** Mark a material for re-tender (ACT.csExclSave). */
    public function exclude(string $cid, string $pid, string $reason): void
    {
        Perm::need('cs.select');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $reason) {
            $this->unlocked($c);
            $this->line($c, $pid);
            if (trim($reason) === '') {
                throw new BusinessRule('Give the reason for the re-tender.');
            }
            $x = $c->csOf($pid);
            $x['excl'] = true;
            $x['exclWhy'] = trim($reason);
            $c->csSet($pid, $x);
            Workflow::log('CS excluded', $pid, trim($reason), ['cid' => $c->id]);
        });
    }

    public function include(string $cid, string $pid): void
    {
        Perm::need('cs.select');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid) {
            $this->unlocked($c);
            $this->line($c, $pid);
            $x = $c->csOf($pid);
            $x['excl'] = false;
            $c->csSet($pid, $x);
            Workflow::log('CS re-included', $pid, '', ['cid' => $c->id]);
        });
    }

    /** Preview of the bulk strategy: ['res' => [[line, bid],...], 'un' => [line,...]] for pending products with quotes. */
    public function bulkPlan(CaseBook $c, string $k): array
    {
        $res = [];
        $un = [];
        foreach ($c->s['booking']['lines'] as $l) {
            if ($c->csStat($l['pid']) !== 'Pending' || !$c->bids($l['pid'])) {
                continue;
            }
            $b = $c->pick($l['pid'], $k);
            if ($b) {
                $res[] = ['l' => $l, 'b' => $b];
            } else {
                $un[] = $l;
            }
        }
        return ['res' => $res, 'un' => $un];
    }

    /** Apply the strategy to the remaining pending products (ACT.csBulkApply). Returns the number allocated. */
    public function bulkApply(string $cid, string $k): int
    {
        Perm::need('cs.select');
        if (!isset(CaseBook::STRAT[$k])) {
            throw new BusinessRule('Choose a valid strategy.');
        }
        return CaseBook::tx($cid, function (CaseBook $c) use ($k) {
            $this->unlocked($c);
            $plan = $this->bulkPlan($c, $k);
            foreach ($plan['res'] as $r) {
                $this->setFull($c, $r['l']['pid'], $r['b']['sid']);
            }
            $n = count($plan['res']);
            Workflow::log('Bulk supplier select', $c->id, CaseBook::STRAT[$k] . " · {$n} products", ['cid' => $c->id]);
            return $n;
        });
    }

    /** Rows for the bulk CS CSV export. */
    public function csvRows(CaseBook $c): array
    {
        $rows = [['product', 'supplier', 'qty', 'unit_price', 'material_amount', 'est_landed']];
        foreach ($c->lineAwards() as $x) {
            $rows[] = [$x['pid'], Masters::supplier($x['sid'])['name'], $x['qty'], $x['price'], number_format($x['amt'], 2, '.', ''), number_format($x['est'], 2, '.', '')];
        }
        return $rows;
    }

    /** QA sample result (ACT.smpSave). A failed sample drops the allocation and raises 'Sample rejected' (AC-10). */
    public function sampleResult(string $cid, string $sid, string $pid, string $res, ?string $report = null, ?string $note = null): void
    {
        Perm::need('sample.edit');
        if (!in_array($res, self::SAMPLE_RESULTS, true)) {
            throw new BusinessRule('Choose a valid sample result.');
        }
        CaseBook::tx($cid, function (CaseBook $c) use ($sid, $pid, $res, $report, $note) {
            if (!empty($c->s['csLocked'])) {
                throw new BusinessRule('Comparison is locked while the award package is in approval.');
            }
            if (!isset($c->s['quotes'][$sid][$pid])) {
                throw new BusinessRule('Quotation not found.');
            }
            $c->s['quotes'][$sid][$pid]['sample'] = $res;
            $c->s['quotes'][$sid][$pid]['report'] = (string) $report;
            $name = Masters::supplier($sid)['name'];
            Workflow::log('Sample result set', $pid, "{$name} → {$res}" . ($note ? ' · ' . $note : ''), ['cid' => $c->id]);
            if ($res === 'Fail') {
                $x = $c->csOf($pid);
                unset($x['alloc'][$sid], $x['waiver'][$sid]);
                $c->csSet($pid, $x);
                Workflow::raise('Sample rejected', $pid, "{$name} sample failed — offer ineligible", ['cid' => $c->id]);
            }
        });
    }
}
