<?php

namespace App\View\Composers;

use App\Domain\CaseBook;
use App\Domain\Perm;
use App\Models\AppNotification;
use App\Models\ExceptionRecord;
use App\Models\PurchaseCase;
use App\Support\WorkingCase;
use Illuminate\View\View;

/** Feeds the app shell (sidebar / top bar) with role-filtered menu, badges, notifications and the working case. */
class ShellComposer
{
    public function compose(View $view): void
    {
        // composer runs for the layout + each partial; build once per request
        $req = request();
        if (!$req->attributes->has('shell')) {
            $req->attributes->set('shell', $this->build());
        }
        $view->with('shell', $req->attributes->get('shell'));
    }

    private function build(): array
    {
        $role = Perm::role();
        $case = WorkingCase::get();
        $badge = $this->badges($case, $role);

        $groups = [];
        foreach (config('menu') as $g) {
            $items = array_values(array_filter($g['items'], fn ($i) => Perm::navAllows($g['key'], $i['route'], $role)));
            if (!$items) {
                continue;
            }
            $on = false;
            foreach ($items as &$i) {
                $i['on'] = request()->routeIs(array_merge([$i['route']], $i['also'] ?? []));
                $i['badge'] = $badge[$i['route']] ?? 0;
                $on = $on || $i['on'];
            }
            unset($i);
            $groups[] = ['key' => $g['key'], 'title' => $g['title'], 'icon' => $g['icon'], 'items' => $items, 'on' => $on];
        }

        return [
            'role' => $role, 'roleName' => Perm::roleName($role), 'user' => Perm::user(),
            'initials' => collect(explode(' ', preg_replace('/[^A-Za-z ]/', '', Perm::user())))->map(fn ($x) => $x[0] ?? '')->take(2)->implode(''),
            'groups' => $groups, 'caseId' => $case?->id,
            'cases' => PurchaseCase::orderBy('id')->get(['id', 'month']),
            'bell' => AppNotification::where('read', false)->where(fn ($q) => $q->whereNull('role')->orWhere('role', $role))->count(),
            'demo' => (bool) config('oilpms.demo_login'),
            'roles' => Perm::roles(),
        ];
    }

    /** Menu badges keyed by route name (prototype navBadge). */
    private function badges(?CaseBook $c, string $role): array
    {
        $b = ['exc' => ExceptionRecord::where('status', 'Open')->count()];
        if (!$c) {
            return $b;
        }
        $b['rq.board'] = $c->s['frozen'] ? 0 : count(array_filter($c->s['demand'], fn ($d) => $d['status'] === 'pending'));
        $b['src.cs'] = count($c->s['inv']) ? $c->csCounts()['pend'] : 0;
        $b['aw.appr'] = (in_array($c->s['appr']['status'], ['mkt', 'fin', 'ed'], true) && $c->s['appr']['status'] === $role) ? 1 : 0;
        $b['po.disp'] = array_sum(array_map(fn ($x) => count(array_filter($x->s['pos'], fn ($p) => $p['ack'] === 'Disputed')), CaseBook::all()));
        return $b;
    }
}
