<?php

namespace App\Http\Controllers;

use App\Domain\Masters;
use App\Services\RequisitionService;
use App\Support\WorkingCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

// Sidebar group: Requisitions (board + confirmed book qty). Thin: validate -> RequisitionService -> toast.
class RequisitionController extends Controller
{
    public function __construct(private RequisitionService $svc)
    {
    }

    private function cid(): string
    {
        return WorkingCase::id() ?? abort(404, 'No purchase case yet.');
    }

    // ---------------- pages
    public function board()
    {
        return view('requisitions.board', ['c' => $this->workingCase(), 'svc' => $this->svc, 'reasons' => (array) Masters::setting('reasons', []),
            'mats' => Masters::materials(), 'buyers' => RequisitionService::BUYERS, 'custs' => RequisitionService::CUSTS, 'mkts' => RequisitionService::MKTS]);
    }

    public function booking()
    {
        $c = $this->workingCase();
        return view('requisitions.booking', ['c' => $c, 'mats' => Masters::materials(), 'next' => $c ? $this->svc->nextSerial($c) : null]);
    }

    // ---------------- requisition lines
    public function accept(string $line)
    {
        $this->svc->accept($this->cid(), $line);
        return $this->done("Line {$line} accepted", 'ok');
    }

    public function revert(string $line)
    {
        $this->svc->revert($this->cid(), $line);
        return $this->done("Line {$line} back to pending");
    }

    public function destroy(string $line)
    {
        $this->svc->delete($this->cid(), $line);
        return $this->done("Line {$line} deleted");
    }

    public function bulkAccept(Request $r)
    {
        $ids = (array) $r->input('ids', []);
        if (!$ids) {
            return $this->done('Select at least one line first', 'warn');
        }
        $this->svc->bulkAccept($this->cid(), $ids);
        return $this->done(count($ids) . ' line(s) accepted', 'ok');
    }

    public function bulkReject(Request $r)
    {
        $ids = (array) $r->input('ids', []);
        if (!$ids) {
            return $this->done('Select at least one line first', 'warn');
        }
        $d = $r->validate(['reason' => 'required|string', 'note' => 'nullable|string|max:200']);
        $this->svc->bulkReject($this->cid(), $ids, $d['reason'], $d['note'] ?? null);
        return $this->done('Lines rejected', 'warn');
    }

    public function saveLine(Request $r)
    {
        $d = $r->validate([
            'id' => 'nullable|string|max:20', 'pid' => ['required', Rule::in(array_keys(Masters::materials()))],
            'buyer' => 'required|string|max:80', 'customer' => 'required|string|max:80', 'qty' => 'required|numeric',
            'need' => 'nullable|date', 'mkt' => 'nullable|string|max:60', 'remark' => 'nullable|string|max:200',
        ]);
        $this->svc->saveLine($this->cid(), $d);
        return $this->done('Requisition line saved', 'ok');
    }

    public function freeze()
    {
        $this->svc->freezeAction($this->cid());
        return $this->doneTo(route('rq.booking'), 'Requisition frozen → Import Booking Report ready', 'ok');
    }

    // ---------------- booking
    public function bkSave(Request $r)
    {
        $d = $r->validate(['pid' => 'required|string', 'pct' => 'nullable|numeric|min:0', 'qty' => 'required|numeric']);
        $this->svc->editBooking($this->cid(), $d['pid'], $d['pct'] ?? 0, $d['qty']);
        return $this->done('Book qty saved', 'ok');
    }

    public function bkDel(string $pid)
    {
        $this->svc->deleteBooking($this->cid(), $pid);
        return $this->done("{$pid} removed from the booking");
    }

    public function bkAdd(Request $r)
    {
        $d = $r->validate(['pid' => 'required|string', 'qty' => 'required|numeric']);
        $this->svc->addBooking($this->cid(), $d['pid'], $d['qty']);
        return $this->done("{$d['pid']} added to the booking", 'ok');
    }

    public function bkCsv(Request $r)
    {
        $r->validate(['csv' => 'required|file|max:512']);
        $n = $this->svc->importCsv($this->cid(), (string) file_get_contents($r->file('csv')->getRealPath()));
        return $this->done("{$n} line(s) updated from CSV", $n ? 'ok' : 'warn');
    }

    /** Import Booking Report download (CSV). */
    public function bkDl()
    {
        $rows = $this->svc->report($this->cid());
        $esc = fn ($v) => '"' . str_replace('"', '""', preg_match('/^[=+\-@]/', (string) $v) && !is_numeric($v) ? "'" . $v : (string) $v) . '"';   // no CSV formula injection
        $csv = "\xEF\xBB\xBF" . implode("\r\n", array_map(fn ($row) => implode(',', array_map($esc, $row)), $rows));
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="import-booking-report.csv"']);
    }

    public function bkConfirm(Request $r)
    {
        $this->need('booking.confirm');   // before validation / storing the file
        $d = $r->validate([
            'ref' => 'required|string|max:120', 'by' => 'required|string|max:80', 'date' => 'required|date',
            'doc' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], ['doc.required' => 'Attach the signed document.', 'ref.required' => 'Enter the signed document reference.']);
        $cid = $this->cid();
        $f = $r->file('doc');
        $path = $f->storeAs('booking-docs/' . $cid, bin2hex(random_bytes(8)) . '.' . $f->getClientOriginalExtension());   // private disk, never public
        try {
            $serial = $this->svc->confirm($cid, $d['ref'], ['doc' => $f->getClientOriginalName(), 'signedBy' => $d['by'], 'signedOn' => $d['date'], 'path' => $path]);
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw $e;
        }
        return $this->done("{$serial} confirmed", 'ok', ['url' => route('src.rfq', ['new' => 1]), 'label' => 'Create RFQ']);
    }
}
