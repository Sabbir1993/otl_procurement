<?php

namespace App\Services;

use App\Domain\CaseBook;
use App\Domain\Masters;

/** Reports computed from real case aggregates (prototype an.perf / an.pipe). Measured data only. */
class ReportService
{
    /** @return array{kpi:array,rows:array} */
    public function performance(): array
    {
        $cases = CaseBook::all();
        $aw = [];
        $sv = 0;
        $samples = [];
        $inv = [];
        foreach ($cases as $c) {
            foreach ($c->lineAwards() as $x) {
                $aw[] = $x + ['cid' => $c->id];
            }
            $sv += $c->savings();
            foreach ($c->s['quotes'] as $q) {
                foreach ($q as $x) {
                    $samples[] = $x['sample'] ?? '';
                }
            }
            foreach ($c->s['inv'] as $i) {
                $inv[] = $i;
            }
        }
        $ev = array_filter($samples, fn ($x) => in_array($x, ['Pass', 'Fail', 'Conditional'], true));
        $submitted = count(array_filter($inv, fn ($i) => !empty($i['submitted'])));

        $rows = [];
        foreach (Masters::suppliers() as $sid => $sp) {
            $ivs = [];
            $qs = [];
            foreach ($cases as $c) {
                foreach ($c->s['inv'] as $i) {
                    if ($i['sid'] === $sid) {
                        $ivs[] = $i;
                    }
                }
                foreach ($c->s['quotes'][$sid] ?? [] as $x) {
                    $qs[] = $x;
                }
            }
            $won = array_values(array_filter($aw, fn ($x) => $x['sid'] === $sid));
            $rows[] = [
                'id' => $sid, 'name' => $sp['name'], 'inv' => count($ivs), 'sub' => count(array_filter($ivs, fn ($i) => !empty($i['submitted']))),
                'pass' => count(array_filter($qs, fn ($x) => ($x['sample'] ?? '') === 'Pass')), 'fail' => count(array_filter($qs, fn ($x) => ($x['sample'] ?? '') === 'Fail')),
                'nq' => count($qs), 'won' => count($won), 'val' => array_sum(array_column($won, 'amt')),
            ];
        }
        return [
            'rows' => $rows,
            'kpi' => [
                'savings' => $sv, 'awarded' => array_sum(array_column($aw, 'amt')), 'inv' => count($inv), 'submitted' => $submitted,
                'evaluated' => count($ev), 'passed' => count(array_filter($ev, fn ($x) => $x === 'Pass')),
            ],
        ];
    }

    /** One row per case with the stage timestamps (epoch ms or null). */
    public function pipeline(): array
    {
        $rows = [];
        foreach (CaseBook::all() as $c) {
            $s = $c->s;
            $subs = array_map(fn ($i) => $i['submittedAt'] ?? null, array_filter($s['inv'], fn ($i) => !empty($i['submitted'])));
            $subs = array_filter($subs);
            $sent = array_filter(array_map(fn ($i) => $i['sentAt'] ?? null, $s['inv']));
            $po = array_filter(array_map(fn ($p) => $p['sentAt'] ?? null, $s['pos']));
            $rows[] = [
                'id' => $c->id, 'c' => $c, 'stage' => $c->caseStatus(), 'created' => $s['created'] ?? null, 'book' => $s['booking']['at'] ?? null,
                'sent' => $sent ? min($sent) : null, 'lastQ' => $subs ? max($subs) : null,
                'ed' => $s['appr']['ed']['at'] ?? null, 'fp' => $po ? min($po) : null,
            ];
        }
        return $rows;
    }
}
