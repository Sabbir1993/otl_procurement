<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Deadline;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\EmailTemplate;
use App\Models\ExceptionRecord;
use App\Models\PurchaseCase;
use App\Models\SupplierProduct;

/**
 * Sourcing: supplier-product shortlist, RFQ drafts / sending / reminders / extensions / revocation / cut-off,
 * and the secure supplier link (token) handling. Ports the prototype's genDrafts, sendDraft, checkNoBid and
 * the ACT.sp* / rfq* / dr* / iv* handlers 1:1.
 *
 * Secure links: each invitation gets a random nonce (iv.tokN). The link token is
 *   HMAC-SHA256(app key, caseId|supplierId|nonce)[0..40]  - only SHA-256 of it is stored in the case (iv.tok, "sha256:<hex>").
 * The plaintext is never stored in the case aggregate (iv.tokPlain does not exist); it is re-derived on the server whenever
 * an e-mail (RFQ / reminder / extension) must carry the link, and is looked up by hash on the public route /s/{token}.
 */
class RfqService
{
    public const SENDERS = ['Procurement Desk — import@[oil-domain]', 'Import Cell — rfq@[oil-domain]'];

    // =====================================================================================================
    // secure link tokens
    // =====================================================================================================
    public static function hashToken(string $plain): string
    {
        return 'sha256:' . hash('sha256', $plain);
    }

    /** The (re-derivable) plaintext link token of an invitation. Server side only; never persisted. */
    public static function tokenFor(string $caseId, array $iv): string
    {
        return substr(hash_hmac('sha256', $caseId . '|' . $iv['sid'] . '|' . ($iv['tokN'] ?? ''), (string) config('app.key')), 0, 40);
    }

    public static function linkFor(string $caseId, array $iv): string
    {
        return \App\Support\Link::to('/s/' . self::tokenFor($caseId, $iv));
    }

    /** Resolve a public token to [CaseBook, invitation] or null. Compares hashes only. */
    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }
        $h = self::hashToken($token);
        foreach (PurchaseCase::where('state', 'like', '%' . $h . '%')->get() as $m) {
            $c = new CaseBook($m);
            foreach ($c->s['inv'] as $iv) {
                if (isset($iv['tok']) && hash_equals($iv['tok'], $h)) {
                    return [$c, $iv];
                }
            }
        }
        return null;
    }

    /** E-mail variables (prototype rfqVars) with the real secure link. */
    public function vars(CaseBook $c, array $iv): array
    {
        return array_merge($c->rfqVars($iv), ['RFQ_LINK' => self::linkFor($c->id, $iv)]);
    }

    private static function to(array $sp): string
    {
        return ($sp['contact'] ?? '') . ' <' . ($sp['email'] ?? '') . '>';
    }

    private static function opts(CaseBook $c, ?string $by): array
    {
        return ['cid' => $c->id] + ($by ? ['by' => $by] : []);
    }

    // =====================================================================================================
    // supplier-product shortlist (spTog / spDel / spForm / spSave), perm sp.edit
    // =====================================================================================================
    public function toggleMapping(int $id): bool
    {
        Perm::need('sp.edit');
        $x = SupplierProduct::findOrFail($id);
        $x->active = !$x->active;
        $x->save();
        Masters::flush();
        Workflow::log('Supplier product ' . ($x->active ? 'activated' : 'deactivated'), $x->material_id, "{$x->supplier_id} · {$x->name}");
        return $x->active;
    }

    public function deleteMapping(int $id): void
    {
        Perm::need('sp.edit');
        $x = SupplierProduct::findOrFail($id);
        $x->delete();
        Masters::flush();
        Workflow::log('Supplier product removed', $x->material_id, "{$x->supplier_id} · {$x->name}");
    }

    /** @param array{id?:?int,pid:string,sid:string,name:string,active:string} $d  active = 'Active'|'Inactive' */
    public function saveMapping(array $d): void
    {
        Perm::need('sp.edit');
        $pid = $d['pid'];
        $sid = $d['sid'];
        if (!isset(Masters::materials()[$pid]) || !isset(Masters::suppliers()[$sid])) {
            throw new BusinessRule('Choose a valid material and supplier.');
        }
        $name = trim($d['name'] ?? '');
        if ($name === '') {
            throw new BusinessRule('Supplier product name is required.');
        }
        $id = !empty($d['id']) ? (int) $d['id'] : null;
        $dup = SupplierProduct::where('material_id', $pid)->where('supplier_id', $sid)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($dup) {
            throw new BusinessRule('This supplier is already mapped to the material.');
        }
        $rec = ['material_id' => $pid, 'supplier_id' => $sid, 'name' => $name, 'active' => ($d['active'] ?? 'Active') === 'Active'];
        $id ? SupplierProduct::findOrFail($id)->update($rec) : SupplierProduct::create($rec);
        Masters::flush();
        Workflow::log('Supplier product saved', $pid, "{$sid} · {$name}");
    }

    // =====================================================================================================
    // RFQ drafts (prototype genDrafts) - mutates $c->s, the caller saves
    // =====================================================================================================
    /** $opt: pool (sid => pids), urgent (bool), sender, notes, by ('System' for automation). */
    public function generateDrafts(CaseBook $c, array $opt = []): void
    {
        $by = $opt['by'] ?? null;
        if (empty($c->s['rfq']['no'])) {
            $c->s['rfq']['no'] = Workflow::nid('RFQ');
        }
        $pool = $opt['pool'] ?? $c->poolSup();
        $urgent = !empty($opt['urgent']);
        foreach ($pool as $sid => $pids) {
            $k = null;
            foreach ($c->s['drafts'] as $i => $d) {
                if ($d['sid'] === $sid) {
                    $k = $i;
                    break;
                }
            }
            if ($k !== null && in_array($c->s['drafts'][$k]['status'], ['Sent', 'Scheduled'], true)) {
                continue;
            }
            $dl = Deadline::calc(Masters::supplier($sid)['cal'], Clock::ms(), $urgent ? 2 : 3);
            $d = ['id' => 'DR-' . substr($sid, -2), 'sid' => $sid, 'pids' => array_values($pids), 'status' => isset($dl['err']) ? 'Blocked' : 'Draft',
                'why' => $dl['err'] ?? '', 'urgent' => $urgent, 'sender' => $opt['sender'] ?? $c->s['sender']];
            if ($k !== null) {
                $c->s['drafts'][$k] = array_merge($c->s['drafts'][$k], $d);
            } else {
                $c->s['drafts'][] = $d;
            }
            if (isset($dl['err'])) {
                Workflow::raise('Calendar unavailable', $sid, $dl['err'], self::opts($c, $by));
            }
        }
        $c->s['rfq']['urgent'] = $urgent;
        if (array_key_exists('notes', $opt)) {
            $c->s['rfq']['notes'] = $opt['notes'];
        }
        Workflow::log('RFQ drafts generated', $c->s['rfq']['no'], count($c->s['drafts']) . ' supplier drafts', self::opts($c, $by));
    }

    /** Issue the supplier link: deadline from the supplier-country working-day calendar; blocked (+ exception) if unapproved. */
    public function sendDraft(CaseBook $c, array &$draft, ?int $ts = null, ?string $by = null): bool
    {
        $ts ??= Clock::ms();
        $sp = Masters::supplier($draft['sid']);
        $dl = Deadline::calc($sp['cal'] ?? '', $ts, !empty($draft['urgent']) ? 2 : 3);
        $o = self::opts($c, $by);
        if (isset($dl['err'])) {
            $draft['status'] = 'Blocked';
            $draft['why'] = $dl['err'];
            Workflow::raise('Calendar unavailable', $draft['sid'], $dl['err'], $o);
            return false;
        }
        $iv = ['sid' => $draft['sid'], 'rfq' => $c->s['rfq']['no'], 'pids' => $draft['pids'], 'state' => 'Sent', 'sentAt' => $ts, 'deadline' => $dl['ts'],
            'origDeadline' => $dl['ts'], 'ext' => [], 'submitted' => false, 'tokN' => Workflow::rnd(16), 'ip' => null, 'rev' => 0, 'revoked' => false];
        $iv['tok'] = self::hashToken(self::tokenFor($c->id, $iv));
        $c->s['inv'] = array_values(array_filter($c->s['inv'], fn ($i) => $i['sid'] !== $draft['sid']));
        $c->s['inv'][] = $iv;
        $draft['status'] = 'Sent';
        $draft['sentAt'] = $ts;
        $draft['why'] = '';
        Workflow::mail($c->id, 'RFQ', self::to($sp), $this->vars($c, $iv), ['ts' => $ts]);
        Workflow::log('RFQ sent', $c->s['rfq']['no'], "{$sp['name']} · link " . substr($iv['tok'], 0, 17) . '…', $o + ['ts' => $ts]);
        return true;
    }

    /** drPrev: the e-mail a draft would send (no real token). @return array{err:string}|array{to:string,from:string,subject:string,body:string} */
    public function preview(CaseBook $c, array $draft): array
    {
        $sp = Masters::supplier($draft['sid']);
        $iv = $c->invOf($draft['sid']);
        $dl = $iv ? ['ts' => $iv['deadline']] : Deadline::calc($sp['cal'] ?? '', Clock::ms(), !empty($draft['urgent']) ? 2 : 3);
        if (isset($dl['err'])) {
            return ['err' => $dl['err']];
        }
        $t = EmailTemplate::findOrFail('RFQ');
        $v = $c->rfqVars(['sid' => $draft['sid'], 'pids' => $draft['pids'], 'deadline' => $dl['ts'], 'rfq' => $c->s['rfq']['no'], 'tokPlain' => '[SECURE-TOKEN]']) + ['CONTACT_EMAIL' => '[OIL_CONTACT_EMAIL]'];
        return ['to' => self::to($sp), 'from' => $draft['sender'] ?? $c->s['sender'], 'subject' => Workflow::fill($t->subject, $v), 'body' => Workflow::fill($t->body, $v)];
    }

    /** After cut-off: a material nobody bid for is a "No bid" exception (prototype checkNoBid). */
    public function checkNoBid(CaseBook $c, ?string $by = null): void
    {
        if (!$c->rfqClosed()) {
            return;
        }
        foreach ($c->s['booking']['lines'] as $l) {
            if (!$c->bids($l['pid'])) {
                Workflow::raise('No bid', $l['pid'], 'No valid quotation received for ' . Masters::material($l['pid'])['name'] . " ({$c->id}) — re-tender or extend.", self::opts($c, $by));
            }
        }
    }

    // =====================================================================================================
    // actions
    // =====================================================================================================
    /**
     * rfqSave: create RFQ drafts from the confirmed booking.
     * @param array{urgent?:mixed,reason?:?string,sender?:?string,sample?:?string,docs?:?string,notes?:?string,rf?:array,sup?:array} $d
     */
    public function saveRfq(string $caseId, array $d): CaseBook
    {
        Perm::need('rfq.create');
        return CaseBook::tx($caseId, function (CaseBook $c) use ($d) {
            if (empty($c->s['booking']['confirmed'])) {
                throw new BusinessRule('Confirm the booking first — the RFQ is built from the confirmed book qty.');
            }
            $urgent = ($d['urgent'] ?? '0') === '1' || ($d['urgent'] ?? false) === true;
            $reason = trim((string) ($d['reason'] ?? ''));
            if ($urgent && $reason === '') {
                throw new BusinessRule('An authorised reason is required for an urgent RFQ.');
            }
            $pool0 = $c->poolSup();
            $pool = [];
            foreach ((array) ($d['sup'] ?? []) as $sid) {
                if (isset($pool0[$sid])) {
                    $pool[$sid] = $pool0[$sid];
                }
            }
            if (!$pool) {
                throw new BusinessRule('Select at least one supplier.');
            }
            $sender = in_array($d['sender'] ?? '', self::SENDERS, true) ? $d['sender'] : $c->s['sender'];
            $valid = array_column(config('quote_fields.extra_req'), 0);
            $c->s['sender'] = $sender;
            $c->s['rfq']['docs'] = trim((string) ($d['docs'] ?? ''));
            $c->s['rfq']['sample'] = ($d['sample'] ?? 'Yes') === 'Yes' ? 'Yes' : 'No — approved waiver';
            $c->s['rfq']['reqFields'] = array_values(array_filter($valid, fn ($k) => in_array($k, (array) ($d['rf'] ?? []), true)));
            if ($urgent) {
                $c->s['rfq']['reason'] = $reason;
            }
            $this->generateDrafts($c, ['pool' => $pool, 'urgent' => $urgent, 'sender' => $sender, 'notes' => trim((string) ($d['notes'] ?? ''))]);
            return $c;
        });
    }

    /** drSend: send the selected drafts now. @return array{ok:int,n:int} */
    public function sendDrafts(string $caseId, array $ids): array
    {
        Perm::need('rfq.send');
        return CaseBook::tx($caseId, function (CaseBook $c) use ($ids) {
            $ok = 0;
            $n = 0;
            foreach ($c->s['drafts'] as $k => $d) {
                if (in_array($d['id'], $ids, true) && in_array($d['status'], ['Draft', 'Failed'], true)) {
                    $n++;
                    if ($this->sendDraft($c, $c->s['drafts'][$k], Clock::ms())) {
                        $ok++;
                    }
                }
            }
            return ['ok' => $ok, 'n' => $n];
        });
    }

    /** drSchedSave: schedule the selected drafts at $t (datetime-local, Bangladesh time). @return int drafts scheduled */
    public function schedule(string $caseId, array $ids, string $t): int
    {
        Perm::need('rfq.send');
        $at = strtotime($t . ':00+06:00') ?: 0;
        if (!$at) {
            throw new BusinessRule('Choose a valid send time.');
        }
        if ($at * 1000 < Clock::ms() - 60000) {
            throw new BusinessRule('The scheduled time must be in the future.');
        }
        return CaseBook::tx($caseId, function (CaseBook $c) use ($ids, $t, $at) {
            $n = 0;
            foreach ($c->s['drafts'] as $k => $d) {
                if (in_array($d['id'], $ids, true) && in_array($d['status'], ['Draft', 'Failed'], true)) {
                    $c->s['drafts'][$k]['status'] = 'Scheduled';
                    $c->s['drafts'][$k]['at'] = $at * 1000;
                    $n++;
                }
            }
            if (!$n) {
                throw new BusinessRule('Select draft(s) in Draft status first.');
            }
            Workflow::log('RFQ scheduled', $c->s['rfq']['no'], "{$n} drafts · {$t}", ['cid' => $c->id]);
            return $n;
        });
    }

    /** drRun: dispatch scheduled drafts. $all=false: only those due now (scheduler); $all=true: everything scheduled (demo). The deadline is counted from the scheduled send time. */
    public function runDue(string $caseId, bool $all = false): int
    {
        Perm::need('rfq.send');
        return CaseBook::tx($caseId, function (CaseBook $c) use ($all) {
            $n = 0;
            foreach ($c->s['drafts'] as $k => $d) {
                if ($d['status'] === 'Scheduled' && ($all || ($d['at'] ?? 0) <= Clock::ms())) {
                    if ($this->sendDraft($c, $c->s['drafts'][$k], $d['at'] ?? Clock::ms())) {
                        $n++;
                    }
                }
            }
            return $n;
        });
    }

    /** ivRem: reminder (template B). */
    public function remind(string $caseId, string $sid): void
    {
        Perm::need('rfq.remind');
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid) {
            $iv = $c->invOf($sid) ?? throw new BusinessRule('No invitation for this supplier.');
            if ($c->ivDone($iv)) {
                throw new BusinessRule('The link is no longer open — no reminder needed.');
            }
            $sp = Masters::supplier($sid);
            Workflow::mail($c->id, 'B', self::to($sp), $this->vars($c, $iv));
            Workflow::log('Reminder sent', $c->s['rfq']['no'], $sp['name'], ['cid' => $c->id]);
        });
    }

    /** ivRev: revoke the supplier link. */
    public function revoke(string $caseId, string $sid): void
    {
        Perm::need('rfq.revoke');
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid) {
            foreach ($c->s['inv'] as $k => $iv) {
                if ($iv['sid'] === $sid) {
                    $c->s['inv'][$k]['revoked'] = true;
                    Workflow::log('Link revoked', $c->s['rfq']['no'], Masters::supplier($sid)['name'], ['cid' => $c->id]);
                    return;
                }
            }
            throw new BusinessRule('No invitation for this supplier.');
        });
    }

    /** Suggested new deadline date (local YYYY-MM-DD) for the extend dialog: 2 working days after max(now, current deadline). */
    public function suggestExtension(array $iv): string
    {
        $sp = Masters::supplier($iv['sid']);
        $cal = Masters::calendar($sp['cal'] ?? '');
        $d = Deadline::calc($sp['cal'] ?? '', max(Clock::ms(), $iv['deadline']), 2);
        return isset($d['ts']) ? Fmt::tz($d['ts'], 'Y-m-d', $cal['tz'] ?? 'Asia/Shanghai') : '';
    }

    /** ivExtSave: authorised extension. Keeps the original deadline, appends to ext[], informs the supplier (template D), resolves open "No bid" exceptions. */
    public function extend(string $caseId, string $sid, string $date, string $reason): void
    {
        Perm::need('rfq.extend');
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid, $date, $reason) {
            $k = null;
            foreach ($c->s['inv'] as $i => $iv) {
                if ($iv['sid'] === $sid) {
                    $k = $i;
                }
            }
            if ($k === null) {
                throw new BusinessRule('No invitation for this supplier.');
            }
            $sp = Masters::supplier($sid);
            $cal = Masters::calendar($sp['cal'] ?? '') ?? ['off' => 8, 'close' => 17];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date . ' 00:00:00 UTC')) {
                throw new BusinessRule('Choose a valid new deadline date.');
            }
            if (trim($reason) === '') {
                throw new BusinessRule('A reason for the extension is required.');
            }
            $ts = (strtotime($date . ' 00:00:00 UTC') + ($cal['close'] - $cal['off']) * 3600) * 1000;   // closing hour, supplier-country time
            if (!($ts > Clock::ms())) {
                throw new BusinessRule('New deadline must be in the future.');
            }
            $iv = &$c->s['inv'][$k];
            $iv['ext'][] = ['from' => $iv['deadline'], 'to' => $ts, 'reason' => $reason, 'by' => Perm::user()];
            $iv['deadline'] = $ts;
            Workflow::log('Deadline extended', $c->s['rfq']['no'], "{$sp['name']} · {$reason}", ['cid' => $c->id]);
            Workflow::mail($c->id, 'D', self::to($sp), $this->vars($c, $iv));
            ExceptionRecord::where('type', 'No bid')->where('status', 'Open')->where('case_id', $c->id)->whereIn('ref', $iv['pids'])
                ->update(['status' => 'Resolved', 'res' => 'Deadline extended ' . Fmt::dD($ts)]);
            unset($iv);
        });
    }

    /** rfqClose: apply the cut-off now. @return bool false when there was nothing to close */
    public function close(string $caseId): bool
    {
        Perm::need('rfq.close');
        return CaseBook::tx($caseId, function (CaseBook $c) {
            if (!$c->s['inv'] || $c->rfqClosed()) {
                return false;
            }
            foreach ($c->s['inv'] as $k => $iv) {
                if (!$c->ivDone($iv)) {
                    $c->s['inv'][$k]['revoked'] = true;
                    $sp = Masters::supplier($iv['sid']);
                    Workflow::mail($c->id, 'E', self::to($sp), $this->vars($c, $iv));
                }
            }
            Workflow::log('RFQ closed', $c->s['rfq']['no'], 'Cut-off applied', ['cid' => $c->id]);
            $this->checkNoBid($c);
            return true;
        });
    }

    /** RFQ register status (prototype rfqStatus). */
    public static function status(CaseBook $c): string
    {
        if (empty($c->s['rfq']['no'])) {
            return '—';
        }
        if (!$c->s['inv']) {
            return array_filter($c->s['drafts'], fn ($d) => $d['status'] === 'Scheduled') ? 'Scheduled' : 'Draft';
        }
        return $c->rfqClosed() ? 'Closed' : 'Open';
    }
}
