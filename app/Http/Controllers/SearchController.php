<?php

namespace App\Http\Controllers;

use App\Domain\CaseBook;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Models\ExceptionRecord;
use Illuminate\Http\Request;

// Top-bar global search (port of prototype gsearch): grouped hits, max 5 per group, links only to pages the role may open.
class SearchController extends Controller
{
    public function __invoke(Request $r)
    {
        $q = mb_strtolower(trim((string) $r->query('q', '')));
        if ($q === '') {
            return response()->json(['groups' => []]);
        }
        $m = fn ($s) => str_contains(mb_strtolower((string) $s), $q);
        $role = Perm::role();
        $ok = fn (string $grp, string $route) => Perm::navAllows($grp, $route, $role);
        $cases = CaseBook::all();
        $G = [];
        $add = function (string $title, array $items) use (&$G) {
            if ($items) {
                $G[] = ['title' => $title, 'items' => array_slice($items, 0, 5)];
            }
        };

        $add('Purchase cases', array_values(array_map(
            fn ($c) => ['t' => $c->id . ' ' . $c->name, 's' => $c->caseStatus(), 'u' => route('case.show', $c->id)],
            array_filter($cases, fn ($c) => $m($c->id . $c->name . $c->month . ($c->s['booking']['serial'] ?? ''))))));
        if ($ok('src', 'src.rfq')) {
            $add('RFQs', array_values(array_map(
                fn ($c) => ['t' => $c->s['rfq']['no'], 's' => $c->id, 'u' => route('rfq.show', $c->id)],
                array_filter($cases, fn ($c) => !empty($c->s['rfq']['no']) && $m($c->s['rfq']['no'])))));
        }
        if ($ok('po', 'po.reg')) {
            $pos = [];
            foreach ($cases as $c) {
                foreach ($c->pos() as $p) {
                    if ($m($p['no'] . Masters::supplier($p['sid'])['name'])) {
                        $pos[] = ['t' => $p['no'] . ' ' . Masters::supplier($p['sid'])['name'], 's' => $c->id, 'u' => route('po.show', $p['no'])];
                    }
                }
            }
            $add('Purchase orders', $pos);
        }
        if ($ok('sd', 'sup.dir')) {
            $add('Suppliers', array_values(array_map(
                fn ($s) => ['t' => $s['name'], 's' => $s['id'], 'u' => route('sup.show', $s['id'])],
                array_filter(Masters::suppliers(), fn ($s) => $m($s['id'] . $s['name'] . ($s['contact'] ?? ''))))));
        }
        if ($ok('adm', 'ad.master')) {
            $add('Materials', array_values(array_map(
                fn ($p) => ['t' => $p['id'] . ' ' . $p['name'], 's' => '', 'u' => route('ad.master')],
                array_filter(Masters::materials(), fn ($p) => $m($p['id'] . $p['name'] . ($p['meta'] ?? ''))))));
        }
        if ($ok('ctl', 'exc')) {
            $add('Exceptions', ExceptionRecord::all()
                ->filter(fn ($e) => $m($e->id . $e->type . $e->ref . $e->detail))
                ->map(fn ($e) => ['t' => $e->id . ' ' . $e->type, 's' => (string) $e->ref, 'u' => route('exc')])->values()->all());
        }
        return response()->json(['groups' => $G]);
    }
}
