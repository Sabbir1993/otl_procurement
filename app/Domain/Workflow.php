<?php

namespace App\Domain;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\ExceptionRecord;
use App\Models\MailRecord;
use Illuminate\Support\Facades\DB;

/**
 * Cross-cutting workflow side effects ported from the prototype: numbering (nid), audit log, in-app
 * notifications, exceptions (raise) and the e-mail archive (mail). Every state change in a service
 * should call log(); every business exception should call raise().
 *
 * $o options: by (actor label e.g. 'System'|'Supplier'), cid (case id), ts (epoch ms), role (notification role), pid (mail ref).
 */
class Workflow
{
    /** Default owner + severity per exception type (prototype EXT). */
    public const EXC = [
        'No bid' => ['Procurement approver', 'High'], 'Late submission' => ['Authorized Procurement manager', 'Medium'],
        'Calendar unavailable' => ['System admin / Procurement', 'High'], 'Quote incomplete' => ['Supplier', 'Low'],
        'Single source' => ['Procurement / Management', 'Medium'], 'Sample rejected' => ['QA / Marketing', 'High'],
        'Incoterms without place' => ['Procurement', 'Medium'], 'Quote revision' => ['Supplier / Procurement', 'Low'],
        'Change after approval' => ['Finance / Final approver', 'High'], 'PO email failed' => ['Procurement / IT', 'High'],
        'PO dispute' => ['Procurement / Finance', 'High'], 'PO vs PI mismatch' => ['Import / Finance', 'High'],
        'Bank details changed' => ['Finance', 'High'], 'MOQ waiver' => ['Procurement supervisor', 'Medium'],
        'Supplier contact changed' => ['Procurement supervisor', 'Medium'], 'Supplier identity changed' => ['Procurement supervisor', 'Medium'],
        'Spec deviation declared' => ['QA / Marketing', 'Medium'],
    ];

    /** Next number for a sequence: nid('PO') => PO-2026-00012. Atomic. */
    public static function nid(string $kind, int $year = 2026): string
    {
        return "{$kind}-{$year}-" . Fmt::pad(self::nextSeq($kind), 5);
    }

    public static function nextSeq(string $key): int
    {
        return DB::transaction(function () use ($key) {
            DB::table('sequences')->insertOrIgnore(['key' => $key, 'value' => 0]);
            DB::table('sequences')->where('key', $key)->increment('value');
            return (int) DB::table('sequences')->where('key', $key)->value('value');
        });
    }

    public static function rnd(int $n = 8): string
    {
        return substr(bin2hex(random_bytes((int) ceil($n / 2))), 0, $n);
    }

    public static function log(string $action, ?string $ref, ?string $detail = '', array $o = []): AuditLog
    {
        $by = $o['by'] ?? null;
        return AuditLog::create([
            'ts_ms' => $o['ts'] ?? Clock::ms(),
            'role' => $by ?: Perm::role(),
            'who' => $by ? ($by === 'Supplier' ? 'Supplier' : 'System') : Perm::user(),
            'action' => $action, 'ref' => $ref, 'detail' => $detail ?? '', 'case_id' => $o['cid'] ?? null,
        ]);
    }

    public static function notify(string $sev, string $txt, ?string $link = null, array $o = []): AppNotification
    {
        return AppNotification::create([
            'id' => 'N' . self::rnd(6), 'ts_ms' => $o['ts'] ?? Clock::ms(), 'sev' => $sev, 'txt' => $txt,
            'link' => $link, 'read' => false, 'role' => $o['role'] ?? null,
        ]);
    }

    /** Raise a business exception once per (type, ref) while unresolved. Returns the record or null if one is already open. */
    public static function raise(string $type, string $ref, string $detail, array $o = []): ?ExceptionRecord
    {
        if (ExceptionRecord::where('type', $type)->where('ref', $ref)->where('status', '!=', 'Resolved')->exists()) {
            return null;
        }
        [$owner, $sev] = self::EXC[$type] ?? ['Procurement', 'Medium'];
        $e = ExceptionRecord::create([
            'id' => self::nid('EXC'), 'type' => $type, 'ref' => $ref, 'detail' => $detail, 'owner' => $owner, 'sev' => $sev,
            'status' => 'Open', 'at_ms' => $o['ts'] ?? Clock::ms(), 'res' => '', 'case_id' => $o['cid'] ?? null,
        ]);
        self::log('Exception raised', $ref, "{$type}: {$detail}", $o);
        self::notify($sev === 'High' ? 'bad' : 'warn', "{$type} — {$ref}", 'exc', $o);
        return $e;
    }

    /** Resolve every open exception of $type for $ref. */
    public static function resolve(string $type, string $ref, string $note): void
    {
        ExceptionRecord::where('type', $type)->where('ref', $ref)->where('status', '!=', 'Resolved')->update(['status' => 'Resolved', 'res' => $note]);
    }

    public static function fill(string $s, array $v): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($v[$m[1]] ?? $m[0]), $s);
    }

    /** Render a template and archive it in `mails` (prototype mail()). Delivery itself is a Mail integration (Policy item 12). */
    public static function mail(?string $caseId, string $code, string $to, array $vars, array $o = []): MailRecord
    {
        $t = EmailTemplate::findOrFail($code);
        // internal approval link = the award page of the case (behind login, so no secret token in the URL)
        $v = $vars + ['CONTACT_EMAIL' => '[OIL_CONTACT_EMAIL]', 'INTERNAL_APPROVAL_LINK' => $caseId ? \App\Support\Link::route('award.show', $caseId) : \App\Support\Link::to('/')];
        return MailRecord::create([
            'id' => 'MSG-' . self::rnd(6), 'code' => $code, 'to' => $to, 'subject' => self::fill($t->subject, $v), 'body' => self::fill($t->body, $v),
            'ts_ms' => $o['ts'] ?? Clock::ms(), 'status' => $o['status'] ?? 'Sent', 'case_id' => $caseId, 'ref' => $o['pid'] ?? null,
        ]);
    }
}
