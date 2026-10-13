<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Services\ApprovalService;
use Illuminate\Http\Request;

// Sidebar group: Negotiation & Awards (+ approval actions)
class AwardController extends Controller
{
    public function negotiation()
    {
        $rows = [];
        foreach (CaseBook::all() as $c) {
            foreach ($c->s['booking']['lines'] as $l) {
                foreach ($c->csOf($l['pid'])['neg'] as $n) {
                    $rows[] = $n + ['pid' => $l['pid'], 'cid' => $c->id];
                }
            }
        }
        return view('awards.negotiation', ['rows' => $rows]);
    }

    public function recommendation()
    {
        return view('awards.recommendation', ['c' => $this->workingCase()]);
    }

    public function approvals()
    {
        return view('awards.approvals', ['cases' => array_values(array_filter(CaseBook::all(), fn ($c) => $c->s['appr']['status'] !== 'none'))]);
    }

    public function show(string $id)
    {
        return view('awards.show', ['c' => CaseBook::findOrFail($id)]);
    }

    public function submit(Request $r, ApprovalService $svc, string $id)
    {
        $d = $r->validate(['s' => 'required|string|max:1000']);
        $svc->submit($id, $d['s']);
        return $this->doneTo(route('award.show', $id), 'Award package submitted → Marketing ACK', 'ok');
    }

    public function decide(Request $r, ApprovalService $svc, string $id)
    {
        $d = $r->validate(['d' => 'required|in:ok,ret,rej', 'r' => 'nullable|string|max:1000']);
        $svc->decideAs($id, $d['d'], $d['r'] ?? null);
        return $this->done($d['d'] === 'ok' ? 'Decision recorded' : 'Package ' . ($d['d'] === 'ret' ? 'returned' : 'rejected'), $d['d'] === 'ok' ? 'ok' : 'warn');
    }

    public function change(Request $r, ApprovalService $svc, string $id)
    {
        $d = $r->validate(['f' => 'required|string', 'r' => 'required|string|max:1000']);
        $svc->changeRequest($id, $d['f'], $d['r']);
        return $this->done('Change request raised — unsent POs frozen, package returned for revision.', 'warn');
    }
}
