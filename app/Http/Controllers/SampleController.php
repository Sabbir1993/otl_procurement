<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Masters;
use App\Services\CsService;
use Illuminate\Http\Request;

// Sidebar group: Sourcing / RFQ - samples & QA
class SampleController extends Controller
{
    public function index()
    {
        $rows = [];
        foreach (CaseBook::all() as $c) {
            foreach ($c->s['booking']['lines'] as $l) {
                foreach (Masters::suppliers() as $sid => $sp) {
                    $q = $c->s['quotes'][$sid][$l['pid']] ?? null;
                    if ($q) {
                        $rows[] = ['cid' => $c->id, 'sid' => $sid, 'sp' => $sp, 'pid' => $l['pid'], 'q' => $q, 'locked' => !empty($c->s['csLocked'])];
                    }
                }
            }
        }
        return view('sourcing.samples', ['rows' => $rows]);
    }

    public function save(Request $r, CsService $svc)
    {
        $d = $r->validate(['cid' => 'required|string', 'sid' => 'required|string', 'pid' => 'required|string', 'res' => 'required|in:' . implode(',', CsService::SAMPLE_RESULTS),
            'rep' => 'nullable|string|max:200', 'note' => 'nullable|string|max:500']);
        $svc->sampleResult($d['cid'], $d['sid'], $d['pid'], $d['res'], $d['rep'] ?? '', $d['note'] ?? '');
        return $this->done($d['res'] === 'Fail' ? 'Sample failed — offer removed from the award and an exception raised.' : 'Sample result saved.', $d['res'] === 'Fail' ? 'warn' : 'ok');
    }
}
