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
 * Supplier PI, the 10-point PO<->PI match, import documentation checklist, LC approval and LC opening
 * (prototype seedPI, piMatch, lcOf, ACT.piSave/piApprove/piClar/piBank/impDoc/impDone/lcAppr/lcSave).
 * Domain methods (seed, match, ok) are pure; action methods enforce Perm::need and run their own transaction.
 * State: $c->s['pis'][poNo] and $c->s['lc'][poNo] = {docs, docsDone, appr{by,at}, open{ref,bank,amt,exp,at}}.
 */
class PiService
{
    public const BUYER = 'Osman Interlinings Limited (OIL)';

    /** Indicative import documentation & banking checklist (prototype IMPDOCS). */
    public const IMPDOCS = [
        'Proforma Invoice (PI) validated against PO',
        'Approved PO — frozen approved version attached',
        'Import permission / customs documentation',
        'LC / TT application documents prepared',
        'HS code and customs classification confirmed',
        'Sanction / compliance screening completed',
        'Import registration & permitted-goods check (IRC) — verify per Bangladesh Bank / AD-bank rules',
        'LC authorization, margin and FX exposure check per policy',
    ];

    /**
     * PI pre-filled from the PO (prototype seedPI). Consumes a PI system number unless $over['sys'] is given
     * (use that when you only need the form defaults).
     */
    public function seed(CaseBook $c, array $po, array $over = []): array
    {
        $sp = Masters::supplier($po['sid']);
        return $over + [
            'no' => '[SUPPLIER-PI-REF]', 'date' => Clock::ms(), 'seller' => $sp['name'], 'buyer' => self::BUYER,
            'lines' => array_map(fn ($l) => ['pid' => $l['pid'], 'desc' => $l['name'], 'qty' => $l['qty'], 'price' => $l['price'], 'hs' => $l['hs'] ?? ''], $po['lines']),
            'cur' => 'USD', 'inc' => $po['inc'], 'place' => $po['place'], 'payment' => $po['payment'], 'shipBy' => $po['ship'],
            'bankChanged' => false, 'bankV' => ['fin' => false, 'sup' => false], 'approved' => false, 'ver' => 1,
            'sys' => $over['sys'] ?? Workflow::nid('PI'),
        ];
    }

    public static function piTotal(array $pi): float|int
    {
        return array_sum(array_map(fn ($x) => $x['qty'] * $x['price'], $pi['lines']));
    }

    /** The 10 match points: list of ['n' => name, 'ok' => bool, 'd' => detail] (prototype piMatch). */
    public function match(CaseBook $c, array $po, array $pi): array
    {
        $sp = Masters::supplier($po['sid']);
        $eq = fn ($a, $b) => abs($a - $b) < .0005;
        $ln = array_map(function ($l) use ($pi) {
            $i = [];
            foreach ($pi['lines'] as $x) {
                if ($x['pid'] === $l['pid']) {
                    $i = $x;
                    break;
                }
            }
            return ['l' => $l, 'i' => $i];
        }, $po['lines']);
        $all = fn (callable $f) => !array_filter($ln, fn ($x) => !$f($x));
        $descBad = fn ($x) => ($x['i']['desc'] ?? null) !== $x['l']['name'] || (!empty($x['l']['hs']) && !empty($x['i']['hs']) && $x['i']['hs'] !== $x['l']['hs']);
        $qtyBad = fn ($x) => !isset($x['i']['qty']) || $x['i']['qty'] != $x['l']['qty'];
        $prBad = fn ($x) => !isset($x['i']['price']) || !$eq($x['i']['price'], $x['l']['price']);
        $piTot = self::piTotal($pi);
        $bv = $pi['bankV'] ?? ['fin' => false, 'sup' => false];
        $bankOk = empty($pi['bankChanged']) || (!empty($bv['fin']) && !empty($bv['sup']));
        $rows = [
            ['Buyer / seller identities', $pi['seller'] === $sp['name'] && $pi['buyer'] === self::BUYER, "{$pi['seller']} → {$pi['buyer']}"],
            ['Description / specification / HS code', $all(fn ($x) => !$descBad($x)),
                implode('; ', array_map(fn ($x) => ($x['i']['desc'] ?? null) !== $x['l']['name'] ? "{$x['l']['pid']}: “" . ($x['i']['desc'] ?? '') . "” vs “{$x['l']['name']}”" : "{$x['l']['pid']}: HS {$x['i']['hs']} vs {$x['l']['hs']}", array_filter($ln, $descBad))) ?: 'Match'],
            ['Quantities', $all(fn ($x) => !$qtyBad($x)),
                implode('; ', array_map(fn ($x) => "{$x['l']['pid']}: PI " . Fmt::n0($x['i']['qty'] ?? 0) . ' vs PO ' . Fmt::n0($x['l']['qty']), array_filter($ln, $qtyBad))) ?: 'Match'],
            ['Unit price', $all(fn ($x) => !$prBad($x)),
                implode('; ', array_map(fn ($x) => "{$x['l']['pid']}: PI " . Fmt::usd3($x['i']['price'] ?? 0) . ' vs PO ' . Fmt::usd3($x['l']['price']), array_filter($ln, $prBad))) ?: 'Match'],
            ['Totals', $eq($piTot, $po['total']), 'PI ' . Fmt::usd($piTot) . ' vs PO ' . Fmt::usd($po['total'])],
            ['Currency', $pi['cur'] === 'USD', "PI {$pi['cur']} vs PO USD"],
            ['Shipping window', $pi['shipBy'] <= $po['ship'], 'PI ' . Fmt::dD($pi['shipBy']) . ' vs PO by ' . Fmt::dD($po['ship'])],
            ['Incoterms place', $pi['inc'] === $po['inc'] && $pi['place'] === $po['place'], "PI {$pi['inc']} {$pi['place']} vs PO {$po['inc']} {$po['place']}"],
            ['Payment terms', $pi['payment'] === $po['payment'], "PI “{$pi['payment']}” vs PO “{$po['payment']}”"],
            ['Beneficiary bank details', $bankOk, !empty($pi['bankChanged']) ? ((!empty($bv['fin']) && !empty($bv['sup'])) ? 'Changed — dual control complete' : 'Changed vs vendor master — dual control ' . ((!empty($bv['fin']) || !empty($bv['sup'])) ? '1/2' : '0/2')) : 'Unchanged vs vendor master'],
        ];
        return array_map(fn ($r) => ['n' => $r[0], 'ok' => (bool) $r[1], 'd' => $r[2]], $rows);
    }

    public function ok(CaseBook $c, array $po, array $pi): bool
    {
        return !array_filter($this->match($c, $po, $pi), fn ($x) => !$x['ok']);
    }

    /** Read-only LC record (defaults when none yet). */
    public static function lcOf(CaseBook $c, string $no): array
    {
        return ($c->s['lc'][$no] ?? []) + ['docs' => [], 'docsDone' => false, 'appr' => null, 'open' => null];
    }

    /** PI-phase required guard shared by the actions. */
    private function need(CaseBook $c, array $po): array
    {
        $pi = $c->s['pis'][$po['no']] ?? null;
        if (!$pi) {
            throw new BusinessRule('Record the supplier PI first.');
        }
        return $pi;
    }

    // ------------------------------------------------------------------ PI

    /** Starting page of the "Record supplier PI" picker: must be an accepted PO. */
    public function pick(string $no): void
    {
        Perm::need('pi.record');
        $c = PoService::locate($no) ?? throw new BusinessRule("Purchase order {$no} not found.");
        $po = $c->s['pos'][PoService::idx($c, $no)];
        if ($po['ack'] !== 'Accepted') {
            throw new BusinessRule('No accepted POs yet — PI starts after supplier acceptance.');
        }
    }

    /**
     * ACT.piSave: record the supplier PI (or a new revision) against an ACCEPTED PO and compare it with the PO.
     * $d: pino, seller, buyer, cur, inc, pl, pay, sh (Y-m-d), bc (bool), lines[i] = {desc, hs, qty, price}.
     * @return array{pi:array,bad:string[]} the saved PI and the names of the match points that differ from the PO
     */
    public function record(string $no, array $d): array
    {
        Perm::need('pi.record');
        return PoService::with($no, function (CaseBook $c, array &$po) use ($d, $no) {
            if ($po['ack'] !== 'Accepted') {
                throw new BusinessRule($po['ack'] === 'Disputed' ? 'PO disputed — PI progression is stopped until the dispute is resolved and re-approved.' : 'PI intake opens after supplier acceptance.');
            }
            $old = $c->s['pis'][$no] ?? null;
            if (!empty($c->s['lc'][$no]['open'])) {
                throw new BusinessRule('The LC is already opened for this PO — the PI can no longer be revised.');
            }
            $ref = trim((string) ($d['pino'] ?? ''));
            if ($ref === '') {
                throw new BusinessRule('PI reference is required.');
            }
            $ship = strtotime(($d['sh'] ?? '') . 'T00:00:00Z');
            if ($ship === false) {
                throw new BusinessRule('Ship-by date is required.');
            }
            $b = $old ?: $this->seed($c, $po);
            $lines = [];
            foreach ($b['lines'] as $i => $l) {
                $x = $d['lines'][$i] ?? [];
                if (!is_numeric($x['qty'] ?? null) || !is_numeric($x['price'] ?? null)) {
                    throw new BusinessRule("Quantity and unit price are required for {$l['pid']}.");
                }
                $lines[] = ['pid' => $l['pid'], 'desc' => trim((string) ($x['desc'] ?? '')), 'hs' => trim((string) ($x['hs'] ?? '')), 'qty' => $x['qty'] + 0, 'price' => $x['price'] + 0];
            }
            $pi = array_merge($b, [
                'no' => $ref, 'seller' => trim((string) ($d['seller'] ?? '')), 'buyer' => trim((string) ($d['buyer'] ?? '')), 'cur' => $d['cur'] ?? 'USD',
                'inc' => $d['inc'] ?? '', 'place' => trim((string) ($d['pl'] ?? '')), 'payment' => trim((string) ($d['pay'] ?? '')), 'shipBy' => $ship * 1000,
                'bankChanged' => !empty($d['bc']), 'ver' => $old ? $b['ver'] + 1 : 1, 'approved' => false, 'date' => Clock::ms(), 'lines' => $lines,
            ]);
            if (!$old || empty($old['bankChanged'])) {
                $pi['bankV'] = ['fin' => false, 'sup' => false];
            }
            $c->s['pis'][$no] = $pi;
            Workflow::log('PI recorded', $no, "rev {$pi['ver']}", ['cid' => $c->id]);
            if ($pi['bankChanged']) {
                Workflow::raise('Bank details changed', $no, 'PI beneficiary bank differs from vendor master — dual control required', ['cid' => $c->id]);
            }
            $bad = array_filter($this->match($c, $po, $pi), fn ($x) => !$x['ok']);
            if ($bad) {
                Workflow::raise('PO vs PI mismatch', $no, implode(', ', array_column($bad, 'n')), ['cid' => $c->id]);
            }
            return ['pi' => $pi, 'bad' => array_values(array_column($bad, 'n'))];
        });
    }

    /** ACT.piApprove: approve for import processing - only when all 10 points match (bank dual control included). */
    public function approve(string $no): void
    {
        Perm::need('pi.match');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no) {
            $pi = $this->need($c, $po);
            if ($po['ack'] !== 'Accepted') {
                throw new BusinessRule('PO is not accepted — PI cannot be approved.');
            }
            $bad = array_filter($this->match($c, $po, $pi), fn ($x) => !$x['ok']);
            if ($bad) {
                throw new BusinessRule('PI cannot be approved — mismatch on: ' . implode(', ', array_column($bad, 'n')) . '.');
            }
            $c->s['pis'][$no]['approved'] = true;
            Workflow::resolve('PO vs PI mismatch', $no, 'PI matched');
            Workflow::resolve('Bank details changed', $no, 'PI matched');
            Workflow::log('PI matched / approved', $no, 'Approved for import processing', ['cid' => $c->id]);
        });
    }

    /** ACT.piClar: log a clarification / change request to the supplier. */
    public function clarify(string $no): void
    {
        Perm::need('pi.record');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no) {
            $this->need($c, $po);
            Workflow::log('Clarification sent', $no, 'PI mismatch — supplier asked to correct PI', ['cid' => $c->id]);
        });
    }

    /** ACT.piBank: dual control on a changed beneficiary bank. k = fin (Finance, bank.verify1) | sup (Supervisor, bank.verify2). */
    public function bank(string $no, string $k): void
    {
        if (!in_array($k, ['fin', 'sup'], true)) {
            throw new BusinessRule('Unknown verification step.');
        }
        Perm::need($k === 'fin' ? 'bank.verify1' : 'bank.verify2');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no, $k) {
            $pi = $this->need($c, $po);
            if (empty($pi['bankChanged'])) {
                throw new BusinessRule('Beneficiary bank is unchanged — nothing to verify.');
            }
            if (!empty($pi['approved'])) {
                throw new BusinessRule('PI already approved.');
            }
            $c->s['pis'][$no]['bankV'][$k] = true;
            Workflow::log('Bank detail verified', $no, $k === 'fin' ? 'Finance callback' : 'Supervisor second check', ['cid' => $c->id]);
        });
    }

    // ------------------------------------------------------------------ import docs / LC

    /** ACT.impDoc: tick / untick one checklist item (only after PI approval, before the checks are completed). */
    public function doc(string $no, int $i, bool $checked): void
    {
        Perm::need('imp.docs');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no, $i, $checked) {
            $pi = $this->need($c, $po);
            if (empty($pi['approved'])) {
                throw new BusinessRule('Approve the PI before the import checks.');
            }
            $l = self::lcOf($c, $no);
            if ($l['docsDone']) {
                throw new BusinessRule('Import checks are already complete.');
            }
            if ($i < 0 || $i >= count(self::IMPDOCS)) {
                throw new BusinessRule('Unknown checklist item.');
            }
            $l['docs'][$i] = $checked;
            $c->s['lc'][$no] = $l;
        });
    }

    /** ACT.impDone */
    public function docsDone(string $no): void
    {
        Perm::need('imp.docs');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no) {
            $pi = $this->need($c, $po);
            if (empty($pi['approved'])) {
                throw new BusinessRule('Approve the PI before the import checks.');
            }
            $l = self::lcOf($c, $no);
            foreach (array_keys(self::IMPDOCS) as $i) {
                if (empty($l['docs'][$i])) {
                    throw new BusinessRule('Tick every item of the import & banking checklist first.');
                }
            }
            $l['docsDone'] = true;
            $c->s['lc'][$no] = $l;
            Workflow::log('Import checks complete', $no, '', ['cid' => $c->id]);
        });
    }

    /** ACT.lcAppr: Finance control (lc.approve). Import cannot bypass it. */
    public function lcApprove(string $no): void
    {
        Perm::need('lc.approve');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no) {
            $l = self::lcOf($c, $no);
            if (!$l['docsDone']) {
                throw new BusinessRule('Complete the import & banking checks before LC approval.');
            }
            if ($l['appr']) {
                throw new BusinessRule('LC already approved.');
            }
            $l['appr'] = ['by' => Perm::user(), 'at' => Clock::ms()];
            $c->s['lc'][$no] = $l;
            Workflow::log('LC approved', $no, 'Finance control', ['cid' => $c->id]);
            Workflow::notify('info', "LC approved for {$no} — open the LC", 'imp.lc', ['role' => 'imp']);
        });
    }

    /**
     * ACT.lcSave: record the LC as opened (Import, lc.open) against the validated PI and approved PO.
     * Use [LC-REFERENCE] / [AD-BANK-NAME] style placeholders in demos - never real bank or LC identifiers.
     */
    public function lcOpen(string $no, string $bank, string $ref, string $expiry): void
    {
        Perm::need('lc.open');
        PoService::with($no, function (CaseBook $c, array &$po) use ($no, $bank, $ref, $expiry) {
            $pi = $this->need($c, $po);
            $l = self::lcOf($c, $no);
            if (!$l['appr']) {
                throw new BusinessRule('Finance must approve the LC before it can be opened.');
            }
            if ($l['open']) {
                throw new BusinessRule('LC already opened.');
            }
            if (empty($pi['approved'])) {
                throw new BusinessRule('Approve the PI first.');
            }
            if (trim($bank) === '' || trim($ref) === '') {
                throw new BusinessRule('Bank and LC reference are required.');
            }
            $exp = strtotime($expiry . 'T00:00:00Z');
            if ($exp === false) {
                throw new BusinessRule('LC expiry date is required.');
            }
            $amt = self::piTotal($pi);
            $l['open'] = ['ref' => trim($ref), 'bank' => trim($bank), 'amt' => $amt, 'exp' => $exp * 1000, 'at' => Clock::ms()];
            $c->s['lc'][$no] = $l;
            Workflow::log('LC opened', $no, trim($ref) . ' · USD ' . Fmt::n0($amt), ['cid' => $c->id]);
        });
    }
}
