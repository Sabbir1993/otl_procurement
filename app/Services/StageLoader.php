<?php

namespace App\Services;

use App\Domain\AdminCatalog;
use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\ExceptionRecord;
use App\Models\Supplier;

/**
 * Demo utility "Load a case to a stage" (prototype loadStage / buildCase / STEPS).
 * Rebuilds ONE case from its requisition lines up to the chosen stage by driving the real services
 * (Domain-level methods that take a CaseBook, no Perm checks - same as the prototype's System actor).
 * Quotation figures come from the seeded October quotations, re-scaled to the case's booked quantities.
 */
class StageLoader
{
    private const ORDER = ['req', 'booking', 'rfq', 'quotes', 'cs', 'appr', 'po', 'ack', 'pi', 'lc', 'done'];

    public function load(string $cid, string $stage): string
    {
        if (!in_array($stage, self::ORDER, true)) {
            throw new BusinessRule('Unknown stage.');
        }
        $full = $cid === 'IC-2026-09' && $stage === 'done';
        CaseBook::tx($cid, function (CaseBook $c) use ($cid, $stage, $full) {
            $s = CaseBook::blankState($c->t0, $c->s['notes'] ?? '');
            $s['demand'] = $c->s['demand'];
            foreach ($s['demand'] as &$d) {
                $d['status'] = 'pending';
                $d['reason'] = '';
                $d['remark'] = '';
            }
            unset($d);
            $c->s = $s;
            ExceptionRecord::where('case_id', $cid)->delete();
            $this->restoreSuppliers();
            $up = array_search($stage, self::ORDER, true);
            if ($up === 0) {
                $this->req($c);
            } else {
                for ($i = 1; $i <= $up; $i++) {
                    $this->{'step' . ucfirst(self::ORDER[$i])}($c, $full);
                }
            }
            Workflow::log('Case loaded to stage', $cid, $stage, ['cid' => $cid]);
        });
        Masters::flush();
        foreach (AdminCatalog::STAGES as $x) {
            if ($x[0] === $stage) {
                return $x[1];
            }
        }
        return $stage;
    }

    // ------------------------------------------------------------------ helpers

    private function seedCase(string $id = 'IC-2026-10'): array
    {
        $src = json_decode(file_get_contents(database_path('seeders/data/prototype-db.json')), true);
        foreach ($src['db']['cases'] as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        return [];
    }

    private function restoreSuppliers(): void
    {
        $src = json_decode(file_get_contents(database_path('seeders/data/prototype-db.json')), true);
        foreach ($src['db']['sup'] as $s) {
            Supplier::where('id', $s['id'])->update(['contact' => $s['contact'] ?? null, 'email' => $s['email'] ?? null, 'email_verified' => true]);
        }
    }

    private function req(CaseBook $c): void
    {
        foreach ($c->s['demand'] as &$d) {
            $n = (int) substr($d['id'], 1);
            if ($n >= 1 && $n <= 6) {
                $d['status'] = 'accepted';
            }
            if ($n === 9) {
                $d['status'] = 'rejected';
                $d['reason'] = Masters::setting('reasons', [])[0] ?? '';
            }
        }
    }

    private function stepBooking(CaseBook $c, bool $full): void
    {
        $r = Masters::setting('reasons', []);
        foreach ($c->s['demand'] as &$d) {
            if (preg_match('/09$/', $d['id'])) {
                $d['status'] = 'rejected';
                $d['reason'] = $r[0] ?? '';
            } elseif (preg_match('/16$/', $d['id'])) {
                $d['status'] = 'rejected';
                $d['reason'] = $r[1] ?? '';
            } else {
                $d['status'] = 'accepted';
            }
        }
        unset($d);
        app(RequisitionService::class)->freeze($c, 'System');
    }

    private function stepRfq(CaseBook $c, bool $full): void
    {
        app(RequisitionService::class)->confirmBooking($c, '[SIGNED-DOC-REF]', 'Farhana S.', $c->t0 - Clock::DAY, ['doc' => 'IMPBOK_signed_scan.pdf']);
        $rfq = app(RfqService::class);
        $rfq->generateDrafts($c, ['by' => 'System', 'pool' => $c->poolSup()]);
        $c->s['rfq']['reqFields'] = ['peel', 'shrink'];
        foreach ($c->s['drafts'] as $k => $_) {
            if ($c->s['drafts'][$k]['status'] === 'Draft') {
                $rfq->sendDraft($c, $c->s['drafts'][$k], $c->t0, 'System');
            }
        }
    }

    private function stepQuotes(CaseBook $c, bool $full): void
    {
        $seed = $this->seedCase();
        $sub = $full ? ['SUP-00001', 'SUP-00002', 'SUP-00003', 'SUP-00004'] : ['SUP-00001', 'SUP-00002', 'SUP-00003'];
        foreach ($c->s['inv'] as $n => $_) {
            $iv = &$c->s['inv'][$n];
            $sid = $iv['sid'];
            $sp = Masters::supplier($sid);
            if (in_array($sid, $sub, true)) {
                $c->s['quotes'][$sid] = [];
                foreach ($iv['pids'] as $pid) {
                    $q = $seed['quotes'][$sid][$pid] ?? null;
                    if ($q) {
                        $c->s['quotes'][$sid][$pid] = $this->rescale($q, (float) $c->reqQty($pid));
                    }
                }
                $iv['submitted'] = true;
                $iv['state'] = 'Submitted';
                $iv['submittedAt'] = $c->t0 + (int) (($n + 3) * Clock::DAY / 2);
                $iv['quoRef'] = Workflow::nid('QUO');
                $iv['rev'] = 1;
                $iv['ip'] = '58.246.xxx.xxx';
                $c->s['quoteHdr'][$sid] = $seed['quoteHdr'][$sid] ?? [];
                Workflow::mail($c->id, 'C', "{$sp['contact']} <{$sp['email']}>", $c->rfqVars($iv) + ['SUBMISSION_ID' => $iv['quoRef'], 'SUBMITTED_AT' => Fmt::dBD($iv['submittedAt'])], ['ts' => $iv['submittedAt']]);
                Workflow::log('Quotation submitted', $iv['quoRef'], $sp['name'], ['by' => 'Supplier', 'ts' => $iv['submittedAt'], 'cid' => $c->id]);
            } elseif ($sid === 'SUP-00004') {
                $iv['state'] = 'Draft started';
                $iv['ip'] = '113.108.xxx.xxx';
                Workflow::log('Draft saved', $iv['rfq'], $sp['name'], ['by' => 'Supplier', 'ts' => $c->t0 + Clock::DAY, 'cid' => $c->id]);
            }
            unset($iv);
        }
        if (!$full) {
            Supplier::where('id', 'SUP-00003')->update(['contact' => 'Zhao Wei', 'email' => 'zhao.wei@huanan.example', 'email_verified' => false]);
            Masters::flush();
            Workflow::raise('Supplier contact changed', 'SUP-00003', 'Kunshan Huanan changed PO contact during quotation — callback verification needed before any PO is sent.', ['by' => 'System', 'cid' => $c->id]);
        }
        app(RfqService::class)->checkNoBid($c, 'System');
    }

    /** Re-price a seeded quote line for another required quantity (freight / insurance / duty are quantity-driven). */
    private function rescale(array $q, float $qty): array
    {
        $old = (float) ($q['qty'] ?: 1);
        if ($qty > 0 && $qty != $old) {
            $k = $qty / $old;
            foreach (['freight', 'ins', 'duty'] as $f) {
                $q[$f] = (int) round($q[$f] * $k);
            }
            $q['qty'] = $qty;
            if (isset($q['spec']['qtyQuoted'])) {
                $q['spec']['qtyQuoted'] = $qty;
            }
        }
        return $q;
    }

    private function stepCs(CaseBook $c, bool $full): void
    {
        $cs = app(CsService::class);
        foreach ($c->s['booking']['lines'] as $l) {
            $b = $c->pick($l['pid'], 'cost');
            if ($b) {
                $cs->setFull($c, $l['pid'], $b['sid'], 'System');
                if ($c->needsJust($l['pid'])) {
                    $x = $c->csOf($l['pid']);
                    $x['just'] = 'Selected after excluding cheaper offers that fail the sample test or MOQ gate.';
                    $c->csSet($l['pid'], $x);
                }
            } else {
                $x = $c->csOf($l['pid']);
                $x['excl'] = true;
                $x['exclWhy'] = 'No valid quotation — re-tender';
                $c->csSet($l['pid'], $x);
            }
        }
        $w = array_key_first($c->csOf('P-1008')['alloc'] ?? []);
        if ($w) {
            $cs->negotiate($c, 'P-1008', $w, round($c->s['quotes'][$w]['P-1008']['price'] * .98, 3), 'Volume commitment across the booking', 'Imran K.');
        }
    }

    private function stepAppr(CaseBook $c, bool $full): void
    {
        $a = app(ApprovalService::class);
        $a->submitAward($c, 'Imran K.', 'Lowest eligible landed cost per product; unquoted product returned to re-tender.');
        $a->decide($c, 'ok', 'Specs and sample outcomes match requirement.', 'Nasrin A.');
    }

    private function stepPo(CaseBook $c, bool $full): void
    {
        $x = $c->csOf('P-1001');
        $x['fin'] = 'Landed-cost assumptions accepted; confirm FX rate before LC opening.';
        $c->csSet('P-1001', $x);
        $a = app(ApprovalService::class);
        $a->decide($c, 'ok', 'Budget available; LC / advance exposure within limits.', 'Kamal U.');
        $a->decide($c, 'ok', 'Approved.', 'ED (delegate)');
    }

    private function stepAck(CaseBook $c, bool $full): void
    {
        $po = app(PoService::class);
        $now = Clock::ms();
        foreach ($c->s['pos'] as $k => $_) {
            $c->s['pos'][$k]['draft'] = true;
            if ($po->status($c, $c->s['pos'][$k]) === 'Ready to Dispatch') {
                $po->dispatch($c, $c->s['pos'][$k], $now - (int) (3600e3 * ($full ? 400 : 1)), 'System');
            }
        }
        foreach ($c->s['pos'] as $k => $_) {
            if (!empty($c->s['pos'][$k]['sent'])) {
                $c->s['pos'][$k]['ack'] = 'Accepted';
                $c->s['pos'][$k]['ackAt'] = $now - 3000000;
                Workflow::log('PO accepted', $c->s['pos'][$k]['no'], Masters::supplier($c->s['pos'][$k]['sid'])['name'], ['by' => 'Supplier', 'cid' => $c->id]);
                if (!$full) {
                    break;
                }
            }
        }
    }

    private function firstAccepted(CaseBook $c): ?array
    {
        foreach ($c->s['pos'] as $p) {
            if (($p['ack'] ?? '') === 'Accepted') {
                return $p;
            }
        }
        return null;
    }

    private function stepPi(CaseBook $c, bool $full): void
    {
        $p = $this->firstAccepted($c);
        if (!$p) {
            return;
        }
        $pi = app(PiService::class)->seed($c, $p, ['no' => '[SUPPLIER-PI-REF]']);
        $pi['lines'][0]['price'] = round($pi['lines'][0]['price'] + .01, 3);
        $pi['payment'] = 'TT 100% advance';
        $c->s['pis'][$p['no']] = $pi;
        Workflow::log('PI recorded', $p['no'], 'Supplier PI rev 1', ['by' => 'System', 'cid' => $c->id]);
        Workflow::raise('PO vs PI mismatch', $p['no'], 'Price and payment terms differ', ['by' => 'System', 'cid' => $c->id]);
    }

    private function stepLc(CaseBook $c, bool $full): void
    {
        $p = $this->firstAccepted($c);
        if (!$p) {
            return;
        }
        $c->s['pis'][$p['no']] = app(PiService::class)->seed($c, $p, ['no' => '[SUPPLIER-PI-REF]', 'approved' => true]);
        Workflow::resolve('PO vs PI mismatch', $p['no'], 'PI corrected (rev 2)');
        $c->s['lc'][$p['no']] = ['docs' => array_fill_keys(array_keys(PiService::IMPDOCS), true), 'docsDone' => true, 'appr' => null, 'open' => null];
    }

    private function stepDone(CaseBook $c, bool $full): void
    {
        $po = app(PoService::class);
        $pis = app(PiService::class);
        $now = Clock::ms();
        foreach ($c->s['pos'] as $k => $_) {
            if (empty($c->s['pos'][$k]['sent'])) {
                $c->s['pos'][$k]['draft'] = true;
                $po->dispatch($c, $c->s['pos'][$k], $now - (int) 1.08e9, 'System');
            }
            $p = &$c->s['pos'][$k];
            $p['ack'] = 'Accepted';
            $pi = $c->s['pis'][$p['no']] ?? $pis->seed($c, $p, ['no' => '[SUPPLIER-PI-REF]']);
            $pi['approved'] = true;
            $c->s['pis'][$p['no']] = $pi;
            $amt = 0;
            foreach ($pi['lines'] as $l) {
                $amt += $l['qty'] * $l['price'];
            }
            $c->s['lc'][$p['no']] = ['docs' => array_fill_keys(array_keys(PiService::IMPDOCS), true), 'docsDone' => true,
                'appr' => ['by' => 'Kamal U.', 'at' => $now - 86400000 * 3],
                'open' => ['ref' => '[LC-REFERENCE]', 'bank' => '[AD-BANK-NAME]', 'amt' => $amt, 'exp' => gmmktime(0, 0, 0, 1, 15, 2027) * 1000, 'at' => $now - 86400000 * 2]];
            ExceptionRecord::where('ref', $p['no'])->update(['status' => 'Resolved', 'res' => 'Closed with case']);
            unset($p);
        }
    }
}
