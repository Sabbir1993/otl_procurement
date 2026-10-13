<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\Sequence;

/**
 * Requisition board + confirmed book qty (prototype menu/rq/requisition-board.js, booking.js,
 * freezeCase / bookingSerial in 04-widgets-logic.js).
 * Public action methods take a case id, enforce Perm + stage rules and persist in a transaction.
 * freeze() / confirmBooking() are the cross-module contract: they mutate $c->s and log, the caller saves.
 */
class RequisitionService
{
    public const BUYERS = ['Nordic Apparel Group', 'Atlas Denim Co.', 'Kestrel Outdoor', 'Veloura Fashion'];
    public const CUSTS = ['Evergreen Garments Ltd', 'Meghna Apparels Ltd', 'Padma Knit Composite', 'Jamuna Fashions Ltd'];
    public const MKTS = ['Nasrin A.', 'Rakib H.', 'Sumaiya T.'];

    // ------------------------------------------------------------------ helpers
    private function idx(CaseBook $c, string $id): int
    {
        foreach ($c->s['demand'] as $i => $d) {
            if ($d['id'] === $id) {
                return $i;
            }
        }
        throw new BusinessRule("Requisition line {$id} not found.");
    }

    /** Lines of a frozen requisition cannot be changed any more. */
    private function open(CaseBook $c): void
    {
        if ($c->s['frozen']) {
            throw new BusinessRule('Requisition is frozen — lines cannot be added or edited.');
        }
    }

    private function bookingOpen(CaseBook $c): void
    {
        if (!$c->s['frozen']) {
            throw new BusinessRule('Validate and freeze the requisition first.');
        }
        if ($c->s['booking']['confirmed']) {
            throw new BusinessRule('Booking is confirmed — quantities are locked.');
        }
    }

    private static function round($x): int
    {
        return (int) floor($x + 0.5);       // JS Math.round
    }

    // ------------------------------------------------------------------ reads (controllers/views go through these only,
    // so demand-line storage can later move to the ERP tables product_req_from_mktp[_dtl])
    /** @return array<int,array> all requisition lines of the case */
    public function lines(CaseBook $c): array
    {
        return $c->s['demand'];
    }

    /** @return array{total:int,accepted:int,rejected:int,pending:int,acceptedQty:float|int} */
    public function stats(CaseBook $c): array
    {
        $L = $this->lines($c);
        $by = fn ($st) => array_filter($L, fn ($d) => $d['status'] === $st);
        return ['total' => count($L), 'accepted' => count($by('accepted')), 'rejected' => count($by('rejected')), 'pending' => count($by('pending')),
            'acceptedQty' => array_sum(array_column($by('accepted'), 'qty'))];
    }

    /** Sum of demand qty for a material, optionally by status. */
    public function demandQty(CaseBook $c, string $pid, ?string $status = null): float|int
    {
        return array_sum(array_map(fn ($d) => $d['qty'], array_filter($this->lines($c), fn ($d) => $d['pid'] === $pid && (!$status || $d['status'] === $status))));
    }

    // ------------------------------------------------------------------ requisition lines
    public function accept(string $cid, string $id): void
    {
        Perm::need('demand.edit');
        CaseBook::tx($cid, function (CaseBook $c) use ($id) {
            $this->open($c);
            $i = $this->idx($c, $id);
            $c->s['demand'][$i]['status'] = 'accepted';
            $c->s['demand'][$i]['reason'] = '';
            $x = $c->s['demand'][$i];
            Workflow::log('Requisition accepted', $id, "{$x['pid']} " . Fmt::n0($x['qty']), ['cid' => $c->id]);
        });
    }

    public function revert(string $cid, string $id): void
    {
        Perm::need('demand.edit');
        CaseBook::tx($cid, function (CaseBook $c) use ($id) {
            $this->open($c);
            $i = $this->idx($c, $id);
            $c->s['demand'][$i]['status'] = 'pending';
            $c->s['demand'][$i]['reason'] = '';
            Workflow::log('Requisition reverted', $id, 'Back to pending', ['cid' => $c->id]);
        });
    }

    public function delete(string $cid, string $id): void
    {
        Perm::need('demand.edit');
        CaseBook::tx($cid, function (CaseBook $c) use ($id) {
            $this->open($c);
            $this->idx($c, $id);
            $c->s['demand'] = array_values(array_filter($c->s['demand'], fn ($d) => $d['id'] !== $id));
            Workflow::log('Requisition deleted', $id, '', ['cid' => $c->id]);
        });
    }

    /** @param string[] $ids */
    public function bulkAccept(string $cid, array $ids): void
    {
        Perm::need('demand.edit');
        if (!$ids) {
            throw new BusinessRule('Select at least one line first');
        }
        CaseBook::tx($cid, function (CaseBook $c) use ($ids) {
            $this->open($c);
            foreach ($c->s['demand'] as $i => $d) {
                if (in_array($d['id'], $ids, true) && $d['status'] === 'pending') {
                    $c->s['demand'][$i]['status'] = 'accepted';
                }
            }
            Workflow::log('Bulk accept', $c->id, count($ids) . ' lines', ['cid' => $c->id]);
        });
    }

    /** @param string[] $ids */
    public function bulkReject(string $cid, array $ids, string $reason, ?string $note = null): void
    {
        Perm::need('demand.edit');
        if (!$ids) {
            throw new BusinessRule('Select at least one line first');
        }
        if (!in_array($reason, (array) Masters::setting('reasons', []), true)) {
            throw new BusinessRule('Choose a reason of non-acceptance.');
        }
        CaseBook::tx($cid, function (CaseBook $c) use ($ids, $reason, $note) {
            $this->open($c);
            foreach ($c->s['demand'] as $i => $d) {
                if (in_array($d['id'], $ids, true)) {
                    $c->s['demand'][$i]['status'] = 'rejected';
                    $c->s['demand'][$i]['reason'] = $reason;
                    $c->s['demand'][$i]['remark'] = (string) $note;
                }
            }
            Workflow::log('Bulk reject', $c->id, count($ids) . " lines · {$reason}", ['cid' => $c->id]);
        });
    }

    /** Add (no id) or edit (id) a requisition line. @param array{id?:?string,pid:string,buyer:string,customer:string,qty:mixed,need?:?string,mkt?:?string,remark?:?string} $o */
    public function saveLine(string $cid, array $o): string
    {
        Perm::need('demand.edit');
        if (!is_numeric($o['qty'] ?? null) || !($o['qty'] > 0)) {
            throw new BusinessRule('Quantity must be greater than zero.');
        }
        if (!isset(Masters::materials()[$o['pid'] ?? ''])) {
            throw new BusinessRule('Choose a material.');
        }
        if (trim($o['buyer'] ?? '') === '' || trim($o['customer'] ?? '') === '') {
            throw new BusinessRule('Buyer and customer are required.');
        }
        return CaseBook::tx($cid, function (CaseBook $c) use ($o) {
            $this->open($c);
            $f = ['pid' => $o['pid'], 'buyer' => trim($o['buyer']), 'customer' => trim($o['customer']), 'qty' => $o['qty'] + 0,
                'need' => (string) ($o['need'] ?? ''), 'mkt' => ($o['mkt'] ?? '') ?: self::MKTS[0], 'remark' => (string) ($o['remark'] ?? '')];
            if (!empty($o['id'])) {
                $i = $this->idx($c, $o['id']);
                $c->s['demand'][$i] = $f + $c->s['demand'][$i];
                Workflow::log('Requisition edited', $o['id'], '', ['cid' => $c->id]);
                return $o['id'];
            }
            $n = array_reduce($c->s['demand'], fn ($m, $d) => max($m, (int) preg_replace('/\D/', '', (string) $d['id'])), 0) + 1;
            $ids = array_column($c->s['demand'], 'id');
            while (in_array('R' . Fmt::pad($n), $ids, true)) {
                $n++;
            }
            $id = 'R' . Fmt::pad($n);
            $c->s['demand'][] = ['id' => $id] + $f + ['status' => 'pending', 'reason' => ''];
            Workflow::log('Requisition added', $id, "{$o['pid']} " . Fmt::n0($o['qty']), ['cid' => $c->id]);
            return $id;
        });
    }

    /** Validate and freeze (prototype ACT.rqFreeze). */
    public function freezeAction(string $cid): void
    {
        Perm::need('demand.freeze');
        CaseBook::tx($cid, function (CaseBook $c) {
            if ($c->s['frozen']) {
                throw new BusinessRule('Requisition is already frozen.');
            }
            $p = count(array_filter($c->s['demand'], fn ($d) => $d['status'] === 'pending'));
            if ($p) {
                throw new BusinessRule("{$p} line(s) still pending — accept or reject each first.");
            }
            if (!array_filter($c->s['demand'], fn ($d) => $d['status'] === 'accepted')) {
                throw new BusinessRule('Nothing accepted — nothing to book.');
            }
            $this->freeze($c);
        });
    }

    /** Contract: freeze the requisition and build the booking lines (prototype freezeCase). Caller saves. */
    public function freeze(CaseBook $c, ?string $by = null): void
    {
        $c->s['frozen'] = true;
        $lines = [];
        foreach (Masters::materials() as $pid => $m) {
            $q = $c->demandSum($pid, 'accepted');
            if ($q) {
                $lines[] = ['pid' => $pid, 'demand' => $q, 'pct' => 100, 'qty' => $q];
            }
        }
        $c->s['booking']['lines'] = $lines;
        Workflow::log('Requisition frozen', $c->id, count($lines) . ' products → Import Booking Report', ['cid' => $c->id] + ($by ? ['by' => $by] : []));
    }

    // ------------------------------------------------------------------ booking lines
    private function bidx(CaseBook $c, string $pid): int
    {
        foreach ($c->s['booking']['lines'] as $i => $l) {
            if ($l['pid'] === $pid) {
                return $i;
            }
        }
        throw new BusinessRule("{$pid} is not in the booking.");
    }

    public function editBooking(string $cid, string $pid, $pct, $qty): void
    {
        Perm::need('booking.edit');
        if (!is_numeric($qty) || !($qty > 0)) {
            throw new BusinessRule('Book qty must be greater than zero.');
        }
        $pct = is_numeric($pct) ? $pct + 0 : 0;
        $qty = $qty + 0;
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $pct, $qty) {
            $this->bookingOpen($c);
            $i = $this->bidx($c, $pid);
            $l = &$c->s['booking']['lines'][$i];
            // changing the % recalculates the qty unless the qty was overridden to a different value
            if ($pct != $l['pct'] && self::round($l['demand'] * $pct / 100) != $qty) {
                $l['qty'] = self::round($l['demand'] * $pct / 100);
            } else {
                $l['qty'] = $qty;
            }
            $l['pct'] = $pct;
            Workflow::log('Book qty edited', $pid, '→ ' . Fmt::n0($l['qty']), ['cid' => $c->id]);
        });
    }

    public function deleteBooking(string $cid, string $pid): void
    {
        Perm::need('booking.edit');
        CaseBook::tx($cid, function (CaseBook $c) use ($pid) {
            $this->bookingOpen($c);
            $this->bidx($c, $pid);
            $c->s['booking']['lines'] = array_values(array_filter($c->s['booking']['lines'], fn ($l) => $l['pid'] !== $pid));
            Workflow::log('Booking line removed', $pid, '', ['cid' => $c->id]);
        });
    }

    public function addBooking(string $cid, string $pid, $qty): void
    {
        Perm::need('booking.edit');
        if (!isset(Masters::materials()[$pid])) {
            throw new BusinessRule('Choose a material.');
        }
        if (!is_numeric($qty) || !($qty > 0)) {
            throw new BusinessRule('Book qty must be greater than zero.');
        }
        CaseBook::tx($cid, function (CaseBook $c) use ($pid, $qty) {
            $this->bookingOpen($c);
            if (collect($c->s['booking']['lines'])->contains('pid', $pid)) {
                throw new BusinessRule("{$pid} is already in the booking.");
            }
            $c->s['booking']['lines'][] = ['pid' => $pid, 'demand' => 0, 'pct' => 100, 'qty' => $qty + 0];
            Workflow::log('Booking line added', $pid, Fmt::n0($qty), ['cid' => $c->id]);
        });
    }

    /** Bulk override from CSV "product_id,book_qty" (header row skipped). Returns lines updated. */
    public function importCsv(string $cid, string $content): int
    {
        Perm::need('booking.edit');
        return CaseBook::tx($cid, function (CaseBook $c) use ($content) {
            $this->bookingOpen($c);
            $n = 0;
            $rows = preg_split('/\r?\n/', ltrim($content, "\xEF\xBB\xBF"));
            foreach (array_slice($rows, 1) as $ln) {
                $cells = array_map(fn ($x) => trim(trim($x), '"'), explode(',', $ln));
                $p = $cells[0] ?? '';
                $q = $cells[1] ?? '';
                foreach ($c->s['booking']['lines'] as $i => $l) {
                    if ($l['pid'] === $p && is_numeric($q) && $q > 0) {
                        $c->s['booking']['lines'][$i]['qty'] = $q + 0;
                        $n++;
                    }
                }
            }
            Workflow::log('Book qty uploaded', $c->id, "{$n} lines updated from CSV", ['cid' => $c->id]);
            return $n;
        });
    }

    /** Import Booking Report rows (header first) + audit. */
    public function report(string $cid): array
    {
        $c = CaseBook::findOrFail($cid);
        $out = [['product_id', 'product_name', 'accepted_demand', 'book_qty']];
        foreach ($c->s['booking']['lines'] as $l) {
            $out[] = [$l['pid'], Masters::material($l['pid'])['name'], $l['demand'], $l['qty']];
        }
        Workflow::log('Export', $cid, 'Import booking report', ['cid' => $cid]);
        return $out;
    }

    // ------------------------------------------------------------------ confirm
    /** Next serial that confirmation will assign (display only). */
    public function nextSerial(CaseBook $c): string
    {
        return $this->serialFor($c, (int) Sequence::whereKey('IMPBOK-' . $c->mon)->value('value') + 1);
    }

    private function serialFor(CaseBook $c, int $n): string
    {
        $yr = preg_match('/(\d{4})\s*$/', (string) $c->month, $m) ? $m[1] : '2026';
        return "IMPBOK/{$yr}/{$c->mon}/" . Fmt::pad($n);
    }

    /**
     * Action wrapper: permission + persist. $extra = ['doc' => file name, 'signedBy' => .., 'signedOn' => .., 'path' => stored path].
     */
    public function confirm(string $cid, string $docRef, array $extra = []): string
    {
        Perm::need('booking.confirm');
        return CaseBook::tx($cid, function (CaseBook $c) use ($docRef, $extra) {
            $this->confirmBooking($c, $docRef, null, null, $extra);
            return $c->s['booking']['serial'];
        });
    }

    /** Contract: confirm the booking (signed-document reference mandatory), assign the next serial, audit + notify. Caller saves. */
    public function confirmBooking(CaseBook $c, string $docRef, ?string $by = null, ?int $ts = null, array $extra = []): void
    {
        if (!$c->s['frozen']) {
            throw new BusinessRule('Validate and freeze the requisition first.');
        }
        if ($c->s['booking']['confirmed']) {
            throw new BusinessRule('Booking is already confirmed.');
        }
        if (trim($docRef) === '') {
            throw new BusinessRule('Attach the signed document and enter its reference.');
        }
        $L = $c->s['booking']['lines'];
        if (!$L) {
            throw new BusinessRule('The booking has no lines.');
        }
        if (array_filter($L, fn ($l) => !($l['qty'] > 0))) {
            throw new BusinessRule('A line has zero book qty — fix or remove it.');
        }
        $b = &$c->s['booking'];
        $b['doc'] = $extra['doc'] ?? $docRef;
        $b['ref'] = $docRef;
        $b['signedBy'] = $extra['signedBy'] ?? ($by ?? Perm::user());
        if (!empty($extra['signedOn'])) {
            $b['signedOn'] = $extra['signedOn'];
        }
        if (!empty($extra['path'])) {
            $b['docPath'] = $extra['path'];
        }
        $b['serial'] = $this->serialFor($c, Workflow::nextSeq('IMPBOK-' . $c->mon));
        $b['confirmed'] = true;
        $b['at'] = $ts ?? Clock::ms();
        $b['by'] = $by ?? Perm::user();
        $o = ['cid' => $c->id] + ($by ? ['by' => $by] : []) + ($ts ? ['ts' => $ts] : []);
        Workflow::log('Booking confirmed', $b['serial'], "{$b['doc']} · ref {$docRef}", $o);
        Workflow::notify('ok', "{$b['serial']} confirmed — create the RFQ", 'src.rfq', ['role' => 'exec'] + ($ts ? ['ts' => $ts] : []));
    }
}
