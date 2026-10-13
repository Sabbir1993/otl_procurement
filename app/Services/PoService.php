<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\EmailTemplate;

/**
 * Purchase orders (prototype generatePOs, validatePO, poStatus, dispatchPO, poAck + the PO register / acknowledgment /
 * dispute actions). generate() is called by the approval flow (ApprovalService, final ED approval).
 *
 * Domain-level methods (generate, acknowledge, validate, status, dispatch, vars) mutate $c->s and log; callers save inside
 * CaseBook::tx. Action methods (genDrafts, dispatchMany, ... ) enforce Perm::need and run their own transaction.
 */
class PoService
{
    // ------------------------------------------------------------------ lookup helpers

    /** Case that owns a PO number (null if unknown). */
    public static function locate(string $no): ?CaseBook
    {
        foreach (CaseBook::all() as $c) {
            foreach ($c->s['pos'] as $p) {
                if ($p['no'] === $no) {
                    return $c;
                }
            }
        }
        return null;
    }

    /** Array index of a PO inside a case, or throws BusinessRule. */
    public static function idx(CaseBook $c, string $no): int
    {
        foreach ($c->s['pos'] as $i => $p) {
            if ($p['no'] === $no) {
                return $i;
            }
        }
        throw new BusinessRule("Purchase order {$no} not found.");
    }

    /** Run $fn(CaseBook $c, array &$po) inside a transaction on the case that owns $no; returns the callback result. */
    public static function with(string $no, callable $fn)
    {
        $c0 = self::locate($no) ?? throw new BusinessRule("Purchase order {$no} not found.");
        return CaseBook::tx($c0->id, function (CaseBook $c) use ($no, $fn) {
            $po = &$c->s['pos'][self::idx($c, $no)];
            return $fn($c, $po);
        });
    }

    /** All POs of all cases, each tagged with its CaseBook as `_c` and `id` (prototype poRows / allPOs). */
    public static function rows(): array
    {
        $out = [];
        foreach (CaseBook::all() as $c) {
            foreach ($c->s['pos'] as $p) {
                $out[] = $p + ['_c' => $c, 'id' => $p['no']];
            }
        }
        return $out;
    }

    /** PO number incl. revision suffix (prototype poFull). */
    public static function full(array $po): string
    {
        return ($po['rev'] ?? 1) > 1 ? "{$po['no']}-R{$po['rev']}" : $po['no'];
    }

    // ------------------------------------------------------------------ generation / acknowledgment

    /**
     * Create one PO per awarded supplier from the approved award (prototype generatePOs). Replaces $c->s['pos'].
     * A frozen PO (material change accepted after a supplier dispute) is re-issued under the SAME number with rev+1
     * (suffix -R2 ...) - a different legal PO is never created under the same identifier without a revision (AC-16).
     * A PO that was already sent and whose lines are unchanged by the re-approval is kept as is (no duplicate PO to the supplier).
     */
    public function generate(CaseBook $c): void
    {
        $by = [];
        foreach ($c->lineAwards() as $x) {
            $by[$x['sid']][] = $x;
        }
        $st = Masters::setting('settings', []);
        $A = $c->s['appr'];
        $now = Clock::ms();
        $old = [];
        foreach ($c->s['pos'] ?? [] as $p) {
            $old[$p['sid']] = $p;
        }
        $pos = [];
        $revs = 0;
        foreach ($by as $sid => $lines) {
            $q0 = $c->s['quotes'][$sid][$lines[0]['pid']];
            $H = $c->hdrOf($sid);
            $prev = $old[$sid] ?? null;
            $sig = fn (array $ls) => json_encode(array_map(fn ($x) => [$x['pid'], (float) $x['qty'], round((float) $x['price'], 6)], $ls));
            if ($prev && empty($prev['frozen']) && !empty($prev['sent']) && $sig($prev['lines']) === $sig($lines)) {
                $pos[] = ['ver' => $A['version'], 'chk' => $A['chk']] + $prev;      // already with the supplier and unchanged: keep it as is
                continue;
            }
            $no = $prev && !empty($prev['sent']) ? $prev['no'] : Workflow::nid('PO');
            $rev = 1;
            $hist = [];
            if ($prev && (!empty($prev['frozen']) || !empty($prev['sent']))) {      // amendment of a PO the supplier has seen: same number, next revision
                $no = $prev['no'];
                $rev = ($prev['rev'] ?? 1) + 1;
                $hist = array_merge($prev['prev'] ?? [], [['rev' => $prev['rev'] ?? 1, 'total' => $prev['total'], 'sentAt' => $prev['sentAt'] ?? null, 'ack' => $prev['ack'], 'dispute' => $prev['dispute'] ?? '', 'chk' => $prev['chk']]]);
                $revs++;
            }
            $pos[] = [
                'no' => $no, 'cid' => $c->id, 'sid' => $sid, 'rev' => $rev, 'prev' => $hist,
                'lines' => array_map(function ($x) use ($c, $sid) {
                    $spec = $c->s['quotes'][$sid][$x['pid']]['spec'] ?? [];
                    return $x + ['name' => Masters::spName($sid, $x['pid']), 'hs' => $spec['hs'] ?? '', 'pack' => implode(', ', array_filter([$spec['pkgType'] ?? '', $spec['rollLen'] ?? '']))];
                }, $lines),
                'total' => array_sum(array_column($lines, 'amt')), 'inc' => $q0['incoterm'], 'place' => $q0['place'], 'payment' => $q0['payment'],
                'claims' => $H['claims'] ?? '', 'partial' => $H['partial'] ?? '', 'transship' => $H['transship'] ?? '', 'origin' => $H['origin'] ?? '', 'dest' => $H['dischargePort'] ?? '',
                'ship' => $now + max(array_column($lines, 'days')) * Clock::DAY,
                'shipTo' => $st['shipTo'] ?? '', 'billTo' => $st['billTo'] ?? '', 'tol' => $st['tol'] ?? '', 'packing' => $st['packing'] ?? '', 'docs' => $st['docs'] ?? '', 'notes' => '',
                'sent' => false, 'status' => '', 'ack' => '—', 'sentAt' => null, 'msgId' => '', 'draft' => false, 'frozen' => false,
                'chk' => $A['chk'], 'ver' => $A['version'], 'devOk' => [], 'tries' => 0, 'created' => $now,
            ];
        }
        $c->s['pos'] = $pos;
        Workflow::log('POs generated', $A['id'], count($pos) . ' PO(s) from approved fields' . ($revs ? " · {$revs} revision(s) of frozen PO(s)" : ''), ['cid' => $c->id, 'by' => 'System']);
    }

    /**
     * Supplier acknowledgment: Accept or Dispute only (prototype poAck). $po is a reference into $c->s['pos'].
     * Used by the supplier portal (C) and the PO register (E). Caller saves the case.
     */
    public function acknowledge(CaseBook $c, array &$po, string $decision, ?string $reason = null): void
    {
        $sp = Masters::supplier($po['sid']);
        $po['ackAt'] = Clock::ms();
        if ($decision === 'ok') {
            $po['ack'] = 'Accepted';
            Workflow::log('PO accepted', $po['no'], $sp['name'], ['cid' => $c->id, 'by' => 'Supplier']);
            return;
        }
        $po['ack'] = 'Disputed';
        $po['dispute'] = $reason;
        Workflow::raise('PO dispute', $po['no'], "{$sp['name']}: {$reason}", ['cid' => $c->id]);
        Workflow::log('PO disputed', $po['no'], (string) $reason, ['cid' => $c->id, 'by' => 'Supplier']);
    }

    // ------------------------------------------------------------------ validation / status

    /** Lines with an MOQ waiver that still lack the supplier's written acceptance (prototype poDev). */
    public function dev(CaseBook $c, array $po): array
    {
        return array_values(array_filter($po['lines'], function ($l) use ($c, $po) {
            $w = (array) ($c->csOf($l['pid'])['waiver'] ?? []);
            return array_key_exists($po['sid'], $w) && empty($po['devOk'][$l['pid']]);
        }));
    }

    /** The five automatic dispatch conditions (prototype validatePO): list of ['k' => name, 'ok' => bool, 'why' => text]. */
    public function validate(CaseBook $c, array $po): array
    {
        $A = $c->s['appr'];
        $sp = Masters::supplier($po['sid']);
        $dev = $this->dev($c, $po);
        $linesOk = true;
        foreach ($po['lines'] as $l) {
            if (!($l['qty'] > 0 && $l['price'] > 0)) {
                $linesOk = false;
            }
        }
        $req = !empty($po['inc']) && !empty($po['place']) && !empty($po['payment']) && !empty($po['ship']) && !empty($po['shipTo']) && !empty($po['billTo']) && !empty($po['tol']) && !empty($po['claims']) && $linesOk;
        $sampleFail = (bool) array_filter($po['lines'], fn ($l) => ($l['sample'] ?? '') === 'Fail');
        return [
            ['k' => 'Final approval valid', 'ok' => $A['status'] === 'approved' && ($A['approvedChk'] ?? null) === $A['chk'] && empty($po['frozen']),
                'why' => !empty($po['frozen']) ? 'Frozen — material change awaiting re-approval' : 'Final approval missing or pack changed'],
            ['k' => 'PO source = final approved version', 'ok' => $po['ver'] === $A['version'] && $po['chk'] === ($A['approvedChk'] ?? null),
                'why' => 'PO built from a superseded version'],
            ['k' => 'Required fields complete', 'ok' => $req,
                'why' => empty($po['place']) ? 'Incoterms named place missing' : (empty($po['shipTo']) || empty($po['billTo']) ? 'Ship-to / bill-to address missing' : (empty($po['claims']) ? 'Claims / replacement provisions missing' : 'Mandatory PO field empty'))],
            ['k' => 'Supplier email verified', 'ok' => !empty($sp['emailVerified']),
                'why' => 'Contact changed — independent callback verification needed'],
            ['k' => 'No unresolved blocking deviations', 'ok' => !$sampleFail && !$dev,
                'why' => $dev ? 'MOQ waiver needs supplier written acceptance' : 'Failed sample on a line'],
        ];
    }

    /** Failed checks only. */
    public function failing(CaseBook $c, array $po): array
    {
        return array_values(array_filter($this->validate($c, $po), fn ($x) => !$x['ok']));
    }

    /** Ready to Dispatch | Correction queue | Scheduled | Frozen | Sent | Delivery Failed (prototype poStatus). */
    public function status(CaseBook $c, array $po): string
    {
        if (!empty($po['frozen'])) {            // a PO frozen by an accepted dispute shows as Frozen even though it was sent
            return 'Frozen';
        }
        if (!empty($po['sent'])) {
            return $po['status'];
        }
        if (($po['status'] ?? '') === 'Scheduled') {
            return 'Scheduled';
        }
        return $this->failing($c, $po) ? 'Correction queue' : 'Ready to Dispatch';
    }

    /** Template variables for mail templates PO / G (prototype poVars). */
    public function vars(CaseBook $c, array $po): array
    {
        $sp = Masters::supplier($po['sid']);
        return [
            'PO_NUMBER' => self::full($po), 'RFQ_NUMBER' => $c->s['rfq']['no'], 'SUPPLIER_CONTACT_NAME' => $sp['contact'] ?? '',
            'PRODUCTS' => implode('; ', array_map(fn ($l) => Masters::material($l['pid'])['name'] . " ({$l['name']})", $po['lines'])),
            'QUANTITY' => implode(' + ', array_map(fn ($l) => Fmt::n0($l['qty']) . ' ' . Masters::material($l['pid'])['uom'], $po['lines'])),
            'PO_TOTAL' => 'USD ' . Fmt::n0($po['total']), 'INCOTERMS_NAMED_PLACE' => "{$po['inc']} {$po['place']}", 'SHIPMENT' => Fmt::dD($po['ship']),
            'PAYMENT_TERMS' => $po['payment'], 'PO_ACK_LINK' => ($iv = $c->invOf($po['sid'])) ? RfqService::linkFor($c->id, $iv) . '/po' : \App\Support\Link::to('/'), 'ACK_DEADLINE' => '[ACK SLA — Policy Item 8]',
        ];
    }

    /** Rendered template (for the Email tab preview). */
    public function preview(CaseBook $c, array $po, string $code = 'PO'): array
    {
        $t = EmailTemplate::findOrFail($code);
        $v = $this->vars($c, $po) + ['CONTACT_EMAIL' => '[OIL_CONTACT_EMAIL]'];
        return ['subject' => Workflow::fill($t->subject, $v), 'body' => Workflow::fill($t->body, $v)];
    }

    // ------------------------------------------------------------------ dispatch

    /**
     * Send the approved PO to ITS supplier only (prototype dispatchPO). Returns true when sent.
     * $by = 'System' (seeding / demo loader) skips the validation gate and the e-mail failure simulation, like the prototype.
     * Interactive dispatch (by = null) is gated by the five checks: a failing PO is NEVER mailed - it stays in the correction
     * queue and an exception is raised (AC-14). When the e-mail provider fails (settings flag flags.emailFail) the PO is held
     * as 'Delivery Failed' and can be retried with the SAME PO number (AC-19).
     */
    public function dispatch(CaseBook $c, array &$po, ?int $ts = null, ?string $by = null): bool
    {
        if (!$by) {
            $bad = $this->failing($c, $po);
            if ($bad) {
                $this->block($c, $po, $bad);
                return false;
            }
        }
        $o = ['cid' => $c->id] + ($by ? ['by' => $by] : []) + ($ts ? ['ts' => $ts] : []);
        if (!empty(Masters::setting('flags', [])['emailFail']) && !$by) {
            $po['tries'] = ($po['tries'] ?? 0) + 1;
            $po['sent'] = true;
            $po['status'] = 'Delivery Failed';
            $po['sentAt'] = null;
            Workflow::raise('PO email failed', $po['no'], 'Email provider failure — PO held, safe retry available', ['cid' => $c->id]);
            Workflow::log('PO delivery failed', $po['no'], 'Held for retry (no new PO number)', ['cid' => $c->id]);
            return false;
        }
        $sp = Masters::supplier($po['sid']);
        $m = Workflow::mail($c->id, 'PO', "{$sp['contact']} <{$sp['email']}>", $this->vars($c, $po), ['ts' => $ts, 'pid' => $po['no']]);
        $po['sent'] = true;
        $po['status'] = 'Sent';
        $po['sentAt'] = $ts ?: Clock::ms();
        $po['ack'] = 'Awaiting';
        $po['msgId'] = $m->id;
        $po['tries'] = ($po['tries'] ?? 0) + 1;
        Workflow::resolve('PO email failed', $po['no'], 'Retried successfully · same PO number');
        Workflow::log('PO sent', $po['no'], "{$sp['name']} · {$po['msgId']}", $o);
        return true;
    }

    /** Dispatch blocked by failed checks: audit + exception (AC-14). */
    private function block(CaseBook $c, array $po, array $bad): void
    {
        $o = ['cid' => $c->id];
        $why = implode('; ', array_column($bad, 'why'));
        foreach ($bad as $x) {
            if ($x['k'] === 'Required fields complete' && empty($po['place'])) {
                Workflow::raise('Incoterms without place', $po['no'], "PO " . self::full($po) . ': Incoterms named place missing — auto-email blocked', $o);
            } elseif ($x['k'] === 'Supplier email verified') {
                Workflow::raise('Supplier contact changed', $po['sid'], 'Callback verification needed before any PO is sent (' . self::full($po) . ').', $o);
            } elseif (str_starts_with($x['k'], 'No unresolved') && $this->dev($c, $po)) {
                Workflow::raise('MOQ waiver', $po['no'], 'MOQ waiver needs the supplier’s written acceptance before the PO is sent.', $o);
            }
        }
        Workflow::log('PO dispatch blocked', $po['no'], $why, $o);
    }

    // ------------------------------------------------------------------ register actions

    /** poGen: mark every unsent PO of a case as a draft e-mail. */
    public function genDrafts(string $caseId): int
    {
        Perm::need('po.dispatch');
        return CaseBook::tx($caseId, function (CaseBook $c) {
            $n = 0;
            foreach ($c->s['pos'] as &$p) {
                if (empty($p['sent'])) {
                    $p['draft'] = true;
                    $n++;
                }
            }
            unset($p);
            if (!$c->s['pos']) {
                throw new BusinessRule('This case has no purchase orders yet — POs are generated after ED final approval.');
            }
            Workflow::log('PO drafts generated', $c->s['appr']['id'], count($c->s['pos']) . " drafts · {$c->s['sender']}", ['cid' => $c->id]);
            return $n;
        });
    }

    /**
     * poSend: dispatch the selected POs. Only Ready-to-Dispatch drafts are sent, each to its own supplier (AC-15).
     * Selected POs that fail the checks are blocked + exception raised (AC-14).
     * @return array{sent:int,failed:int,blocked:int,skipped:int}
     */
    public function dispatchMany(array $nos): array
    {
        Perm::need('po.dispatch');
        $r = ['sent' => 0, 'failed' => 0, 'blocked' => 0, 'skipped' => 0];
        foreach (array_unique($nos) as $no) {
            self::with($no, function (CaseBook $c, array &$po) use (&$r) {
                if (!empty($po['sent'])) {
                    $r['skipped']++;
                } elseif (empty($po['draft'])) {
                    $r['skipped']++;                       // drafts must be generated first
                } elseif ($this->status($c, $po) === 'Ready to Dispatch') {
                    $this->dispatch($c, $po) ? $r['sent']++ : $r['failed']++;
                } else {
                    $this->block($c, $po, $this->failing($c, $po));
                    $r['blocked']++;
                }
            });
        }
        return $r;
    }

    /** poDispatch1. @return string sent|failed|blocked */
    public function dispatchOne(string $no): string
    {
        Perm::need('po.dispatch');
        return self::with($no, function (CaseBook $c, array &$po) {
            if (!empty($po['sent'])) {
                throw new BusinessRule('PO already sent.');
            }
            if ($this->status($c, $po) !== 'Ready to Dispatch') {
                $this->block($c, $po, $this->failing($c, $po));
                return 'blocked';
            }
            $po['draft'] = true;
            return $this->dispatch($c, $po) ? 'sent' : 'failed';
        });
    }

    /** poSchedSave: schedule selected Ready POs ($at = datetime-local string, Bangladesh time). */
    public function schedule(array $nos, string $at): int
    {
        Perm::need('po.dispatch');
        $t = strtotime(trim($at) . '+06:00');
        if ($t === false) {
            throw new BusinessRule('Choose a valid send time.');
        }
        $n = 0;
        foreach (array_unique($nos) as $no) {
            self::with($no, function (CaseBook $c, array &$po) use ($t, &$n, $at) {
                if (empty($po['sent']) && !empty($po['draft']) && $this->status($c, $po) === 'Ready to Dispatch') {
                    $po['status'] = 'Scheduled';
                    $po['at'] = $t * 1000;
                    Workflow::log('PO scheduled', $po['no'], "{$at} (Bangladesh time)", ['cid' => $c->id]);
                    $n++;
                }
            });
        }
        if (!$n) {
            throw new BusinessRule('Select PO(s) that are Ready to Dispatch (generate drafts first).');
        }
        return $n;
    }

    /** poRun: dispatch every scheduled PO (the demo "scheduler tick"). @return array{sent:int,failed:int} */
    public function runDue(): array
    {
        Perm::need('po.dispatch');
        $r = ['sent' => 0, 'failed' => 0];
        foreach (CaseBook::all() as $c0) {
            foreach ($c0->s['pos'] as $p0) {
                if (($p0['status'] ?? '') !== 'Scheduled' || !empty($p0['sent'])) {
                    continue;
                }
                self::with($p0['no'], function (CaseBook $c, array &$po) use (&$r) {
                    $at = $po['at'] ?? null;
                    $po['status'] = '';
                    $this->dispatch($c, $po, $at ? min($at, Clock::ms()) : null) ? $r['sent']++ : $r['failed']++;
                });
            }
        }
        if (!$r['sent'] && !$r['failed']) {
            throw new BusinessRule('Nothing scheduled.');
        }
        return $r;
    }

    /** poRetry: re-send a held PO — same PO number, no duplicate (AC-19). Clears the simulated provider failure. */
    public function retry(string $no): bool
    {
        Perm::need('po.dispatch');
        return self::with($no, function (CaseBook $c, array &$po) {
            if (($po['status'] ?? '') !== 'Delivery Failed') {
                throw new BusinessRule('Only a PO held after a delivery failure can be retried.');
            }
            $po['sent'] = false;
            $f = Masters::setting('flags', []);
            if (!empty($f['emailFail'])) {
                $f['emailFail'] = false;
                Masters::putSetting('flags', $f);
            }
            $ok = $this->dispatch($c, $po);
            if ($ok) {
                Workflow::log('PO re-sent', $po['no'], 'Safe retry — same PO number, no duplicate', ['cid' => $c->id]);
            }
            return $ok;
        });
    }

    /** poRem: reminder (template G) to the supplier. */
    public function remind(string $no): void
    {
        Perm::need('rfq.remind');
        self::with($no, function (CaseBook $c, array &$po) {
            if (empty($po['sent']) || $po['ack'] !== 'Awaiting') {
                throw new BusinessRule('A reminder only makes sense for a sent PO that awaits acknowledgment.');
            }
            $sp = Masters::supplier($po['sid']);
            Workflow::mail($c->id, 'G', "{$sp['contact']} <{$sp['email']}>", $this->vars($c, $po), ['pid' => $po['no']]);
            Workflow::log('PO reminder', $po['no'], $sp['name'], ['cid' => $c->id]);
        });
    }

    // ------------------------------------------------------------------ disputes / corrections

    /**
     * poResSave: record the outcome of a supplier dispute. 'stand' = PO stands (dispute rejected, back to Awaiting);
     * 'chg' = accept change -> PO frozen, award returned for approved revision (AC-16).
     */
    public function resolveDispute(string $no, string $outcome, string $note): void
    {
        Perm::need('exc.resolve');
        if (trim($note) === '') {
            throw new BusinessRule('A resolution note is required.');
        }
        self::with($no, function (CaseBook $c, array &$po) use ($outcome, $note) {
            if ($po['ack'] !== 'Disputed') {
                throw new BusinessRule("{$po['no']} has no open dispute.");
            }
            if ($outcome === 'stand') {
                $po['ack'] = 'Awaiting';
                $po['dispute'] = '';
                Workflow::log('Dispute rejected', $po['no'], $note, ['cid' => $c->id]);
            } else {
                $A = &$c->s['appr'];
                $A['status'] = 'returned';
                $c->s['csLocked'] = false;
                $po['frozen'] = true;
                $A['hist'][] = ['ts' => Clock::ms(), 'a' => 'Change request from PO dispute ' . $po['no'], 'by' => Perm::user(), 'r' => $note];
                Workflow::raise('Change after approval', $A['id'], "PO {$po['no']} dispute accepted — reapproval needed", ['cid' => $c->id]);
                Workflow::log('Dispute → change request', $po['no'], $note, ['cid' => $c->id]);
            }
            Workflow::resolve('PO dispute', $po['no'], $note);
        });
    }

    /** poEditSave: operational fields only (commercial terms are locked by the approval). */
    public function edit(string $no, array $d): void
    {
        Perm::need('po.edit');
        self::with($no, function (CaseBook $c, array &$po) use ($d) {
            if (!empty($po['sent'])) {
                throw new BusinessRule('PO already sent — changes need a versioned revision.');
            }
            if (!empty($po['frozen'])) {
                throw new BusinessRule('PO is frozen — it will be re-issued after re-approval.');
            }
            foreach (['billTo', 'shipTo', 'tol', 'packing', 'docs', 'notes'] as $k) {
                $po[$k] = trim((string) ($d[$k] ?? ''));
            }
            Workflow::log('PO details edited', $po['no'], 'Operational fields', ['cid' => $c->id]);
        });
    }

    /** poDevOk: supplier's written acceptance of the MOQ waiver recorded. */
    public function devOk(string $no): void
    {
        Perm::need('po.correct');
        self::with($no, function (CaseBook $c, array &$po) {
            $dev = $this->dev($c, $po);
            if (!$dev) {
                throw new BusinessRule('No MOQ waiver is waiting for acceptance on this PO.');
            }
            foreach ($dev as $l) {
                $po['devOk'][$l['pid']] = true;
            }
            Workflow::resolve('MOQ waiver', $po['no'], 'Supplier written acceptance recorded');
            Workflow::log('MOQ waiver accepted', $po['no'], 'Supplier written acceptance recorded', ['cid' => $c->id]);
        });
    }
}
