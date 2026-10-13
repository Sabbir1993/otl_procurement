<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Domain\QuoteSpec;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Supplier quotation form + submission (prototype supplier-portal.js: pf, pfErr, pDraft, pRevise, pSubmit, buildLine)
 * and the supplier PO acknowledgment (pAccept / pDispute). Used by the PUBLIC token portal (no login, actor = supplier)
 * and, read-only, by the staff "Supplier Portal Preview".
 *
 * Form state lives in $case->s['portal'][$sid] = ['h' => header fields, 'l' => [pid => line fields], 'decl' => ['ok' => bool],
 * 'revising' => true when the supplier is editing a new revision of an already submitted quotation, 'files' => [key => storage path]].
 * Only a SUBMITTED version is evaluated; drafts never are. All checks (cut-off, required fields) are enforced here on the server.
 */
class QuotationService
{
    public const TABS = ['general' => 'Company', 'commercial' => 'Commercial', 'technical' => 'Technical', 'delivery' => 'Delivery & packing', 'sample' => 'Sample & documents', 'review' => 'Review & submit'];
    public const UPLOAD_EXT = ['pdf', 'xlsx', 'jpg', 'jpeg', 'png'];

    // =====================================================================================================
    // form state
    // =====================================================================================================
    private static function lineVals(array $q): array
    {
        return ($q['spec'] ?? []) + ['listPrice' => $q['listPrice'] ?? '', 'tiers' => $q['tiers'] ?? [], 'disc' => $q['disc'] ?? '', 'moq' => $q['moq'] ?? ''];
    }

    /** Fresh form for a supplier (prototype pf() when no draft exists): from the submitted quote if any, else defaults from the master. Not persisted. */
    public function fresh(CaseBook $c, string $sid): array
    {
        $sp = Masters::supplier($sid);
        $iv = $c->invOf($sid) ?? ['pids' => []];
        $H = $c->hdrOf($sid);
        $qs = $c->s['quotes'][$sid] ?? [];
        $h = $H ?: [
            'legal' => $sp['name'], 'country' => ($sp['cc'] ?? '') === 'CN' ? 'China' : ($sp['cc'] ?? ''), 'address' => $sp['address'] ?? '', 'kind' => 'Manufacturer', 'site' => '',
            'contactName' => $sp['contact'] ?? '', 'desig' => $sp['desig'] ?? '', 'email' => $sp['email'] ?? '', 'phone' => $sp['phone'] ?? '', 'quoteRef' => '', 'quoteDate' => '',
            'currency' => 'USD', 'payMethod' => config('quote_fields.paym')[0], 'payDetail' => '', 'advPct' => '', 'creditDays' => '', 'bankCh' => 'Applicant (OIL)', 'incoterm' => 'FOB', 'place' => '',
            'validity' => '', 'claims' => '', 'loadPort' => '', 'dischargePort' => 'Chattogram Port', 'partial' => '', 'transship' => '', 'origin' => '', 'compliance' => '', 'exceptions' => '', 'decName' => '',
        ];
        unset($h['decAt']);
        $l = [];
        foreach ($iv['pids'] as $pid) {
            $m = Masters::material($pid);
            $l[$pid] = isset($qs[$pid]) ? self::lineVals($qs[$pid]) : ['quoteUnit' => $m['uom'], 'qtyQuoted' => $c->reqQty($pid), 'listPrice' => '', 'tiers' => [], 'disc' => '', 'moq' => '',
                'grade' => $m['meta'], 'code' => Masters::spName($sid, $pid), 'comp' => $m['comp'], 'sampleAvail' => ''];
        }
        return ['h' => $h, 'l' => $l, 'decl' => ['ok' => false]];
    }

    /** Saved draft form or a fresh one (never persists). */
    public function form(CaseBook $c, string $sid): array
    {
        return $c->s['portal'][$sid] ?? $this->fresh($c, $sid);
    }

    /** Validation (prototype pfErr): list of ['tab','k','pid','m']. Empty = ready to submit. */
    public function errors(CaseBook $c, string $sid, ?array $F = null): array
    {
        $F ??= $this->form($c, $sid);
        $iv = $c->invOf($sid);
        $rf = $c->s['rfq']['reqFields'] ?? [];
        $e = [];
        foreach (QuoteSpec::fields() as $f) {
            foreach ($f['s'] === 'h' ? [''] : $iv['pids'] as $pid) {
                if ($f['s'] === 'l' && !QuoteSpec::fApplies($f, $pid)) {
                    continue;
                }
                $v = QuoteSpec::fGet($F, $f, $pid);
                $tag = $f['s'] === 'l' ? "{$pid}: " : '';
                $has = QuoteSpec::hasVal($v);
                if (QuoteSpec::fReq($rf, $f, $pid ?: null, $F) && !$has) {
                    $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => "{$tag}{$f['l']} is required"];
                    continue;
                }
                if (!$has) {
                    continue;
                }
                $num = is_numeric($v) ? (float) $v : null;
                if ($f['t'] === 'number' && !($num !== null && $num >= 0)) {
                    $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => "{$tag}{$f['l']} must be a valid number"];
                }
                if (in_array($f['k'], ['listPrice', 'moq', 'qtyQuoted', 'prodDays', 'shipDays'], true) && !($num !== null && $num > 0)) {
                    $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => "{$tag}{$f['l']} must be greater than zero"];
                }
                if (in_array($f['k'], ['advPct', 'disc'], true) && ($num === null || $num < 0 || $num > 100)) {
                    $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => "{$f['l']} must be between 0 and 100"];
                }
                if ($f['k'] === 'email' && !preg_match('/^\S+@\S+\.\S+$/', (string) $v)) {
                    $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => 'Email address is not valid'];
                }
                if ($f['k'] === 'validity') {
                    $t = self::dateMs((string) $v);
                    if ($t === null) {
                        $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => 'Quotation validity must be a valid date'];
                    } elseif ($t <= Clock::ms()) {
                        $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => 'Quotation validity must be a future date'];
                    }
                }
                if ($f['t'] === 'select' && !in_array((string) $v, $f['opts'], true)) {
                    $e[] = ['tab' => $f['tab'], 'k' => $f['k'], 'pid' => $pid, 'm' => "{$tag}{$f['l']}: choose one of the listed options"];
                }
            }
        }
        foreach ($iv['pids'] as $pid) {
            $t = array_values(array_filter($F['l'][$pid]['tiers'] ?? [], fn ($x) => QuoteSpec::hasVal($x['min'] ?? '') || QuoteSpec::hasVal($x['price'] ?? '')));
            foreach ($t as $x) {
                if (!((float) ($x['min'] ?? 0) > 0 && (float) ($x['price'] ?? 0) > 0)) {
                    $e[] = ['tab' => 'commercial', 'k' => 'tiers', 'pid' => $pid, 'm' => "{$pid}: each price tier needs a minimum quantity and a price"];
                }
            }
            for ($i = 1; $i < count($t); $i++) {
                if ((float) $t[$i]['min'] <= (float) $t[$i - 1]['min']) {
                    $e[] = ['tab' => 'commercial', 'k' => 'tiers', 'pid' => $pid, 'm' => "{$pid}: tier minimum quantities must increase"];
                    break;
                }
            }
        }
        if (empty($F['decl']['ok'])) {
            $e[] = ['tab' => 'review', 'k' => 'decl', 'pid' => '', 'm' => 'Tick the declaration to confirm and submit'];
        }
        return $e;
    }

    /** UTC-midnight epoch ms of a YYYY-MM-DD date, null if not a real date. */
    private static function dateMs(string $v): ?int
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return strtotime($v . ' 00:00:00 UTC') * 1000;
    }

    /** True when no more drafts / submissions are accepted for this invitation (revoked or past the deadline - server time). */
    public static function closedFor(array $iv): bool
    {
        return !empty($iv['revoked']) || Clock::ms() > $iv['deadline'];
    }

    /** Everything the portal view needs for one supplier / tab. */
    public function context(CaseBook $c, string $sid, string $tab = 'general'): array
    {
        $iv = $c->invOf($sid);
        $F = $this->form($c, $sid);
        $closed = $iv ? Clock::ms() > $iv['deadline'] : true;
        $rfLabels = [];
        foreach ((array) ($c->s['rfq']['reqFields'] ?? []) as $k) {
            foreach (config('quote_fields.extra_req') as $x) {
                if ($x[0] === $k) {
                    $rfLabels[] = $x[1];
                }
            }
        }
        return [
            'c' => $c, 'sid' => $sid, 'sp' => Masters::supplier($sid), 'iv' => $iv, 'F' => $F, 'tab' => array_key_exists($tab, self::TABS) ? $tab : 'general',
            'errs' => $iv ? $this->errors($c, $sid, $F) : [], 'closed' => $closed, 'revoked' => !empty($iv['revoked']), 'done' => !empty($iv['submitted']),
            'revising' => !empty($F['revising']) && !empty($iv['submitted']), 'extra' => $rfLabels,
        ];
    }

    // =====================================================================================================
    // supplier actions (public portal)
    // =====================================================================================================
    /** First open of the link: Sent -> Viewed, masked IP, audit (prototype portalRfq side effect). */
    public function markViewed(string $caseId, string $sid, ?string $ip): void
    {
        $c0 = CaseBook::findOrFail($caseId);
        $iv0 = $c0->invOf($sid);
        $mask = self::maskIp($ip);
        if (!$iv0 || !empty($iv0['submitted']) || $c0->ivDone($iv0) || ($iv0['state'] !== 'Sent' && ($iv0['ip'] ?? null) === $mask)) {
            return;
        }
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid, $mask) {
            foreach ($c->s['inv'] as $k => $iv) {
                if ($iv['sid'] === $sid) {
                    $first = $iv['state'] === 'Sent';
                    if ($first) {
                        $c->s['inv'][$k]['state'] = 'Viewed';
                    }
                    $c->s['inv'][$k]['ip'] = $mask;
                    if ($first) {
                        Workflow::log('Link viewed', $iv['rfq'], Masters::supplier($sid)['name'], ['by' => 'Supplier', 'cid' => $c->id]);
                    }
                }
            }
        });
    }

    public static function maskIp(?string $ip): string
    {
        if (!$ip) {
            return '[MASKED-IP]';
        }
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 2)) . ':xxxx';
        }
        $p = explode('.', $ip);
        return ($p[0] ?? 'x') . '.' . ($p[1] ?? 'x') . '.xxx.xxx';
    }

    /**
     * Save the posted part of the form into the draft (any tab). $in = ['h' => [...], 'l' => [pid => [...]], 'decl' => '0|1'].
     * $files = ['h' => [k => UploadedFile], 'l' => [pid => [k => UploadedFile]]]. $explicitDraft = the supplier pressed "Save draft" (audit + state).
     */
    public function saveForm(string $caseId, string $sid, array $in, array $files = [], bool $explicitDraft = false): void
    {
        $fileErr = null; // a bad attachment must not discard the other values the supplier typed: save them, then report it
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid, $in, $files, $explicitDraft, &$fileErr) {
            $iv = $c->invOf($sid) ?? throw new BusinessRule('Invalid link.');
            if (self::closedFor($iv)) {
                throw new BusinessRule('Submission closed — draft not saved.');
            }
            $F = $this->form($c, $sid);
            if (!empty($iv['submitted']) && empty($F['revising'])) {
                throw new BusinessRule('This quotation is already submitted. Use “Revise quotation” to change it.');
            }
            foreach (QuoteSpec::fields() as $f) {
                if (!empty($f['ro'])) {
                    continue;
                }
                $pids = $f['s'] === 'h' ? [''] : $iv['pids'];
                foreach ($pids as $pid) {
                    if ($f['s'] === 'l' && !QuoteSpec::fApplies($f, $pid)) {
                        continue;
                    }
                    $src = $f['s'] === 'h' ? ($in['h'] ?? []) : (($in['l'] ?? [])[$pid] ?? []);
                    if ($f['t'] === 'file') {
                        $up = $f['s'] === 'h' ? ($files['h'][$f['k']] ?? null) : ($files['l'][$pid][$f['k']] ?? null);
                        if ($up instanceof UploadedFile) {
                            try {
                                $name = $this->storeUpload($c->id, $sid, $up, $f['s'] === 'h' ? $f['k'] : "{$pid}.{$f['k']}", $F);
                            } catch (BusinessRule $e) {
                                $fileErr ??= $e->getMessage();
                                continue;
                            }
                            if ($f['s'] === 'h') {
                                $F['h'][$f['k']] = $name;
                            } else {
                                $F['l'][$pid][$f['k']] = $name;
                            }
                        }
                        continue;
                    }
                    if (!array_key_exists($f['k'], $src)) {
                        continue;
                    }
                    if ($f['t'] === 'tiers') {
                        $T = [];
                        for ($i = 0; $i < 3; $i++) {
                            $T[] = ['min' => (string) ($src['tiers'][$i]['min'] ?? ''), 'price' => (string) ($src['tiers'][$i]['price'] ?? '')];
                        }
                        $F['l'][$pid]['tiers'] = $T;
                        continue;
                    }
                    $val = is_array($src[$f['k']]) ? '' : trim((string) ($src[$f['k']] ?? ''));
                    if ($f['s'] === 'h') {
                        $F['h'][$f['k']] = $val;
                    } else {
                        $F['l'][$pid][$f['k']] = $val;
                    }
                }
            }
            if (array_key_exists('decl', $in)) {
                $F['decl']['ok'] = (string) $in['decl'] === '1';
            }
            $c->s['portal'][$sid] = $F;
            if ($explicitDraft) {
                foreach ($c->s['inv'] as $k => $x) {
                    if ($x['sid'] === $sid && empty($x['submitted'])) {
                        $c->s['inv'][$k]['state'] = 'Draft started';
                    }
                }
                Workflow::log('Draft saved', $iv['rfq'], Masters::supplier($sid)['name'], ['by' => 'Supplier', 'cid' => $c->id]);
            }
        });
        if ($fileErr) {
            throw new BusinessRule($fileErr);
        }
    }

    /** Store an uploaded supporting document (pdf/xlsx/jpg/png, max 10 MB) on the private disk; the form keeps the original file name. */
    private function storeUpload(string $caseId, string $sid, UploadedFile $f, string $key, array &$F): string
    {
        $ext = strtolower($f->getClientOriginalExtension());
        if (!$f->isValid() || !in_array($ext, self::UPLOAD_EXT, true) || $f->getSize() > 10 * 1024 * 1024) {
            throw new BusinessRule('Attachments must be PDF, XLSX, JPG or PNG files up to 10 MB.');
        }
        $name = Str::limit(preg_replace('/[^\w.\- ]+/u', '_', basename($f->getClientOriginalName())), 120, '');
        $path = $f->storeAs("rfq-docs/{$caseId}/{$sid}", Str::random(10) . '-' . $name, 'local');
        $F['files'][$key] = $path;
        return $name;
    }

    /** pRevise: while the link is open, start editing a new revision. The previous submission stays valid and evaluated until the new one is submitted. */
    public function revise(string $caseId, string $sid): int
    {
        return CaseBook::tx($caseId, function (CaseBook $c) use ($sid) {
            $iv = $c->invOf($sid) ?? throw new BusinessRule('Invalid link.');
            if (self::closedFor($iv)) {
                throw new BusinessRule('Deadline passed.');
            }
            if (empty($iv['submitted'])) {
                throw new BusinessRule('Nothing to revise yet.');
            }
            $F = $this->fresh($c, $sid);
            $F['revising'] = true;
            $c->s['portal'][$sid] = $F;
            Workflow::log('Revision started', $iv['rfq'], Masters::supplier($sid)['name'] . ' · revision ' . ($iv['rev'] + 1), ['by' => 'Supplier', 'cid' => $c->id]);
            return $iv['rev'] + 1;
        });
    }

    public function cancelRevision(string $caseId, string $sid): void
    {
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid) {
            if (!empty($c->s['portal'][$sid]['revising'])) {
                unset($c->s['portal'][$sid]);
            }
        });
    }

    /**
     * pSubmit: final submission. Rejected server-side after the deadline / when revoked (exception "Late submission"; previous submissions untouched)
     * and while any required parameter is missing. @return array{ref:string,rev:int}
     */
    public function submit(string $caseId, string $sid): array
    {
        $c0 = CaseBook::findOrFail($caseId);
        $iv0 = $c0->invOf($sid) ?? throw new BusinessRule('Invalid link.');
        $sp0 = Masters::supplier($sid);
        if (self::closedFor($iv0)) {                                  // AC-06: server time is the authority
            Workflow::raise('Late submission', $iv0['rfq'] . '/' . $sid, "{$sp0['name']} attempted to submit after cut-off — rejected server-side", ['cid' => $caseId, 'by' => 'Supplier']);
            Workflow::log('Submission rejected', $iv0['rfq'], "{$sp0['name']} · after cut-off", ['by' => 'Supplier', 'cid' => $caseId]);
            throw new BusinessRule('Rejected: deadline passed (server time).');
        }
        $this->requireSubmittable($c0, $sid);
        $out = CaseBook::tx($caseId, function (CaseBook $c) use ($sid) {
            $this->requireSubmittable($c, $sid);
            $k = null;
            foreach ($c->s['inv'] as $i => $x) {
                if ($x['sid'] === $sid) {
                    $k = $i;
                }
            }
            $sp = Masters::supplier($sid);
            $F = $this->form($c, $sid);
            $iv = &$c->s['inv'][$k];
            $rev = ($iv['rev'] ?? 0) + 1;
            $prev = $c->s['quotes'][$sid] ?? [];
            if ($prev) {                                              // revisions are immutable: keep the superseded one
                $c->s['quoteHist'][$sid][] = ['rev' => $iv['rev'], 'ref' => $iv['quoRef'] ?? null, 'at' => $iv['submittedAt'] ?? null, 'hdr' => $c->hdrOf($sid), 'quotes' => $prev];
            }
            $now = Clock::ms();
            $H = $F['h'] + ['decAt' => $now];
            $c->s['quotes'][$sid] = [];
            $c->s['quoteHdr'][$sid] = $H;
            foreach ($iv['pids'] as $pid) {
                $line = $this->buildLine($c, $pid, $F, $prev[$pid] ?? [], $H);
                $line['rev'] = $rev;
                $c->s['quotes'][$sid][$pid] = $line;
            }
            $iv['submitted'] = true;
            $iv['state'] = 'Submitted';
            $iv['submittedAt'] = $now;
            $iv['rev'] = $rev;
            $iv['quoRef'] = $iv['quoRef'] ?? Workflow::nid('QUO');
            $o = ['cid' => $c->id, 'by' => 'Supplier'];
            if ($rev > 1) {
                Workflow::raise('Quote revision', $iv['quoRef'], "{$sp['name']} revision {$rev} — new timestamped version", $o);
            }
            $vars = $this->svcVars($c, $iv) + ['SUBMISSION_ID' => $iv['quoRef'], 'SUBMITTED_AT' => Fmt::dBD($now)];
            $to = ($sp['contact'] ?? '') . ' <' . ($sp['email'] ?? '') . '>';           // confirmation goes to the contact on file (before any change below)
            if (($H['email'] ?? '') !== ($sp['email'] ?? '') || ($H['contactName'] ?? '') !== ($sp['contact'] ?? '')) {
                Supplier::whereKey($sid)->update(['email' => $H['email'], 'contact' => $H['contactName'], 'email_verified' => false]);
                Masters::flush();
                Workflow::raise('Supplier contact changed', $sid, "{$sp['name']} changed PO contact — callback verification needed before any PO is sent", $o);
            }
            if (!empty($sp['address']) && ($H['address'] ?? '') !== $sp['address']) {
                Workflow::raise('Supplier identity changed', $sid, "{$sp['name']} registered address differs from the vendor master — confirm before award", $o);
            }
            if (preg_match('/except/i', $H['compliance'] ?? '')) {
                Workflow::raise('Spec deviation declared', $iv['quoRef'], "{$sp['name']} declared deviations: " . ($H['exceptions'] ?? ''), $o);
            }
            Workflow::mail($c->id, 'C', $to, $vars, ['ts' => $now]);
            Workflow::log('Quotation submitted', $iv['quoRef'], "{$sp['name']} · rev {$rev} · " . count($iv['pids']) . ' lines', $o);
            Workflow::notify('ok', "{$sp['name']} submitted {$iv['quoRef']}", 'src.quotes', ['role' => 'exec']);
            unset($c->s['portal'][$sid]);
            $res = ['ref' => $iv['quoRef'], 'rev' => $rev];
            unset($iv);
            return $res;
        });
        return $out;
    }

    private function svcVars(CaseBook $c, array $iv): array
    {
        return app(RfqService::class)->vars($c, $iv);
    }

    private function requireSubmittable(CaseBook $c, string $sid): void
    {
        $F = $this->form($c, $sid);
        $iv = $c->invOf($sid);
        if (!empty($iv['submitted']) && empty($F['revising'])) {
            throw new BusinessRule('This quotation is already submitted. Use “Revise quotation” to change it.');
        }
        $errs = $this->errors($c, $sid, $F);
        if ($errs) {
            throw new BusinessRule(count($errs) . ' required item(s) missing — final submit blocked.');
        }
    }

    /** Convert the supplier's form line into a case quote line (prototype buildLine). */
    private function buildLine(CaseBook $c, string $pid, array $F, array $pv, array $H): array
    {
        $l = $F['l'][$pid];
        $qty = $c->reqQty($pid);
        $tiers = array_values(array_filter(array_map(fn ($x) => ['min' => (float) ($x['min'] ?? 0), 'price' => (float) ($x['price'] ?? 0)], $l['tiers'] ?? []), fn ($x) => $x['min'] > 0 && $x['price'] > 0));
        usort($tiers, fn ($a, $b) => $a['min'] <=> $b['min']);
        $q = ['listPrice' => (float) $l['listPrice'], 'tiers' => $tiers, 'disc' => (float) ($l['disc'] ?: 0), 'moq' => (float) $l['moq']];
        $q['price'] = QuoteSpec::unitPriceFor($q, $qty);
        $mat = $q['price'] * $qty;
        $days = (float) $l['prodDays'] + (float) $l['shipDays'];
        $perCbm = (float) (Masters::setting('settings', [])['freightPerCbm'] ?? 0);
        $fpc = ($perCbm > 0 && (float) ($l['cbm'] ?? 0) > 0) ? (int) floor((float) $l['cbm'] * $perCbm + 0.5) : (int) floor($qty * .055 + 0.5);
        $spec = $l;
        foreach (['listPrice', 'tiers', 'disc', 'moq'] as $k) {
            unset($spec[$k]);
        }
        foreach (QuoteSpec::fields() as $f) {                         // keep numbers as numbers (like the seed) in the stored spec
            if ($f['s'] === 'l' && $f['t'] === 'number' && isset($spec[$f['k']]) && is_numeric($spec[$f['k']])) {
                $spec[$f['k']] = $spec[$f['k']] + 0;
            }
        }
        $q['moq'] = $q['moq'] + 0;
        return $q + [
            'orig' => $pv['orig'] ?? $q['price'], 'days' => $days + 0, 'freight' => $fpc, 'ins' => (int) floor($mat * .0065 + 0.5), 'duty' => (int) floor($mat * .15 + 0.5), 'bank' => QuoteSpec::bankCost($H),
            'q' => $pv['q'] ?? 70, 'sample' => $pv['sample'] ?? 'Pending', 'qty' => $qty, 'incoterm' => $H['incoterm'], 'place' => $H['place'], 'payment' => QuoteSpec::payText($H),
            'terms' => QuoteSpec::termsScore($H), 'hist' => $pv['hist'] ?? 60, 'validity' => self::dateMs($H['validity']), 'rev' => ($pv['rev'] ?? 0) + 1, 'spec' => $spec,
        ];
    }

    // =====================================================================================================
    // supplier PO acknowledgment (pAccept / pDispute / pDisputeSave) - delegates to PoService::acknowledge
    // =====================================================================================================
    /** POs of this supplier visible in the portal: dispatched and sent successfully. */
    public static function portalPos(CaseBook $c, string $sid): array
    {
        return array_values(array_filter($c->s['pos'], fn ($p) => $p['sid'] === $sid && !empty($p['sent']) && ($p['status'] ?? '') === 'Sent'));
    }

    /** $decision 'ok' = accept, 'no' = dispute (reason required). */
    public function acknowledgePo(string $caseId, string $sid, string $poNo, string $decision, ?string $reason = null): void
    {
        CaseBook::tx($caseId, function (CaseBook $c) use ($sid, $poNo, $decision, $reason) {
            $k = null;
            foreach ($c->s['pos'] as $i => $p) {
                if ($p['no'] === $poNo && $p['sid'] === $sid && !empty($p['sent']) && ($p['status'] ?? '') === 'Sent') {
                    $k = $i;
                }
            }
            if ($k === null) {
                throw new BusinessRule('Purchase order not found for this supplier.');
            }
            if (($c->s['pos'][$k]['ack'] ?? '') !== 'Awaiting') {
                throw new BusinessRule('This purchase order has already been acknowledged.');
            }
            if ($decision !== 'ok' && trim((string) $reason) === '') {
                throw new BusinessRule('Please describe the discrepancy.');
            }
            app(PoService::class)->acknowledge($c, $c->s['pos'][$k], $decision === 'ok' ? 'ok' : 'no', $reason);
        });
    }
}
