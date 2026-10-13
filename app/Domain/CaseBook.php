<?php

namespace App\Domain;

use App\Models\PurchaseCase;
use Illuminate\Support\Facades\DB;

/**
 * The purchase-case aggregate. Wraps a PurchaseCase row and exposes the prototype's case helpers
 * (reqQty, bids, landed, csOf, scored, pick, lineAwards, flow, nextAction ...).
 *
 * State shape ($cb->s, saved as purchase_cases.state):
 *   demand[] · frozen · booking{lines[],confirmed,serial,doc,...} · rfq{no,ver,reqFields[],urgent,notes}
 *   drafts[] · inv[] (RFQ invitations) · quotes[sid][pid] · quoteHdr[sid] · portal · cs[pid] · csLocked
 *   appr{status,version,id,rec,mkt,fin,ed,chk,snap,hist[]} · pos[] · pis[poNo] · lc[poNo] · sender · notes
 * Timestamps are epoch ms. Mutate $cb->s then call $cb->save(); wrap multi-step actions in CaseBook::tx().
 */
class CaseBook
{
    public array $s;

    public function __construct(public PurchaseCase $model)
    {
        $this->s = $model->state;
    }

    public static function find(string $id): ?self
    {
        $m = PurchaseCase::find($id);
        return $m ? new self($m) : null;
    }

    public static function findOrFail(string $id): self
    {
        return new self(PurchaseCase::findOrFail($id));
    }

    /** @return self[] all cases ordered by id */
    public static function all(): array
    {
        return PurchaseCase::orderBy('id')->get()->map(fn ($m) => new self($m))->all();
    }

    /** Run $fn(CaseBook) in a transaction on a freshly locked copy and persist it. */
    public static function tx(string $id, callable $fn)
    {
        return DB::transaction(function () use ($id, $fn) {
            $cb = self::findOrFail($id);
            $r = $fn($cb);
            $cb->save();
            return $r;
        });
    }

    public function save(): void
    {
        $this->model->state = $this->s;
        $this->model->save();
    }

    public function __get($k)
    {
        return match ($k) {
            'id' => $this->model->id, 'name' => $this->model->name, 'month' => $this->model->month,
            'mon' => $this->model->mon, 't0' => $this->model->t0_ms, 'by' => $this->model->created_by,
            default => $this->s[$k] ?? null,
        };
    }

    // ---------- demand / booking ----------
    public function demandSum(string $pid, ?string $status = null): float|int
    {
        return array_sum(array_map(fn ($d) => $d['qty'], array_filter($this->s['demand'], fn ($d) => $d['pid'] === $pid && (!$status || $d['status'] === $status))));
    }

    public function reqQty(string $pid): float|int
    {
        foreach ($this->s['booking']['lines'] as $l) {
            if ($l['pid'] === $pid) {
                return $l['qty'];
            }
        }
        return 0;
    }

    // ---------- quotes ----------
    public function hdrOf(string $sid): array
    {
        return $this->s['quoteHdr'][$sid] ?? [];
    }

    public function invOf(string $sid): ?array
    {
        foreach ($this->s['inv'] as $iv) {
            if ($iv['sid'] === $sid) {
                return $iv;
            }
        }
        return null;
    }

    public static function landed(array $q): float
    {
        return $q['price'] * $q['qty'] + $q['freight'] + $q['ins'] + $q['duty'] + $q['bank'];
    }

    /** Valid (submitted) bids for a material: [['sid','s' => supplier,'q' => quote], ...] in supplier-id order. */
    public function bids(string $pid): array
    {
        $out = [];
        foreach (Masters::suppliers() as $sid => $sp) {
            $q = $this->s['quotes'][$sid][$pid] ?? null;
            $iv = $this->invOf($sid);
            if ($q && $iv && !empty($iv['submitted'])) {
                $out[] = ['sid' => $sid, 's' => $sp, 'q' => $q];
            }
        }
        return $out;
    }

    /** Deviation declared by the supplier for a material (header exception list), '' if none. */
    public function devFor(string $sid, string $pid): string
    {
        $h = $this->hdrOf($sid);
        if (!preg_match('/except/i', $h['compliance'] ?? '')) {
            return '';
        }
        $L = array_values(array_filter(array_map('trim', preg_split('/\n+/', (string) ($h['exceptions'] ?? ''))), fn ($x) => $x !== ''));
        $m = array_filter($L, fn ($x) => preg_match('/^P-\d+/i', $x));
        $mine = array_filter($m, fn ($x) => str_starts_with(strtoupper($x), $pid));
        $gen = array_filter($L, fn ($x) => !preg_match('/^P-\d+/i', $x));
        return implode('; ', array_merge($mine, $gen));
    }

    /** Required technical parameters that the supplier left blank for a quote line (labels). */
    public function techBlanks(string $sid, string $pid): array
    {
        $q = $this->s['quotes'][$sid][$pid] ?? null;
        if (!$q) {
            return [];
        }
        $F = ['h' => $this->hdrOf($sid), 'l' => [$pid => $q['spec'] ?? []]];
        $rf = $this->s['rfq']['reqFields'] ?? [];
        $out = [];
        foreach (QuoteSpec::fields() as $f) {
            if ($f['s'] === 'l' && $f['tab'] === 'technical' && QuoteSpec::fReq($rf, $f, $pid, $F) && !QuoteSpec::hasVal(QuoteSpec::fGet($F, $f, $pid))) {
                $out[] = $f['l'];
            }
        }
        return $out;
    }

    // ---------- comparative statement ----------
    /** @return array{ok:bool,why?:string,warn?:string} */
    public function elig(array $b, string $pid): array
    {
        $q = $b['q'];
        if ($q['sample'] === 'Fail') {
            return ['ok' => false, 'why' => 'Sample failed — blocked from award'];
        }
        if ($q['sample'] === 'Pending') {
            return ['ok' => false, 'why' => 'Sample result pending (QA)'];
        }
        if ($q['validity'] < Clock::ms()) {
            return ['ok' => false, 'why' => 'Quote validity expired'];
        }
        $rq = $this->reqQty($pid);
        $w = [];
        if ($q['sample'] === 'Conditional') {
            $w[] = 'Conditional pass — needs approved deviation';
        }
        if ($q['moq'] > $rq) {
            $w[] = 'MOQ ' . Fmt::n0($q['moq']) . ' exceeds required ' . Fmt::n0($rq);
        }
        if ($dv = $this->devFor($b['sid'], $pid)) {
            $w[] = 'Declared deviation: ' . $dv;
        }
        return ['ok' => true, 'warn' => implode(' · ', $w)];
    }

    /** CS record for a material (created on first touch). Returned by value; write back with csSet(). */
    public function csOf(string $pid): array
    {
        return $this->s['cs'][$pid] ?? ['alloc' => [], 'just' => '', 'excl' => false, 'exclWhy' => '', 'fin' => '', 'neg' => [], 'waiver' => []];
    }

    public function csSet(string $pid, array $x): void
    {
        $this->s['cs'][$pid] = $x;
    }

    public function allocSum(string $pid): float|int
    {
        return array_sum($this->csOf($pid)['alloc']);
    }

    public function csStat(string $pid): string
    {
        $x = $this->csOf($pid);
        if (!empty($x['excl'])) {
            return 'Excluded';
        }
        $a = $this->allocSum($pid);
        $r = $this->reqQty($pid);
        return ($a >= $r && $r > 0) ? 'Complete' : ($a > 0 ? 'Partial' : 'Pending');
    }

    /** @return array{done:int,pend:int,total:int} */
    public function csCounts(): array
    {
        $L = $this->s['booking']['lines'];
        $d = count(array_filter($L, fn ($l) => in_array($this->csStat($l['pid']), ['Complete', 'Excluded'], true)));
        return ['done' => $d, 'pend' => count($L) - $d, 'total' => count($L)];
    }

    /** Index score per supplier (weights are ILLUSTRATIVE — Open Policy Item 7). */
    public function scored(string $pid): array
    {
        $W = ['cost' => .35, 'tech' => .30, 'del' => .20, 'terms' => .10, 'hist' => .05];
        $bs = array_values(array_filter($this->bids($pid), fn ($b) => $b['q']['sample'] !== 'Fail'));
        if (!$bs) {
            return [];
        }
        $mL = min(array_map(fn ($b) => self::landed($b['q']), $bs));
        $mD = min(array_map(fn ($b) => $b['q']['days'], $bs));
        $out = [];
        foreach ($bs as $b) {
            $q = $b['q'];
            $out[$b['sid']] = $W['cost'] * $mL / self::landed($q) * 100 + $W['tech'] * $q['q'] + $W['del'] * $mD / $q['days'] * 100 + $W['terms'] * $q['terms'] + $W['hist'] * $q['hist'];
        }
        return $out;
    }

    public const STRAT = ['cost' => 'Lowest landed cost', 'quality' => 'Highest quality rank', 'lead' => 'Lowest lead time', 'index' => 'Highest index score'];

    /** Best eligible bid (MOQ ok, not Conditional) for a strategy, or null. */
    public function pick(string $pid, string $k): ?array
    {
        $rq = $this->reqQty($pid);
        $sc = $this->scored($pid);
        $cand = array_values(array_filter($this->bids($pid), fn ($b) => $this->elig($b, $pid)['ok'] && $b['q']['moq'] <= $rq && $b['q']['sample'] !== 'Conditional'));
        if (!$cand) {
            return null;
        }
        $key = match ($k) {
            'quality' => fn ($b) => -$b['q']['q'],
            'lead' => fn ($b) => $b['q']['days'],
            'index' => fn ($b) => -($sc[$b['sid']] ?? 0),
            default => fn ($b) => self::landed($b['q']),
        };
        usort($cand, fn ($a, $b) => ($key($a) <=> $key($b)) ?: (self::landed($a['q']) <=> self::landed($b['q'])));
        return $cand[0];
    }

    public function lowestLanded(string $pid): ?string
    {
        $x = array_values(array_filter($this->bids($pid), fn ($b) => $this->elig($b, $pid)['ok']));
        if (!$x) {
            return null;
        }
        usort($x, fn ($a, $b) => self::landed($a['q']) <=> self::landed($b['q']));
        return $x[0]['sid'];
    }

    /** Why a selection needs a written justification ('' = none needed). */
    public function needsJust(string $pid): string
    {
        $x = $this->csOf($pid);
        $ids = array_keys($x['alloc']);
        if (!$ids) {
            return '';
        }
        $low = $this->lowestLanded($pid);
        foreach ($ids as $s) {
            if ($this->devFor($s, $pid)) {
                return 'Supplier declared a specification deviation — record the approved deviation';
            }
        }
        foreach ($ids as $s) {
            if (($this->s['quotes'][$s][$pid]['sample'] ?? '') === 'Conditional') {
                return 'Conditional sample pass — record the approved deviation';
            }
        }
        if (!empty($x['waiver'])) {
            return 'MOQ waiver in use — state the commercial reason';
        }
        if (count($ids) > 1) {
            return 'Split award — state the reason for splitting';
        }
        if ($low && $ids[0] !== $low) {
            return 'Selected supplier is not the lowest landed cost — state the rationale';
        }
        return '';
    }

    /** Cheapest eligible alternative that is not already selected. */
    public function altFor(string $pid): ?array
    {
        $sel = array_keys($this->csOf($pid)['alloc']);
        $x = array_values(array_filter($this->bids($pid), fn ($b) => $this->elig($b, $pid)['ok'] && !in_array($b['sid'], $sel, true)));
        usort($x, fn ($a, $b) => self::landed($a['q']) <=> self::landed($b['q']));
        return $x[0] ?? null;
    }

    /** One row per (material, supplier) allocation with price at the awarded quantity. */
    public function lineAwards(): array
    {
        $out = [];
        foreach ($this->s['booking']['lines'] as $l) {
            foreach ($this->csOf($l['pid'])['alloc'] as $sid => $qty) {
                $q = $this->s['quotes'][$sid][$l['pid']];
                $up = QuoteSpec::unitPriceFor($q, $qty);
                $out[] = ['pid' => $l['pid'], 'sid' => $sid, 'qty' => $qty, 'price' => $up, 'amt' => $up * $qty, 'est' => self::landed($q) * $qty / $q['qty'], 'moq' => $q['moq'], 'sample' => $q['sample'], 'days' => $q['days']];
            }
        }
        return $out;
    }

    /** Award checksum — identical to the prototype's awardChk() so seeded approvals stay valid. */
    public function awardChk(): string
    {
        $rows = array_map(fn ($x) => [$x['pid'], $x['sid'], Fmt::jsNum($x['qty']), Fmt::jsNum($x['price'])], $this->lineAwards());
        return Fmt::hash(json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function savings(): float
    {
        $t = 0;
        foreach ($this->s['booking']['lines'] as $l) {
            foreach ($this->csOf($l['pid'])['alloc'] as $sid => $qty) {
                $q = $this->s['quotes'][$sid][$l['pid']];
                $t += ($q['orig'] - $q['price']) * $qty;
            }
        }
        return $t;
    }

    // ---------- RFQ / invitations ----------
    public function ivState(array $iv): string
    {
        return !empty($iv['submitted']) ? 'Submitted' : (!empty($iv['revoked']) ? 'Revoked' : (Clock::ms() > $iv['deadline'] ? 'Expired' : $iv['state']));
    }

    public function ivDone(array $iv): bool
    {
        return !empty($iv['submitted']) || !empty($iv['revoked']) || Clock::ms() > $iv['deadline'];
    }

    public function rfqClosed(): bool
    {
        return count($this->s['inv']) > 0 && !array_filter($this->s['inv'], fn ($i) => !$this->ivDone($i));
    }

    /** Suppliers able to quote the booked materials: sid => [pid,...]. */
    public function poolSup(): array
    {
        $pids = array_column($this->s['booking']['lines'], 'pid');
        $m = [];
        foreach (Masters::supplierProducts() as $x) {
            if ($x['active'] && in_array($x['pid'], $pids, true)) {
                $m[$x['sid']][] = $x['pid'];
            }
        }
        return $m;
    }

    public function rfqVars(array $iv): array
    {
        $sp = Masters::supplier($iv['sid']);
        return [
            'SUPPLIER_CONTACT_NAME' => $sp['contact'] ?? '', 'RFQ_NUMBER' => $iv['rfq'] ?? $this->s['rfq']['no'],
            'PRODUCTS' => implode('; ', array_map(fn ($id) => Masters::material($id)['name'] . ' (' . Fmt::n0($this->reqQty($id)) . ' ' . Masters::material($id)['uom'] . ')', $iv['pids'])),
            'RFQ_DEADLINE_CHINA' => Fmt::dCN($iv['deadline']), 'RFQ_DEADLINE_BD' => Fmt::tz($iv['deadline'], 'H:i') . ' BST',
            'RFQ_LINK' => \App\Support\Link::to('/s/' . ($iv['tokPlain'] ?? '[TOKEN]')),
        ];
    }

    // ---------- flow engine ----------
    public const FLOW = [['Requisition', 'rq.board'], ['Booking', 'rq.booking'], ['RFQ', 'src.rfq'], ['Quotes', 'src.track'], ['Comparison', 'src.cs'], ['Approval', 'aw.appr'], ['Purchase order', 'po.reg'], ['PI & LC', 'imp.pi']];
    public const APPR_STEPS = ['mkt' => 'Marketing ACK', 'fin' => 'Finance APP', 'ed' => 'ED final APP'];

    public function flowDone(): array
    {
        $cnt = $this->csCounts();
        $L = $this->s['pos'];
        return [
            (bool) $this->s['frozen'], (bool) $this->s['booking']['confirmed'], count($this->s['inv']) > 0, $this->rfqClosed(),
            $cnt['total'] > 0 && $cnt['pend'] === 0, $this->s['appr']['status'] === 'approved',
            count($L) > 0 && !array_filter($L, fn ($p) => $p['ack'] !== 'Accepted'),
            count($L) > 0 && !array_filter($L, fn ($p) => empty($this->s['lc'][$p['no']]['open'])),
        ];
    }

    /** @return array<int,array{n:string,go:string,st:string}> st = done|on|todo */
    public function flow(): array
    {
        $d = $this->flowDone();
        $on = array_search(false, $d, true);
        return array_map(fn ($f, $i) => ['n' => $f[0], 'go' => $f[1], 'st' => $d[$i] ? 'done' : ($i === $on ? 'on' : 'todo')], self::FLOW, array_keys(self::FLOW));
    }

    public function flowIdx(): int
    {
        $i = array_search(false, $this->flowDone(), true);
        return $i === false ? 8 : $i;
    }

    public function caseStatus(): string
    {
        $i = $this->flowIdx();
        return $i >= 8 ? 'Completed' : self::FLOW[$i][0];
    }

    public function lcState(array $po): string
    {
        $l = $this->s['lc'][$po['no']] ?? null;
        $pi = $this->s['pis'][$po['no']] ?? null;
        return $l && !empty($l['open']) ? 'LC opened' : ($l && !empty($l['appr']) ? 'LC approved' : ($l && !empty($l['docsDone']) ? 'Checks done' : ($pi && !empty($pi['approved']) ? 'PI matched' : ($pi ? 'PI recorded' : ($po['ack'] === 'Accepted' ? 'Awaiting PI' : '—')))));
    }

    /** What happens next: ['t' => text, 'go' => route key, 'who' => role key|''] */
    public function nextAction(): array
    {
        $i = $this->flowIdx();
        $pend = count(array_filter($this->s['demand'], fn ($d) => $d['status'] === 'pending'));
        $cnt = $this->csCounts();
        $A = $this->s['appr'];
        $role = fn ($k) => Perm::roleName($k);
        if ($i === 0) {
            return $pend ? ['t' => "Verify {$pend} pending requisition line(s)", 'go' => 'rq.board', 'who' => 'sup'] : ['t' => 'Freeze the validated requisition', 'go' => 'rq.board', 'who' => 'sup'];
        }
        if ($i === 1) {
            return ['t' => 'Confirm book qty — signed document is mandatory', 'go' => 'rq.booking', 'who' => 'sup'];
        }
        if ($i === 2) {
            return $this->s['drafts'] ? ['t' => 'Review and send the RFQ drafts', 'go' => 'src.rfq', 'who' => 'exec'] : ['t' => 'Create the RFQ from the confirmed booking', 'go' => 'src.rfq', 'who' => 'exec'];
        }
        if ($i === 3) {
            $n = count(array_filter($this->s['inv'], fn ($x) => !empty($x['submitted'])));
            return ['t' => "Waiting for supplier quotations — {$n} of " . count($this->s['inv']) . ' submitted', 'go' => 'src.track', 'who' => 'exec'];
        }
        if ($i === 4) {
            return ['t' => "Decide {$cnt['pend']} product(s) in the comparative statement", 'go' => 'src.cs', 'who' => 'exec'];
        }
        if ($i === 5) {
            if (in_array($A['status'], ['none', 'returned', 'rejected'], true)) {
                return ['t' => $A['status'] === 'none' ? 'Submit the award recommendation' : "Award {$A['status']} — revise and resubmit", 'go' => 'aw.rec', 'who' => 'exec'];
            }
            return ['t' => 'Waiting for ' . self::APPR_STEPS[$A['status']] . ' (' . $role($A['status']) . ')', 'go' => 'aw.appr', 'who' => $A['status']];
        }
        if ($i === 6) {
            $un = array_filter($this->s['pos'], fn ($p) => empty($p['sent']));
            if ($un) {
                return ['t' => 'Dispatch ' . count($un) . ' purchase order(s) to suppliers', 'go' => 'po.reg', 'who' => 'exec'];
            }
            $aw = array_filter($this->s['pos'], fn ($p) => $p['ack'] !== 'Accepted');
            return ['t' => 'Waiting for ' . count($aw) . ' supplier PO acknowledgment(s)', 'go' => 'po.ack', 'who' => 'exec'];
        }
        if ($i === 7) {
            $need = array_values(array_filter($this->s['pos'], fn ($p) => empty($this->s['lc'][$p['no']]['open'])));
            $p = $need[0];
            $st = $this->lcState($p);
            $do = match ($st) { 'Awaiting PI' => 'record supplier PI', 'PI recorded' => 'resolve PI match', 'PI matched' => 'complete import & banking checks', 'Checks done' => 'Finance LC approval', default => 'open the LC' };
            return ['t' => "{$p['no']}: {$do}", 'go' => 'imp.pi', 'who' => 'imp'];
        }
        return ['t' => 'Case complete — shipment, GRN and payment continue in the ERP modules', 'go' => 'ship', 'who' => ''];
    }

    /** All POs of this case, each tagged with its case id (prototype allPOs()). */
    public function pos(): array
    {
        return array_map(fn ($p) => $p + ['_cid' => $this->id], $this->s['pos']);
    }

    /** Create a brand-new empty case state (prototype newCase). */
    public static function blankState(int $t0, string $notes = ''): array
    {
        return [
            'created' => $t0, 'demand' => [], 'frozen' => false, 'booking' => ['lines' => [], 'confirmed' => false, 'serial' => null, 'doc' => null],
            'rfq' => ['no' => null, 'ver' => 1, 'reqFields' => []], 'drafts' => [], 'inv' => [], 'quotes' => [], 'quoteHdr' => [], 'portal' => [],
            'cs' => [], 'csLocked' => false,
            'appr' => ['status' => 'none', 'version' => 0, 'id' => null, 'rec' => null, 'mkt' => null, 'fin' => null, 'ed' => null, 'chk' => null, 'hist' => []],
            'pos' => [], 'pis' => [], 'lc' => [], 'sender' => 'Procurement Desk — import@[oil-domain]', 'notes' => $notes,
        ];
    }
}
