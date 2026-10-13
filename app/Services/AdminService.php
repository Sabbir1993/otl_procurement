<?php

namespace App\Services;

use App\Domain\AdminCatalog;
use App\Domain\Masters;
use App\Domain\Perm;
use App\Domain\Workflow;
use App\Exceptions\BusinessRule;
use App\Models\AcceptanceTest;
use App\Models\Calendar;
use App\Models\EmailTemplate;
use App\Models\ExceptionRecord;
use App\Models\Material;
use App\Models\PolicyItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Administration actions (prototype administration.js / demo-utilities.js). Every action needs admin.cfg and writes an audit row. */
class AdminService
{
    // ---------- reads ----------
    public function users(): array
    {
        return User::orderBy('id')->get(['id', 'name', 'email', 'role', 'status'])->all();
    }

    public function materials(): array
    {
        return array_values(Masters::materials());
    }

    // ---------- users & roles ----------

    /** One-time temporary password (never logged / stored in plain text). */
    private function tempPassword(): string
    {
        return substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 12) . random_int(10, 99);
    }

    /** @return array{user:User,password:?string} password is set only for a newly created user - show it ONCE */
    public function userSave(array $d): array
    {
        Perm::need('admin.cfg');
        $role = $d['role'] ?? '';
        if (!isset(config('roles.roles')[$role])) {
            throw new BusinessRule('Choose a valid role.');
        }
        if (!in_array($d['status'] ?? '', ['Active', 'Inactive'], true)) {
            throw new BusinessRule('Invalid status.');
        }
        $name = trim((string) ($d['name'] ?? ''));
        $email = strtolower(trim((string) ($d['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessRule('A name and a valid e-mail are required.');
        }
        return DB::transaction(function () use ($d, $role, $name, $email) {
            $dupe = User::where('email', $email)->when(!empty($d['id']), fn ($q) => $q->where('id', '!=', $d['id']))->exists();
            if ($dupe) {
                throw new BusinessRule('That e-mail is already used by another user.');
            }
            if (!empty($d['id'])) {
                $u = User::findOrFail($d['id']);
                $losesAdmin = $u->role === 'admin' && $u->status === 'Active' && ($role !== 'admin' || $d['status'] !== 'Active');
                if ($u->id === auth()->id() && ($role !== $u->role || $d['status'] !== 'Active')) {
                    throw new BusinessRule("You can't change your own role or deactivate yourself.");
                }
                if ($losesAdmin && User::where('role', 'admin')->where('status', 'Active')->where('id', '!=', $u->id)->doesntExist()) {
                    throw new BusinessRule('At least one active System Administrator must remain.');
                }
                $u->update(['name' => $name, 'email' => $email, 'role' => $role, 'status' => $d['status']]);
                Workflow::log('User saved', $name, config("roles.roles.$role.n") . ' · ' . $d['status']);
                return ['user' => $u, 'password' => null];
            }
            $pw = $this->tempPassword();
            $u = User::create(['name' => $name, 'email' => $email, 'role' => $role, 'status' => $d['status'], 'password' => Hash::make($pw)]);
            Workflow::log('User saved', $name, config("roles.roles.$role.n") . ' · created');
            return ['user' => $u, 'password' => $pw];
        });
    }

    /** @return string new one-time temporary password */
    public function userResetPassword(int $id): array
    {
        Perm::need('admin.cfg');
        $u = User::findOrFail($id);
        $pw = $this->tempPassword();
        $u->update(['password' => Hash::make($pw)]);
        Workflow::log('User password reset', $u->name, '');
        return ['user' => $u, 'password' => $pw];
    }

    // ---------- materials & masters ----------

    public function materialSave(array $d): Material
    {
        Perm::need('admin.cfg');
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            throw new BusinessRule('Material name is required.');
        }
        if (!in_array($d['uom'] ?? '', ['m', 'kg', 'yd', 'roll'], true) || !in_array($d['type'] ?? '', ['fabric', 'powder', 'chemical'], true)) {
            throw new BusinessRule('Invalid UOM or type.');
        }
        $f = ['name' => $name, 'meta' => trim((string) ($d['meta'] ?? '')), 'comp' => trim((string) ($d['comp'] ?? '')), 'uom' => $d['uom'], 'type' => $d['type']];
        return DB::transaction(function () use ($d, $f) {
            if (!empty($d['id'])) {
                $m = Material::findOrFail($d['id']);
                $m->update($f);
                Workflow::log('Material edited', $m->id, $f['name']);
            } else {
                $n = max(1000, (int) Material::get()->map(fn ($x) => (int) substr($x->id, 2))->max()) + 1;
                $m = Material::create($f + ['id' => 'P-' . $n]);
                Workflow::log('Material added', $m->id, $f['name']);
            }
            Masters::flush();
            return $m;
        });
    }

    public function reasonAdd(string $r): void
    {
        Perm::need('admin.cfg');
        $r = trim($r);
        $list = Masters::setting('reasons', []);
        if ($r === '') {
            throw new BusinessRule('Enter a reason.');
        }
        if (in_array(mb_strtolower($r), array_map('mb_strtolower', $list), true)) {
            throw new BusinessRule('That reason already exists.');
        }
        $list[] = $r;
        Masters::putSetting('reasons', array_values($list));
        Workflow::log('Rejection reason added', 'Reasons', $r);
    }

    public function reasonDel(int $i): void
    {
        Perm::need('admin.cfg');
        $list = Masters::setting('reasons', []);
        if (!isset($list[$i])) {
            throw new BusinessRule('Reason not found (list changed) - reload the page.');
        }
        $gone = $list[$i];
        array_splice($list, $i, 1);
        Masters::putSetting('reasons', array_values($list));
        Workflow::log('Rejection reason removed', 'Reasons', $gone);
    }

    public function settingsSave(array $d): void
    {
        Perm::need('admin.cfg');
        $cur = Masters::setting('settings', []);
        foreach (['billTo', 'shipTo', 'tol', 'packing', 'docs'] as $k) {
            $cur[$k] = trim((string) ($d[$k] ?? ''));
        }
        $cur['freightPerCbm'] = max(0, (float) ($d['freightPerCbm'] ?? 0));
        Masters::putSetting('settings', $cur);
        Workflow::log('PO defaults saved', 'Settings', '');
    }

    // ---------- working-day calendars ----------

    public function calApprove(string $key): void
    {
        Perm::need('admin.cfg');
        DB::transaction(function () use ($key) {
            $c = Calendar::findOrFail($key);
            $c->approved = true;
            $c->years = array_values(array_unique(array_merge($c->years, [2026])));
            if ($key === 'KR' && !$c->holidays) {
                $c->holidays = ['2026-10-03', '2026-10-05', '2026-10-09'];
            }
            $c->save();
            ExceptionRecord::where('type', 'Calendar unavailable')->where('status', '!=', 'Resolved')->get()
                ->filter(fn ($e) => Supplier::find($e->ref)?->cal === $key)
                ->each(fn ($e) => Workflow::resolve('Calendar unavailable', $e->ref, 'Calendar approved'));
            Workflow::log('Calendar approved', $key, 'Demo data');
            Masters::flush();
        });
    }

    public function calRevoke(string $key): void
    {
        Perm::need('admin.cfg');
        Calendar::findOrFail($key)->update(['approved' => false]);
        Workflow::log('Calendar revoked', $key, '');
        Masters::flush();
    }

    /** Add a public holiday or make-up working day; its year becomes verified (prototype calHolSave). */
    public function calDay(string $key, string $date, string $kind): void
    {
        Perm::need('admin.cfg');
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date || !in_array($kind, ['holiday', 'makeup'], true)) {
            throw new BusinessRule('Enter a valid date and type.');
        }
        DB::transaction(function () use ($key, $date, $kind) {
            $c = Calendar::findOrFail($key);
            $col = $kind === 'holiday' ? 'holidays' : 'makeup';
            $other = $kind === 'holiday' ? 'makeup' : 'holidays';
            if (in_array($date, $c->{$col}, true)) {
                throw new BusinessRule("$date is already in the $kind list.");
            }
            if (in_array($date, $c->{$other}, true)) {
                throw new BusinessRule("$date is already listed as the opposite type (holiday vs make-up).");
            }
            $list = $c->{$col};
            $list[] = $date;
            sort($list);
            $c->{$col} = $list;
            $y = (int) substr($date, 0, 4);
            if (!in_array($y, $c->years, true)) {
                $c->years = array_merge($c->years, [$y]);
            }
            $c->save();
            Workflow::log('Calendar day added', $key, "$kind $date");
            Masters::flush();
        });
    }

    /** Mark a year's holiday data verified / unverified (unverified years block RFQ publication - Deadline::calc). */
    public function calYear(string $key, int $year, string $op): void
    {
        Perm::need('admin.cfg');
        if ($year < 2020 || $year > 2100) {
            throw new BusinessRule('Enter a valid year.');
        }
        $c = Calendar::findOrFail($key);
        $years = array_values(array_filter($c->years, fn ($y) => $y !== $year));
        if ($op === 'verify') {
            $years[] = $year;
            sort($years);
        }
        $c->years = $years;
        $c->save();
        Workflow::log($op === 'verify' ? 'Calendar year verified' : 'Calendar year unverified', $key, (string) $year);
        Masters::flush();
    }

    // ---------- templates / policy / authority / tests ----------

    /** @return string[] placeholders removed by the edit (caller shows a warning) */
    public function templateSave(string $code, string $subject, string $body): array
    {
        Perm::need('admin.cfg');
        $subject = trim($subject);
        if ($subject === '' || trim($body) === '') {
            throw new BusinessRule('Subject and body are required.');
        }
        $t = EmailTemplate::findOrFail($code);
        $ph = fn (string $s) => array_unique((function () use ($s) {
            preg_match_all('/\{[A-Z0-9_]+\}/', $s, $m);
            return $m[0];
        })());
        $lost = array_values(array_diff($ph($t->subject . ' ' . $t->body), $ph($subject . ' ' . $body)));
        $t->update(['subject' => $subject, 'body' => $body]);
        Workflow::log('Template edited', $code, $lost ? 'placeholders removed: ' . implode(' ', $lost) : '');
        return $lost;
    }

    public function policySave(int $n, array $d): void
    {
        Perm::need('admin.cfg');
        if (!in_array($d['st'] ?? '', ['Open', 'Confirmed'], true)) {
            throw new BusinessRule('Invalid status.');
        }
        $p = PolicyItem::findOrFail($n);
        $p->update(['owner' => trim((string) ($d['owner'] ?? '')), 'target_date' => $d['date'] ?? null, 'outcome' => trim((string) ($d['out'] ?? '')), 'status' => $d['st']]);
        Workflow::log('Policy item updated', '#' . $n, $d['st']);
    }

    public function authoritySave(int $i, string $lim, string $who): void
    {
        Perm::need('admin.cfg');
        $a = Masters::setting('authority', []);
        if (!isset($a[$i])) {
            throw new BusinessRule('Authority level not found.');
        }
        $a[$i]['lim'] = trim($lim);
        $a[$i]['who'] = trim($who);
        Masters::putSetting('authority', $a);
        Workflow::log('Authority rule saved', $a[$i]['lvl'], '');
    }

    public function testResult(string $code, ?string $result): void
    {
        Perm::need('admin.cfg');
        if (!in_array($result, [null, '', 'Pass', 'Fail'], true)) {
            throw new BusinessRule('Result must be Pass, Fail or blank.');
        }
        $t = AcceptanceTest::findOrFail($code);
        $t->update(['result' => $result ?: null]);
        Workflow::log('Acceptance result recorded', $code, $result ?: 'cleared');
    }

    // ---------- demo utilities ----------

    public function failMail(bool $on): void
    {
        Perm::need('admin.cfg');
        $f = Masters::setting('flags', []);
        $f['emailFail'] = $on;
        Masters::putSetting('flags', $f);
        Workflow::log('Email failure simulation', 'Flags', $on ? 'on' : 'off');
    }

    /** All data as an array, without secrets (password hashes, remember tokens, supplier-link tokens). */
    public function export(): array
    {
        Perm::need('admin.cfg');
        $secret = '/(^tok|token|secret|passw|hash|remember)/i';
        $scrub = function ($v) use (&$scrub, $secret) {
            if (!is_array($v)) {
                return $v;
            }
            $o = [];
            foreach ($v as $k => $x) {
                $o[$k] = (is_string($k) && preg_match($secret, $k)) ? '[REDACTED]' : $scrub($x);
            }
            return $o;
        };
        $plain = [];                                           // link tokens that may also appear inside archived e-mail bodies
        $collect = function ($v) use (&$collect, &$plain) {
            foreach ((array) $v as $k => $x) {
                if (is_array($x)) {
                    $collect($x);
                } elseif ($k === 'tokPlain' && is_string($x) && strlen($x) >= 6) {
                    $plain[] = $x;
                }
            }
        };
        $out = ['exported_at' => gmdate('c'), 'note' => 'Secrets (password hashes, tokens) are excluded.'];
        foreach (['materials', 'suppliers', 'supplier_products', 'calendars', 'exceptions', 'audit_logs', 'app_notifications', 'sequences', 'settings', 'email_templates', 'policy_items', 'acceptance_tests'] as $t) {
            $out[$t] = DB::table($t)->get()->map(fn ($r) => (array) $r)->all();
        }
        $out['purchase_cases'] = DB::table('purchase_cases')->get()->map(function ($r) use ($scrub, $collect) {
            $st = json_decode($r->state, true) ?: [];
            $collect($st);
            return ['id' => $r->id, 'name' => $r->name, 'month' => $r->month, 'created_by' => $r->created_by, 'state' => $scrub($st)];
        })->all();
        $out['mails'] = DB::table('mails')->get()->map(function ($r) use ($plain) {
            $r = (array) $r;
            $r['body'] = $plain ? str_replace($plain, '[REDACTED]', $r['body']) : $r['body'];
            return $r;
        })->all();
        $out['users'] = User::orderBy('id')->get(['id', 'name', 'email', 'role', 'status'])->toArray();   // no password / remember_token
        Workflow::log('Data exported', 'Demo Utilities', 'JSON export without secrets');
        return $out;
    }

    /** Load a case to a stage (prototype ACT.stageLoad). Local demo only - it overwrites the case. */
    public function loadStage(string $cid, string $stage): string
    {
        Perm::need('admin.cfg');
        if (!config('oilpms.demo_login')) {
            throw new BusinessRule('Stage loading is only available in the local demo environment.');
        }
        return app(StageLoader::class)->load($cid, $stage);
    }

    /** Reset to the seeded demo data (prototype resetDB). Local demo only. */
    public function reset(string $confirm): void
    {
        Perm::need('admin.cfg');
        if (!config('oilpms.demo_login')) {
            throw new BusinessRule('Reset is only available in the local demo environment.');
        }
        if ($confirm !== 'RESET') {
            throw new BusinessRule('Type RESET to confirm.');
        }
        (new DatabaseSeeder())->run();
        Masters::flush();
        Workflow::log('Demo data reset', 'Demo Utilities', 'Re-seeded from the prototype demo data');
    }
}
