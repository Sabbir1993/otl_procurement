<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Models\AuditLog;
use App\Services\PiService;
use App\Services\PoService;
use Illuminate\Http\Request;

// Sidebar group: Import & Warehouse (PI & banking documents, LC register). Shipment / GRN / payment stubs belong to module B.
class ImportController extends Controller
{
    // ------------------------------------------------------------------ pages

    public function pi(PiService $pis)
    {
        return view('import.pi', ['pis' => $pis, 'c' => $this->workingCase(), 'rows' => PoService::rows(), 'cases' => CaseBook::all()]);
    }

    public function showPi(Request $r, PiService $pis, string $id)
    {
        $c = PoService::locate($id) ?? abort(404);
        $po = $c->s['pos'][PoService::idx($c, $id)];
        return view('import.pi-show', [
            'pis' => $pis, 'c' => $c, 'po' => $po, 'pi' => $c->s['pis'][$id] ?? null, 'lc' => PiService::lcOf($c, $id),
            'events' => AuditLog::where('ref', $id)->whereIn('action', ['PI recorded', 'PI matched / approved', 'Clarification sent', 'Bank detail verified', 'Import checks complete', 'LC approved', 'LC opened', 'Exception raised'])->orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function lc()
    {
        return view('import.lc', ['rows' => PoService::rows(), 'pis' => app(PiService::class)]);
    }

    // ------------------------------------------------------------------ actions

    public function pick(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string']);
        $pis->pick($d['no']);
        return $this->doneTo(route('pi.show', $d['no']) . '?record=1', 'Record the supplier PI — pre-filled from the PO');
    }

    public function record(Request $r, PiService $pis)
    {
        $d = $r->validate([
            'no' => 'required|string', 'pino' => 'required|string|max:80', 'seller' => 'nullable|string|max:200', 'buyer' => 'nullable|string|max:200',
            'cur' => 'required|in:USD,EUR,CNY', 'inc' => 'required|in:FOB,CIF,CFR,FCA,EXW', 'pl' => 'nullable|string|max:120', 'pay' => 'nullable|string|max:200',
            'sh' => 'required|date', 'bc' => 'nullable|boolean',
            'lines' => 'required|array', 'lines.*.desc' => 'nullable|string|max:200', 'lines.*.hs' => 'nullable|string|max:60', 'lines.*.qty' => 'required|numeric', 'lines.*.price' => 'required|numeric',
        ]);
        $x = $pis->record($d['no'], $d + ['bc' => $r->boolean('bc')]);
        $msg = "PI rev {$x['pi']['ver']} saved" . ($x['bad'] ? ' — ' . count($x['bad']) . ' match point(s) differ from the PO (exception raised)' : ' — all 10 match points agree');
        return $this->doneTo(route('pi.show', $d['no']), $msg, $x['bad'] ? 'warn' : 'ok');
    }

    public function approve(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string']);
        $pis->approve($d['no']);
        return $this->done('PI approved for import processing', 'ok');
    }

    public function clarify(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string']);
        $pis->clarify($d['no']);
        return $this->done('Clarification logged. Record the corrected PI as a new revision.', 'ok');
    }

    public function bank(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string', 'k' => 'required|in:fin,sup']);
        $pis->bank($d['no'], $d['k']);
        return $this->done($d['k'] === 'fin' ? 'Bank details verified by Finance (1/2)' : 'Bank details verified by Supervisor (2nd check)', 'ok');
    }

    public function doc(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string', 'i' => 'required|integer', 'checked' => 'nullable|boolean']);
        $pis->doc($d['no'], (int) $d['i'], $r->boolean('checked'));
        return $this->done('Checklist updated');
    }

    public function docsDone(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string']);
        $pis->docsDone($d['no']);
        return $this->done('Import & banking checks complete — ready for Finance LC approval', 'ok');
    }

    public function lcApprove(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string']);
        $pis->lcApprove($d['no']);
        return $this->done('LC approved — Import can now open the LC', 'ok');
    }

    public function lcOpen(Request $r, PiService $pis)
    {
        $d = $r->validate(['no' => 'required|string', 'b' => 'required|string|max:120', 'r' => 'required|string|max:80', 'e' => 'required|date']);
        $pis->lcOpen($d['no'], $d['b'], $d['r'], $d['e']);
        return $this->done('LC opened — end of module scope', 'ok');
    }
}
