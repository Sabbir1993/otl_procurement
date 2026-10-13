<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\ExceptionRecord;
use App\Models\MailRecord;
use App\Services\PoService;
use App\Support\WorkingCase;
use Illuminate\Http\Request;

// Sidebar group: Purchase Orders. Thin controller -> PoService (rules, audit) -> Blade views.
class PurchaseOrderController extends Controller
{
    // ------------------------------------------------------------------ pages

    public function register(PoService $svc)
    {
        return view('purchase-orders.register', ['svc' => $svc, 'c' => $this->workingCase(), 'rows' => PoService::rows(), 'cases' => CaseBook::all()]);
    }

    public function show(Request $r, PoService $svc, string $id)
    {
        $c = PoService::locate($id) ?? abort(404);
        $po = $c->s['pos'][PoService::idx($c, $id)];
        $A = $c->s['appr'];
        return view('purchase-orders.show', [
            'svc' => $svc, 'c' => $c, 'po' => $po, 'tab' => $r->query('tab', 'doc'),
            'events' => AuditLog::where('ref', $id)->orWhere(fn ($q) => $q->where('ref', $A['id'] ?? '-')->where('action', 'like', '%PO%'))->orderByDesc('id')->limit(30)->get(),
            'mails' => MailRecord::where('ref', $id)->orderByDesc('ts_ms')->get(),
            'tpl' => EmailTemplate::find('PO'),
        ]);
    }

    public function acknowledgments(PoService $svc)
    {
        return view('purchase-orders.acknowledgments', ['svc' => $svc, 'rows' => array_values(array_filter(PoService::rows(), fn ($p) => $p['sent'] && $p['status'] === 'Sent'))]);
    }

    public function disputes(PoService $svc)
    {
        $rows = PoService::rows();
        return view('purchase-orders.disputes', [
            'svc' => $svc, 'c' => $this->workingCase(),
            'dis' => array_values(array_filter($rows, fn ($p) => $p['ack'] === 'Disputed')),
            'frozen' => array_values(array_filter($rows, fn ($p) => !empty($p['frozen']))),
            'exc' => ExceptionRecord::whereIn('type', ['PO dispute', 'Change after approval', 'PO email failed', 'PO vs PI mismatch', 'Bank details changed'])->orderByDesc('at_ms')->get(),
        ]);
    }

    // ------------------------------------------------------------------ actions

    public function gen(Request $r, PoService $svc)
    {
        $cid = $r->input('case') ?: WorkingCase::id();
        $n = $svc->genDrafts($cid);
        return $this->done($n ? 'Draft PO emails generated' : 'All POs of this case are already sent', $n ? 'ok' : 'warn');
    }

    public function dispatchSelected(Request $r, PoService $svc)
    {
        $d = $r->validate(['nos' => 'nullable|array', 'nos.*' => 'string']);
        $nos = $d['nos'] ?? [];
        if (!$nos) {
            throw new BusinessRule('Select PO(s) that are Ready to Dispatch (generate drafts first).');
        }
        $x = $svc->dispatchMany($nos);
        $parts = [];
        if ($x['sent']) {
            $parts[] = "{$x['sent']} PO(s) sent to the selected suppliers only";
        }
        if ($x['failed']) {
            $parts[] = "{$x['failed']} held — email provider failed, safe retry available";
        }
        if ($x['blocked']) {
            $parts[] = "{$x['blocked']} blocked by failed checks (exception raised)";
        }
        if ($x['skipped'] && !$parts) {
            $parts[] = 'Nothing sent — generate the draft emails first, or the PO was already sent';
        }
        return $this->done(implode(' · ', $parts), ($x['failed'] || $x['blocked']) ? 'bad' : ($x['sent'] ? 'ok' : 'warn'));
    }

    public function dispatchOne(Request $r, PoService $svc)
    {
        $d = $r->validate(['no' => 'required|string']);
        $res = $svc->dispatchOne($d['no']);
        return match ($res) {
            'sent' => $this->done('PO sent to supplier', 'ok'),
            'failed' => $this->done('Email provider failed — PO held, retry available', 'bad'),
            default => $this->done('Dispatch blocked — the PO failed its checks (exception raised). Fix the failed checks first.', 'bad'),
        };
    }

    public function schedule(Request $r, PoService $svc)
    {
        $d = $r->validate(['nos' => 'nullable|array', 'nos.*' => 'string', 'at' => 'required|string']);
        $n = $svc->schedule($d['nos'] ?? [], $d['at']);
        return $this->done("{$n} PO(s) scheduled", 'ok');
    }

    public function runDue(PoService $svc)
    {
        $x = $svc->runDue();
        return $this->done("{$x['sent']} scheduled PO(s) dispatched" . ($x['failed'] ? " · {$x['failed']} held (email provider failed)" : ''), $x['failed'] ? 'bad' : 'ok');
    }

    public function retry(Request $r, PoService $svc)
    {
        $d = $r->validate(['no' => 'required|string']);
        $ok = $svc->retry($d['no']);
        return $this->done($ok ? "{$d['no']} re-sent — same PO number, no duplicate" : 'Email provider failed again — PO still held', $ok ? 'ok' : 'bad');
    }

    public function remind(Request $r, PoService $svc)
    {
        $d = $r->validate(['no' => 'required|string']);
        $svc->remind($d['no']);
        return $this->done('Reminder (template G) sent', 'ok');
    }

    public function resolve(Request $r, PoService $svc)
    {
        $d = $r->validate(['no' => 'required|string', 'o' => 'required|in:stand,chg', 'r' => 'required|string|max:500']);
        $svc->resolveDispute($d['no'], $d['o'], $d['r']);
        return $this->done($d['o'] === 'stand' ? 'Dispute rejected — PO stands and awaits acknowledgment again' : 'Change accepted — PO frozen, award returned for re-approval', $d['o'] === 'stand' ? 'ok' : 'warn');
    }

    public function update(Request $r, PoService $svc)
    {
        $d = $r->validate(['no' => 'required|string', 'billTo' => 'nullable|string|max:300', 'shipTo' => 'nullable|string|max:300', 'tol' => 'nullable|string|max:60', 'packing' => 'nullable|string|max:300', 'docs' => 'nullable|string|max:400', 'notes' => 'nullable|string|max:500']);
        $svc->edit($d['no'], $d);
        return $this->done('PO details saved', 'ok');
    }

    public function devOk(Request $r, PoService $svc)
    {
        $d = $r->validate(['no' => 'required|string']);
        $svc->devOk($d['no']);
        return $this->done('Supplier written acceptance of the MOQ waiver recorded', 'ok');
    }
}
