<?php

namespace App\Http\Controllers;

use App\Domain\Perm;
use App\Services\ControlService;
use App\Support\ChartUi;
use App\Support\Ui;
use Illuminate\Http\Request;

// Sidebar group: Control
class ControlController extends Controller
{
    private function blocked(string $page)
    {
        return Perm::navAllows('ctl', $page) ? null : $this->doneTo(route('home'), 'Your role cannot open this page.', 'warn');
    }

    public function exceptions()
    {
        return $this->blocked('exc') ?? view('control.exceptions');
    }

    public function notifications(ControlService $svc)
    {
        return $this->blocked('notif') ?? view('control.notifications', ['list' => $svc->visibleNotifications()->orderByDesc('ts_ms')->limit(60)->get()]);
    }

    public function audit(Request $r, ControlService $svc)
    {
        if ($b = $this->blocked('audit')) {
            return $b;
        }
        $tab = in_array($r->query('tab'), ['log', 'mail', 'integ'], true) ? $r->query('tab') : 'log';
        return view('control.audit', ['tab' => $tab, 'integ' => $svc->integrations(), 'mail' => $r->query('view') ? $svc->mail((string) $r->query('view')) : null]);
    }

    public function saveException(Request $r, ControlService $svc)
    {
        $d = $r->validate(['id' => 'required|string|max:24', 'owner' => 'nullable|string|max:120', 'status' => 'required|string|max:12', 'res' => 'nullable|string|max:1000']);
        $e = $svc->saveException($d['id'], $d['owner'] ?? null, $d['status'], $d['res'] ?? null);
        return $this->done($e->status === 'Resolved' ? "Exception {$e->id} resolved" : "Exception {$e->id} updated", 'ok');
    }

    public function readAll(ControlService $svc)
    {
        $n = $svc->markAllRead();
        return $this->done($n ? "{$n} notification(s) marked read" : 'Nothing to mark — all caught up', 'ok');
    }

    public function openNotification(string $id, ControlService $svc)
    {
        $link = $svc->open($id);
        if ($link && ChartUi::canGo($link)) {
            try {
                return redirect(Ui::url($link));
            } catch (\Throwable) {
                // unknown link key -> stay on the list
            }
        }
        return $this->done($link ? 'Marked read — your role cannot open the linked page' : 'Marked read', $link ? 'warn' : 'ok');
    }

    public function exportAudit(Request $r, ControlService $svc)
    {
        [$csv, $name] = $svc->exportCsv((string) $r->query('tab', 'log'), $r->query('case') ?: null, $r->query('q'));
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
    }
}
