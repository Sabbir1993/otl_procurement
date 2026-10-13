<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Models\AuditLog;
use App\Services\CaseService;
use App\Support\WorkingCase;
use Illuminate\Http\Request;

// Sidebar group: Purchase Cases. REFERENCE IMPLEMENTATION of the dynamic page pattern:
// thin controller -> service (business rules, audit) -> Blade view built from the case aggregate.
class PurchaseCaseController extends Controller
{
    public function index()
    {
        return view('cases.index', ['cases' => CaseBook::all()]);
    }

    public function show(Request $r, string $id)
    {
        $c = CaseBook::findOrFail($id);
        return view('cases.show', [
            'c' => $c, 'tab' => $r->query('tab', 'ov'),
            'events' => AuditLog::where('case_id', $id)->orWhere('ref', $id)->orderByDesc('id')->limit(40)->get(),
        ]);
    }

    public function store(Request $r, CaseService $svc)
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'month' => 'required|string', 'notes' => 'nullable|string|max:200']);
        $c = $svc->create($d);
        WorkingCase::set($c->id);
        return $this->doneTo(route('rq.board'), "Case {$c->id} created. Add requisition lines next.", 'ok');
    }

    public function work(string $id)
    {
        WorkingCase::set($id);
        return $this->done("Working case: {$id}");
    }
}
