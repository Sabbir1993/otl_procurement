<?php

namespace Database\Seeders;

use App\Models\AcceptanceTest;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Calendar;
use App\Models\EmailTemplate;
use App\Models\ExceptionRecord;
use App\Models\MailRecord;
use App\Models\Material;
use App\Models\PolicyItem;
use App\Models\PurchaseCase;
use App\Models\Sequence;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Loads the prototype's seeded demo database (database/seeders/data/prototype-db.json, produced by
 * `node _legacy/dump-seed.cjs` from the prototype engine's buildDB()): 3 cases (Sep = completed,
 * Oct = quotations received, Nov = fresh), masters, exceptions, audit, mails, notifications.
 * All values are MOCK; sensitive values use [PLACEHOLDER] format.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $src = json_decode(file_get_contents(database_path('seeders/data/prototype-db.json')), true);
        $db = $src['db'];
        $st = $src['static'];

        DB::transaction(function () use ($db, $st) {
            foreach (['materials', 'suppliers', 'supplier_products', 'calendars', 'purchase_cases', 'exceptions', 'audit_logs', 'app_notifications', 'mails', 'sequences', 'settings', 'email_templates', 'policy_items', 'acceptance_tests', 'saved_views'] as $t) {
                DB::table($t)->delete();
            }

            foreach ($st['MATS'] as $m) {
                Material::create(['id' => $m['id'], 'name' => $m['name'], 'meta' => $m['meta'], 'comp' => $m['comp'], 'uom' => $m['uom'], 'type' => $m['type']]);
            }

            foreach ($db['sup'] as $s) {
                Supplier::create([
                    'id' => $s['id'], 'name' => $s['name'], 'tag' => $s['tag'] ?? null, 'cc' => $s['cc'], 'cal' => $s['cal'],
                    'contact' => $s['contact'] ?? null, 'desig' => $s['desig'] ?? null, 'email' => $s['email'] ?? null,
                    'phone' => $s['phone'] ?? null, 'email_verified' => $s['emailVerified'] ?? true,
                    'status' => $s['status'] ?? 'Active', 'since' => $s['since'] ?? null, 'address' => $s['address'] ?? null,
                    'terms' => $st['SUPTERMS'][$s['id']] ?? null,
                ]);
            }
            foreach ($db['sp'] as $p) {
                SupplierProduct::create(['supplier_id' => $p['sid'], 'material_id' => $p['pid'], 'name' => $p['name'], 'active' => $p['active']]);
            }
            foreach ($db['cal'] as $key => $c) {
                Calendar::create(['key' => $key, 'name' => $c['name'], 'tz' => $c['tz'], 'off' => $c['off'], 'close' => $c['close'], 'approved' => $c['approved'],
                    'years' => $c['years'], 'holidays' => $c['holidays'], 'makeup' => $c['makeup']]);
            }

            foreach ($db['cases'] as $c) {
                $state = $c;
                foreach (['id', 'name', 'month', 'mon', 't0', 'created', 'by'] as $k) {
                    unset($state[$k]);
                }
                $state['created'] = $c['created'];
                PurchaseCase::create(['id' => $c['id'], 'name' => $c['name'], 'month' => $c['month'], 'mon' => $c['mon'], 't0_ms' => $c['t0'], 'created_by' => $c['by'], 'state' => $state]);
            }

            foreach ($db['exc'] as $e) {
                ExceptionRecord::create(['id' => $e['id'], 'type' => $e['type'], 'ref' => $e['ref'], 'detail' => $e['detail'], 'owner' => $e['owner'], 'sev' => $e['sev'], 'status' => $e['status'], 'at_ms' => $e['at'], 'res' => $e['res'], 'case_id' => $e['cid'] ?: null]);
            }
            foreach (array_reverse($db['audit']) as $a) {      // oldest first so ids ascend with time
                AuditLog::create(['ts_ms' => $a['ts'], 'role' => $a['role'], 'who' => $a['who'], 'action' => $a['a'], 'ref' => $a['ref'], 'detail' => $a['detail'], 'case_id' => $a['cid'] ?: null]);
            }
            foreach ($db['notifs'] as $n) {
                AppNotification::create(['id' => $n['id'], 'ts_ms' => $n['t'], 'sev' => $n['sev'], 'txt' => $n['txt'], 'link' => $n['link'], 'read' => $n['read'], 'role' => $n['role'] ?: null]);
            }
            foreach ($db['mails'] as $m) {
                MailRecord::create(['id' => $m['id'], 'code' => $m['code'], 'to' => $m['to'], 'subject' => $m['subject'], 'body' => $m['body'], 'ts_ms' => $m['ts'], 'status' => $m['status'], 'case_id' => $m['cid'] ?: null, 'ref' => $m['ref'] ?: null]);
            }
            foreach ($db['seq'] as $k => $v) {
                Sequence::create(['key' => $k, 'value' => $v]);
            }
            Setting::create(['key' => 'settings', 'value' => $db['settings']]);
            Setting::create(['key' => 'flags', 'value' => $db['flags']]);
            Setting::create(['key' => 'authority', 'value' => $db['authority']]);
            Setting::create(['key' => 'reasons', 'value' => $db['reasons']]);
            foreach ($db['tpl'] as $code => $t) {
                EmailTemplate::create(['code' => $code, 'name' => $t['n'], 'subject' => $t['s'], 'body' => $t['b']]);
            }
            foreach ($db['policy'] as $p) {
                PolicyItem::create(['n' => $p['n'], 'text' => $p['t'], 'owner' => $p['owner'], 'outcome' => $p['out'], 'target_date' => $p['date'], 'status' => $p['st']]);
            }
            foreach ($st['ACS'] as $a) {
                AcceptanceTest::create(['code' => $a[0], 'result' => $db['tests'][$a[0]] ?? null]);
            }

            // Demo users: one per role. Test credentials for local development only (see .env DEMO_PASSWORD).
            $pw = Hash::make(env('DEMO_PASSWORD', 'oilpms-demo'));
            foreach ($st['ROLES'] as $role => $r) {
                User::updateOrCreate(['email' => $role . '@oil-pms.test'], ['name' => $r['u'], 'role' => $role, 'status' => 'Active', 'password' => $pw]);
            }
        });
    }
}
