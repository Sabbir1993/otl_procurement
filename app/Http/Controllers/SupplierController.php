<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Services\SupplierService;
use Illuminate\Http\Request;

// Sidebar group: Suppliers (directory + profile; the portal belongs to module C)
class SupplierController extends Controller
{
    public function index(SupplierService $svc)
    {
        return view('suppliers.index', ['sups' => $svc->all(), 'products' => $svc->products()]);
    }

    public function show(Request $r, string $id, SupplierService $svc)
    {
        $s = $svc->find($id) ?? abort(404);
        $rfqs = [];
        $pos = [];
        foreach (CaseBook::all() as $c) {
            foreach ($c->s['inv'] as $i) {
                if ($i['sid'] === $id) {
                    $rfqs[] = ['id' => count($rfqs), 'c' => $c->id, 'rfq' => $i['rfq'], 'st' => $c->ivState($i), 'deadline' => $i['deadline'] ?? null, 'q' => !empty($i['submitted']) ? $i['quoRef'] : '—'];
                }
            }
            foreach ($c->s['pos'] as $p) {
                if ($p['sid'] === $id) {
                    $pos[] = ['id' => $p['no'], 'no' => $p['no'], 'c' => $c->id, 'total' => $p['total'], 'ack' => $p['ack']];
                }
            }
        }
        return view('suppliers.show', ['s' => $s, 'tab' => $r->query('tab', 'pf'), 'products' => $svc->products($id), 'rfqs' => $rfqs, 'pos' => $pos]);
    }

    private function rules(): array
    {
        return [
            'id' => 'nullable|string|max:16', 'name' => 'required|string|max:160', 'cc' => 'required|string|max:4', 'cal' => 'required|string|max:4',
            'contact' => 'required|string|max:120', 'desig' => 'nullable|string|max:120', 'address' => 'nullable|string|max:300',
            'email' => 'required|email|max:160', 'phone' => 'nullable|string|max:40', 'status' => 'required|in:Active,Inactive,Blocked',
        ];
    }

    public function store(Request $r, SupplierService $svc)
    {
        $s = $svc->save($r->validate($this->rules()));
        return $this->doneTo(route('sup.show', $s->id), 'Supplier saved' . ($s->email_verified ? '' : ' — contact is Unverified until a callback is recorded'), 'ok');
    }

    public function verify(Request $r, string $id, SupplierService $svc)
    {
        $d = $r->validate(['ref' => 'required|string|max:120']);
        $svc->verify($id, $d['ref']);
        return $this->done('Contact verified', 'ok');
    }
}
