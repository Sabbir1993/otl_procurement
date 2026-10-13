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
 * Award recommendation and the sequential approval chain Marketing ACK -> Finance APP -> ED final APP
 * (prototype submitAward / decide / apprSave / apprChgSave). submitAward() and decide() are the low-level
 * prototype functions (no permission check, caller saves); submit() / decideAs() / changeRequest() are the
 * guarded actions used by the controllers.
 */
class ApprovalService
{
    public const CHANGE_FIELDS = ['Supplier', 'Unit price', 'Currency', 'Ordered quantity', 'Incoterms', 'Payment terms', 'Delivery commitment', 'Significant quality deviation'];

    private function sumAmt(array $snap): float
    {
        return (float) array_sum(array_column($snap, 'amt'));
    }

    // ---------- prototype functions ----------

    /** Snapshot the CS as an immutable decision pack and route it to Marketing (prototype submitAward). */
    public function submitAward(CaseBook $c, ?string $by = null, ?string $summary = null): void
    {
        $A = $c->s['appr'];
        $A['version'] = ($A['version'] ?? 0) + 1;
        if (empty($A['id'])) {
            $A['id'] = Workflow::nid('AWD');
        }
        $now = Clock::ms();
        $A['status'] = 'mkt';
        $A['rec'] = ['by' => $by ?: Perm::user(), 'at' => $now];
        $A['mkt'] = $A['fin'] = $A['ed'] = null;
        $A['snap'] = $c->lineAwards();
        $A['chk'] = $c->awardChk();
        $A['summary'] = $summary ?: ($A['summary'] ?? '');
        unset($A['approvedChk']);
        $c->s['csLocked'] = true;
        $A['hist'][] = ['ts' => $now, 'a' => "Recommendation v{$A['version']} submitted", 'by' => $A['rec']['by'], 'r' => $A['summary']];
        $c->s['appr'] = $A;
        $names = array_values(array_unique(array_map(fn ($x) => Masters::supplier($x['sid'])['name'], $A['snap'])));
        Workflow::mail($c->id, 'H', 'Marketing approver', ['REQUEST_ID' => $A['id'], 'STAGE' => 'Marketing ACK', 'SUPPLIER_NAME' => implode(', ', $names),
            'AMOUNT' => Fmt::usd($this->sumAmt($A['snap'])), 'ACTION_LABEL' => 'Acknowledge / Return', 'APPROVER_NAME' => 'Marketing']);
        Workflow::notify('info', "{$A['id']} v{$A['version']} needs Marketing ACK", 'aw.appr', ['role' => 'mkt']);
        Workflow::log('Award submitted', $A['id'], "v{$A['version']} · checksum {$A['chk']}", ['cid' => $c->id] + ($by ? ['by' => $by] : []));
    }

    /** Record the decision of the current step and move the package on (prototype decide). $d = ok | ret | rej. */
    public function decide(CaseBook $c, string $d, ?string $remark, ?string $by = null): void
    {
        $A = $c->s['appr'];
        $key = $A['status'];
        $steps = CaseBook::APPR_STEPS;
        $now = Clock::ms();
        $who = $by ?: Perm::user();
        $A[$key] = ['d' => $d === 'ok' ? 'ok' : 'no', 'by' => $who, 'at' => $now, 'r' => $remark];
        $A['hist'][] = ['ts' => $now, 'a' => "{$steps[$key]} — " . ($d === 'ok' ? ($key === 'mkt' ? 'Acknowledged' : 'Approved') : ($d === 'ret' ? 'Returned' : 'Rejected')), 'by' => $who, 'r' => $remark];
        Workflow::log("{$steps[$key]}: {$d}", $A['id'], $remark ?? '', ['cid' => $c->id] + ($by ? ['by' => $by] : []));
        if ($d === 'ok') {
            $A['status'] = $key === 'mkt' ? 'fin' : ($key === 'fin' ? 'ed' : 'approved');
            $c->s['appr'] = $A;
            if ($A['status'] === 'approved') {
                $A['approvedChk'] = $A['chk'];
                $c->s['appr'] = $A;
                app(PoService::class)->generate($c);
                Workflow::notify('ok', "{$A['id']} approved — POs generated", 'po.reg', ['role' => 'exec']);
            } else {
                $nx = $A['status'];
                Workflow::mail($c->id, 'H', ($nx === 'fin' ? 'Finance' : 'ED') . ' approver', ['REQUEST_ID' => $A['id'], 'STAGE' => $steps[$nx], 'SUPPLIER_NAME' => implode(', ', array_values(array_unique(array_map(fn ($x) => Masters::supplier($x['sid'])['name'], $A['snap'] ?? [])))) ?: 'See award package',
                    'AMOUNT' => Fmt::usd($this->sumAmt($A['snap'] ?? [])), 'ACTION_LABEL' => 'Approve / Return / Reject', 'APPROVER_NAME' => $nx === 'fin' ? 'Finance' : 'ED']);
                Workflow::notify('info', "{$A['id']} needs {$steps[$nx]}", 'aw.appr', ['role' => $nx]);
            }
        } else {
            $A['status'] = $d === 'ret' ? 'returned' : 'rejected';
            $c->s['csLocked'] = false;
            $c->s['appr'] = $A;
            Workflow::notify('bad', "{$A['id']} {$A['status']} at {$steps[$key]}", 'aw.appr', ['role' => 'exec']);
        }
    }

    // ---------- guarded actions ----------

    /** Final recommendation -> approval (ACT.awSubmitForm + awSubmit). */
    public function submit(string $cid, string $summary): void
    {
        Perm::need('cs.submit');
        CaseBook::tx($cid, function (CaseBook $c) use ($summary) {
            $A = $c->s['appr'];
            if (!empty($c->s['csLocked']) || $A['status'] === 'approved' || in_array($A['status'], ['mkt', 'fin', 'ed'], true)) {
                throw new BusinessRule('The package is already in approval or approved. Use a change request to reopen it.');
            }
            if (!$c->rfqClosed()) {
                throw new BusinessRule('RFQ must be closed (deadline, revoke or cut-off) before submitting for approval.');
            }
            if ($c->csCounts()['pend'] > 0) {
                throw new BusinessRule($c->csCounts()['pend'] . ' product(s) are still pending — decide or exclude each in the comparative statement.');
            }
            $bad = [];
            foreach ($c->s['booking']['lines'] as $l) {
                if ($c->csStat($l['pid']) !== 'Excluded' && $c->needsJust($l['pid']) && trim($c->csOf($l['pid'])['just'] ?? '') === '') {
                    $bad[] = $l['pid'];
                }
            }
            if ($bad) {
                throw new BusinessRule('Justification required for ' . implode(', ', $bad) . '.');
            }
            if (!$c->lineAwards()) {
                throw new BusinessRule('Nothing is awarded.');
            }
            if (trim($summary) === '') {
                throw new BusinessRule('Enter the procurement summary / rationale.');
            }
            $this->submitAward($c, null, trim($summary));
        });
    }

    /** Marketing / Finance / ED decision on the current step (ACT.apprSave). */
    public function decideAs(string $cid, string $d, ?string $remark): void
    {
        if (!in_array($d, ['ok', 'ret', 'rej'], true)) {
            throw new BusinessRule('Choose approve, return or reject.');
        }
        $c0 = CaseBook::findOrFail($cid);
        $st = $c0->s['appr']['status'];
        if (!in_array($st, ['mkt', 'fin', 'ed'], true)) {
            throw new BusinessRule('This package is not waiting for a decision.');
        }
        Perm::need('appr.' . $st);
        CaseBook::tx($cid, function (CaseBook $c) use ($d, $remark, $st) {
            $A = $c->s['appr'];
            $remark = trim((string) $remark);
            if ($d !== 'ok' && $remark === '') {
                throw new BusinessRule('Remarks are mandatory for return / reject.');
            }
            if ($d === 'rej' && $st === 'mkt') {
                throw new BusinessRule('Marketing acknowledges or returns for clarification; rejection is for Finance and ED.');
            }
            // one named reviewer per step, no self-approval
            $prev = array_filter([$A['rec']['by'] ?? null, $A['mkt']['by'] ?? null, $A['fin']['by'] ?? null]);
            if (in_array(Perm::user(), $prev, true)) {
                throw new BusinessRule('You already took part in this package (' . Perm::user() . ') — a different reviewer must decide this step.');
            }
            if ($d === 'ok' && $A['chk'] !== $c->awardChk()) {
                throw new BusinessRule('Decision pack changed since submission — resubmit.');
            }
            $this->decide($c, $d, $remark === '' ? null : $remark);
        });
    }

    /** Change request after approval (ACT.apprChgSave): freezes unsent POs and routes the case for re-approval. */
    public function changeRequest(string $cid, string $field, string $reason): void
    {
        if (!(Perm::can('appr.change') || Perm::can('cs.negotiate'))) {
            Perm::need('appr.change');
        }
        if (!in_array($field, self::CHANGE_FIELDS, true)) {
            throw new BusinessRule('Choose the field to change.');
        }
        if (trim($reason) === '') {
            throw new BusinessRule('Give the reason for the change.');
        }
        CaseBook::tx($cid, function (CaseBook $c) use ($field, $reason) {
            $A = $c->s['appr'];
            if ($A['status'] !== 'approved') {
                throw new BusinessRule('Change requests apply to an approved package only.');
            }
            $A['status'] = 'returned';
            $c->s['csLocked'] = false;
            $A['hist'][] = ['ts' => Clock::ms(), 'a' => "Change request raised — {$field}", 'by' => Perm::user(), 'r' => trim($reason)];
            $c->s['appr'] = $A;
            foreach ($c->s['pos'] as $i => $p) {
                if (empty($p['sent'])) {
                    $c->s['pos'][$i]['frozen'] = true;
                }
            }
            Workflow::raise('Change after approval', $A['id'], "{$field}: " . trim($reason), ['cid' => $c->id]);
            Workflow::log('Change request', $A['id'], "{$field} · " . trim($reason), ['cid' => $c->id]);
        });
    }
}
