<?php

namespace App\Http\Controllers;

use App\Domain\AdminCatalog;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Exceptions\SegregationOfDuties;
use App\Models\AcceptanceTest;
use App\Models\EmailTemplate;
use App\Models\PolicyItem;
use App\Services\AdminService;
use Illuminate\Http\Request;

// Sidebar group: Administration (System Administrator only - role_nav 'adm')
class AdminController extends Controller
{
    /** Pages are only for roles whose menu includes the Administration group. */
    private function guard(): void
    {
        if (!Perm::navAllows('adm', 'ad.roles')) {
            throw new SegregationOfDuties('admin.cfg');
        }
    }

    public function roles(Request $r, AdminService $svc)
    {
        $this->guard();
        return view('admin.roles', ['tab' => $r->query('tab', 'users'), 'users' => $svc->users()]);
    }

    public function masters(Request $r, AdminService $svc)
    {
        $this->guard();
        return view('admin.masters', ['tab' => $r->query('tab', 'mat'), 'mats' => $svc->materials(), 'reasons' => Masters::setting('reasons', []), 'set' => Masters::setting('settings', [])]);
    }

    public function calendars()
    {
        $this->guard();
        return view('admin.calendars', ['cals' => Masters::calendars()]);
    }

    public function templates(Request $r)
    {
        $this->guard();
        $all = EmailTemplate::orderBy('code')->get();
        $t = $all->firstWhere('code', $r->query('code')) ?? $all->first();
        return view('admin.templates', ['all' => $all, 't' => $t]);
    }

    public function policy()
    {
        $this->guard();
        return view('admin.policy', ['items' => PolicyItem::orderBy('n')->get(), 'authority' => Masters::setting('authority', [])]);
    }

    public function tests()
    {
        $this->guard();
        return view('admin.tests', ['res' => AcceptanceTest::pluck('result', 'code')->all(), 'acs' => AdminCatalog::ACS]);
    }

    public function demo()
    {
        $this->guard();
        return view('admin.demo', ['mailFail' => !empty(Masters::setting('flags', [])['emailFail']), 'stages' => AdminCatalog::STAGES, 'cases' => \App\Models\PurchaseCase::orderBy('id')->get(['id', 'month']), 'canReset' => (bool) config('oilpms.demo_login')]);
    }

    // ---------------- actions ----------------

    public function userSave(Request $r, AdminService $svc)
    {
        $d = $r->validate(['id' => 'nullable|integer', 'name' => 'required|string|max:120', 'email' => 'required|email|max:160', 'role' => 'required|string|max:16', 'status' => 'required|in:Active,Inactive']);
        $res = $svc->userSave($d);
        $back = back()->with('toast', ['msg' => 'User saved', 'kind' => 'ok']);
        // Temporary password: flashed ONCE for the admin to hand over; only its hash is stored, never logged.
        return $res['password'] ? $back->with('temp_password', ['email' => $res['user']->email, 'password' => $res['password']]) : $back;
    }

    public function userPassword(int $id, AdminService $svc)
    {
        $res = $svc->userResetPassword($id);
        return back()->with('toast', ['msg' => 'Temporary password generated', 'kind' => 'ok'])->with('temp_password', ['email' => $res['user']->email, 'password' => $res['password']]);
    }

    public function materialSave(Request $r, AdminService $svc)
    {
        $svc->materialSave($r->validate(['id' => 'nullable|string|max:16', 'name' => 'required|string|max:160', 'meta' => 'nullable|string|max:160', 'comp' => 'nullable|string|max:200', 'uom' => 'required|in:m,kg,yd,roll', 'type' => 'required|in:fabric,powder,chemical']));
        return $this->done('Material saved', 'ok');
    }

    public function reasonAdd(Request $r, AdminService $svc)
    {
        $svc->reasonAdd($r->validate(['r' => 'required|string|max:160'])['r']);
        return $this->done('Reason added', 'ok');
    }

    public function reasonDel(Request $r, AdminService $svc)
    {
        $svc->reasonDel((int) $r->validate(['i' => 'required|integer|min:0'])['i']);
        return $this->done('Reason removed');
    }

    public function settingsSave(Request $r, AdminService $svc)
    {
        $svc->settingsSave($r->validate(['billTo' => 'nullable|string|max:300', 'shipTo' => 'nullable|string|max:300', 'tol' => 'nullable|string|max:40', 'packing' => 'nullable|string|max:300', 'docs' => 'nullable|string|max:400', 'freightPerCbm' => 'nullable|numeric|min:0']));
        return $this->done('PO defaults saved', 'ok');
    }

    public function calApprove(string $key, AdminService $svc)
    {
        $svc->calApprove($key);
        return $this->done("$key calendar approved — add suppliers again in the RFQ", 'ok');
    }

    public function calRevoke(string $key, AdminService $svc)
    {
        $svc->calRevoke($key);
        return $this->done("$key calendar approval revoked", 'warn');
    }

    public function calDay(Request $r, string $key, AdminService $svc)
    {
        $d = $r->validate(['date' => 'required|date_format:Y-m-d', 'kind' => 'required|in:holiday,makeup']);
        $svc->calDay($key, $d['date'], $d['kind']);
        return $this->done('Calendar day added', 'ok');
    }

    public function calYear(Request $r, string $key, AdminService $svc)
    {
        $d = $r->validate(['year' => 'required|integer', 'op' => 'required|in:verify,unverify']);
        $svc->calYear($key, (int) $d['year'], $d['op']);
        return $this->done($d['op'] === 'verify' ? "{$d['year']} verified for $key" : "{$d['year']} un-verified for $key", $d['op'] === 'verify' ? 'ok' : 'warn');
    }

    public function templateSave(Request $r, string $code, AdminService $svc)
    {
        $d = $r->validate(['s' => 'required|string|max:300', 'b' => 'required|string|max:20000']);
        $lost = $svc->templateSave($code, $d['s'], $d['b']);
        return $lost ? $this->done('Template saved, but these placeholders were removed: ' . implode(' ', $lost), 'warn') : $this->done('Template saved', 'ok');
    }

    public function policySave(Request $r, int $n, AdminService $svc)
    {
        $svc->policySave($n, $r->validate(['owner' => 'nullable|string|max:120', 'date' => 'nullable|date_format:Y-m-d', 'out' => 'nullable|string|max:1000', 'st' => 'required|in:Open,Confirmed']));
        return $this->done('Policy item saved', 'ok');
    }

    public function authoritySave(Request $r, int $i, AdminService $svc)
    {
        $d = $r->validate(['lim' => 'nullable|string|max:120', 'who' => 'nullable|string|max:120']);
        $svc->authoritySave($i, (string) ($d['lim'] ?? ''), (string) ($d['who'] ?? ''));
        return $this->done('Authority rule saved', 'ok');
    }

    public function testResult(Request $r, string $code, AdminService $svc)
    {
        $svc->testResult($code, $r->validate(['res' => 'nullable|in:Pass,Fail'])['res'] ?? null);
        return $this->done("$code recorded");
    }

    public function failMail(Request $r, AdminService $svc)
    {
        $on = $r->boolean('on');
        $svc->failMail($on);
        return $this->done($on ? 'Next PO dispatch will fail — use Retry afterwards' : 'Email failure simulation off', 'warn');
    }

    public function export(AdminService $svc)
    {
        $json = json_encode($svc->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return response($json, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="oilpms-export-' . gmdate('Ymd-His') . '.json"']);
    }

    public function stageLoad(Request $r, AdminService $svc)
    {
        $d = $r->validate(['c' => 'required|string|max:20|exists:purchase_cases,id', 's' => 'required|string|max:10']);
        $label = $svc->loadStage($d['c'], $d['s']);
        return $this->doneTo(route('case.show', $d['c']), 'Case ' . $d['c'] . ' loaded to: ' . $label, 'ok');
    }

    public function reset(Request $r, AdminService $svc)
    {
        $svc->reset((string) $r->input('confirm'));
        return $this->doneTo(route('home'), 'Demo data reset', 'ok');
    }
}
