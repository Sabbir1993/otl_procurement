<?php

namespace App\Services;

use App\Domain\Clock;
use App\Domain\Fmt;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\ExceptionRecord;
use App\Models\MailRecord;
use Illuminate\Database\Eloquent\Builder;

/** Control menu: exceptions, notifications, audit trail & message archive (prototype menu/ctl/*). */
class ControlService
{
    // ---------- exceptions ----------
    /** Owners offered in the resolve dialog (prototype EXT owners + the record's current owner). */
    public static function owners(?string $current = null): array
    {
        $o = array_unique(array_merge(array_column(Workflow::EXC, 0), $current ? [$current] : []));
        sort($o);
        return $o;
    }

    /**
     * Resolve / update an exception (prototype excForm + excSave). Status Resolved needs the resolution note;
     * status Open only re-assigns the owner / records progress.
     */
    public function saveException(string $id, ?string $owner, string $status, ?string $note): ExceptionRecord
    {
        Perm::need('exc.resolve');
        $e = ExceptionRecord::find($id);
        if (!$e) {
            throw new BusinessRule("Exception {$id} not found.");
        }
        if ($e->status === 'Resolved') {
            throw new BusinessRule("{$e->id} is already resolved.");
        }
        if (!in_array($status, ['Open', 'Resolved'], true)) {
            throw new BusinessRule('Choose a valid status.');
        }
        $owner = trim((string) $owner) ?: $e->owner;
        $note = trim((string) $note);
        if ($status === 'Resolved' && $note === '') {
            throw new BusinessRule('Resolution / evidence is required to mark an exception resolved.');
        }
        if ($status === 'Open' && $note === '' && $owner === $e->owner) {
            throw new BusinessRule('Nothing to update — change the owner or add a note.');
        }
        $prev = $e->owner;
        $e->owner = $owner;
        $e->status = $status;
        if ($note !== '') {
            $e->res = $note;
        }
        $e->save();
        if ($status === 'Resolved') {
            Workflow::log('Exception resolved', $e->ref, "{$e->type} · {$note}", ['cid' => $e->case_id]);
        } else {
            Workflow::log('Exception updated', $e->ref, "{$e->type} · owner {$prev} → {$owner}" . ($note !== '' ? " · {$note}" : ''), ['cid' => $e->case_id]);
        }
        return $e;
    }

    // ---------- notifications ----------
    /** Query of the notifications visible to the current role (prototype: no role, own role, or admin sees all). */
    public function visibleNotifications(): Builder
    {
        $role = Perm::role();
        $q = AppNotification::query();
        if ($role !== 'admin') {
            $q->where(fn ($w) => $w->whereNull('role')->orWhere('role', '')->orWhere('role', $role));
        }
        return $q;
    }

    public function markAllRead(): int
    {
        return $this->visibleNotifications()->where('read', false)->update(['read' => true]);
    }

    /** Mark one notification read and return its target link key (prototype notifGo). */
    public function open(string $id): ?string
    {
        $n = $this->visibleNotifications()->where('id', $id)->first();
        if (!$n) {
            throw new BusinessRule('Notification not found.');
        }
        $n->read = true;
        $n->save();
        return $n->link ?: null;
    }

    // ---------- audit / mail archive ----------
    public function mail(string $id): MailRecord
    {
        $m = MailRecord::find($id);
        if (!$m) {
            throw new BusinessRule("Message {$id} not found.");
        }
        return $m;
    }

    /** CSV of the audit log or the message archive (filters: case, q). Writes an audit row. */
    public function exportCsv(string $tab, ?string $case, ?string $q): array
    {
        if (!Perm::navAllows('ctl', 'audit')) {
            throw new BusinessRule('Your role cannot export the audit trail.');
        }
        $q = trim((string) $q);
        $out = [];
        if ($tab === 'mail') {
            $rows = MailRecord::query()->when($case, fn ($w) => $w->where('case_id', $case))->orderByDesc('ts_ms')->get();
            $out[] = ['Time (BST)', 'Message ID', 'Case', 'To', 'Subject', 'Status'];
            foreach ($rows as $r) {
                $row = [Fmt::dS($r->ts_ms), $r->id, $r->case_id ?: '', $r->to, $r->subject, $r->status];
                if ($q === '' || stripos(implode(' ', $row), $q) !== false) {
                    $out[] = $row;
                }
            }
        } else {
            $tab = 'log';
            $rows = AuditLog::query()->when($case, fn ($w) => $w->where('case_id', $case))->orderByDesc('ts_ms')->orderByDesc('id')->get();
            $out[] = ['Time (BST)', 'User', 'Role', 'Action', 'Reference', 'Case', 'Detail'];
            foreach ($rows as $r) {
                $row = [Fmt::dS($r->ts_ms), $r->who, Perm::roleName($r->role) ?: $r->role, $r->action, $r->ref, $r->case_id ?: '', $r->detail];
                if ($q === '' || stripos(implode(' ', $row), $q) !== false) {
                    $out[] = $row;
                }
            }
        }
        $csv = "\xEF\xBB\xBF" . implode("\r\n", array_map(fn ($r) => implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $r)), $out));
        Workflow::log('Audit exported', 'audit', ($tab === 'mail' ? 'Message archive' : 'Audit log') . ' · ' . (count($out) - 1) . ' row(s)' . ($case ? " · {$case}" : ''), ['cid' => $case]);
        return [$csv, 'oilpms_' . ($tab === 'mail' ? 'messages' : 'audit') . '_' . gmdate('Ymd_His', intdiv(Clock::ms(), 1000)) . '.csv'];
    }

    /** Integrations tab rows (prototype). */
    public function integrations(): array
    {
        $fail = (bool) (\App\Domain\Masters::setting('flags', [])['emailFail'] ?? false);
        return [
            ['id' => 1, 'n' => 'Email provider', 'p' => 'RFQ, reminders, PO dispatch (queue, retry, delivery log)', 's' => $fail ? 'Failed' : 'Active', 'note' => MailRecord::count() . ' archived'],
            ['id' => 2, 'n' => 'ERP / inventory', 'p' => 'Requisitions, stock', 's' => 'TBD'],
            ['id' => 3, 'n' => 'Accounting / AP', 'p' => 'Invoice, payment', 's' => 'TBD'],
            ['id' => 4, 'n' => 'Warehouse / GRN', 'p' => 'Receiving', 's' => 'TBD'],
            ['id' => 5, 'n' => 'Document storage', 'p' => 'PO PDFs, signed documents, reports', 's' => 'TBD'],
            ['id' => 6, 'n' => 'Bank / import register', 'p' => 'LC, import permissions', 's' => 'TBD'],
            ['id' => 7, 'n' => 'BI dashboards', 'p' => 'Management reporting', 's' => 'TBD'],
        ];
    }
}
