<?php

namespace Tests\Feature;

use App\Domain\Deadline;
use App\Domain\Masters;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuppliersAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Masters::flush();
    }

    private function sup(array $o = []): array
    {
        return $o + ['name' => 'Acme Interlining Ltd', 'cc' => 'CN', 'cal' => 'CN', 'contact' => 'Wang Li', 'desig' => 'Sales', 'address' => '[ADDR]', 'email' => 'wang@acme.example', 'phone' => '[PHONE]', 'status' => 'Active'];
    }

    public function test_pages_render_for_roles(): void
    {
        foreach (['exec', 'sup', 'audit', 'admin'] as $r) {
            $this->asRole($r)->get(route('sup.dir'))->assertOk();
            $this->get(route('sup.show', 'SUP-00001'))->assertOk();
            foreach (['pf', 'mat', 'rfq', 'po'] as $t) {
                $this->get(route('sup.show', 'SUP-00001') . "?tab=$t")->assertOk();
            }
        }
        $this->asRole('admin');
        foreach (['ad.roles', 'ad.master', 'ad.cal', 'ad.tpl', 'ad.policy', 'ad.tests', 'ad.demo'] as $p) {
            $this->get(route($p))->assertOk();
        }
        $this->get(route('ad.roles', ['tab' => 'perm']))->assertOk();
        foreach (['rsn', 'po', 'fx'] as $t) {
            $this->get(route('ad.master', ['tab' => $t]))->assertOk();
        }
        $this->get(route('sup.show', 'NOPE'))->assertNotFound();
    }

    public function test_phone_and_address_masked_without_sup_edit(): void
    {
        Supplier::find('SUP-00001')->update(['phone' => '+8613800001234']);
        $this->asRole('audit')->get(route('sup.show', 'SUP-00001'))->assertDontSee('+8613800001234')->assertSee('••');
        $this->asRole('exec')->get(route('sup.show', 'SUP-00001'))->assertSee('+8613800001234');
    }

    public function test_exec_creates_supplier_unverified_with_audit(): void
    {
        $this->asRole('exec')->post(route('sup.store'), $this->sup())->assertRedirect(route('sup.show', 'SUP-00006'));
        $s = Supplier::find('SUP-00006');
        $this->assertNotNull($s);
        $this->assertFalse($s->email_verified);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Supplier saved', 'ref' => 'SUP-00006']);
    }

    public function test_contact_change_unverifies_and_raises_exception_then_callback_verifies(): void
    {
        $id = 'SUP-00001';
        $this->asRole('exec')->post(route('sup.store'), $this->sup(['id' => $id, 'name' => Supplier::find($id)->name, 'contact' => 'Lin Mei', 'email' => 'new@weiwen.example']));
        $this->assertFalse((bool) Supplier::find($id)->email_verified);
        $this->assertDatabaseHas('exceptions', ['type' => 'Supplier contact changed', 'ref' => $id, 'status' => 'Open']);

        $this->asRole('sup')->post(route('sup.verify', $id), ['ref' => 'CALL-123'])->assertSessionHas('toast.kind', 'ok');
        $this->assertTrue((bool) Supplier::find($id)->email_verified);
        $this->assertDatabaseHas('exceptions', ['type' => 'Supplier contact changed', 'ref' => $id, 'status' => 'Resolved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Supplier contact verified', 'ref' => $id]);
    }

    public function test_identity_change_raises_identity_exception(): void
    {
        $this->asRole('sup')->post(route('sup.store'), $this->sup(['id' => 'SUP-00001', 'name' => 'Renamed Co', 'contact' => 'Lin Mei', 'email' => 'lin.mei@weiwen.example']));
        $this->assertDatabaseHas('exceptions', ['type' => 'Supplier identity changed', 'ref' => 'SUP-00001']);
    }

    public function test_verify_sod_and_rules(): void
    {
        $s = Supplier::find('SUP-00002');
        $s->update(['email_verified' => false]);
        $this->asRole('exec')->post(route('sup.verify', 'SUP-00002'), ['ref' => 'X'])->assertSessionHas('toast.kind', 'warn');   // exec can edit but not verify
        $this->assertFalse((bool) Supplier::find('SUP-00002')->email_verified);
        $this->asRole('sup')->post(route('sup.verify', 'SUP-00001'), ['ref' => 'X'])->assertSessionHas('toast.kind', 'bad');       // already verified
        $this->asRole('mkt')->post(route('sup.store'), $this->sup())->assertSessionHas('toast.kind', 'warn');
    }

    public function test_users_create_temp_password_and_rules(): void
    {
        $res = $this->asRole('admin')->post(route('ad.user.save'), ['name' => 'New User', 'email' => 'new.user@oil.test', 'role' => 'exec', 'status' => 'Active']);
        $res->assertSessionHas('temp_password');
        $pw = session('temp_password.password');
        $u = User::where('email', 'new.user@oil.test')->first();
        $this->assertTrue(Hash::check($pw, $u->password));
        $this->assertNotSame($pw, $u->password);
        $this->assertDatabaseMissing('audit_logs', ['detail' => $pw]);
        $this->assertSame(0, \App\Models\AuditLog::where('detail', 'like', "%$pw%")->count());
        // duplicate e-mail, bad role, self-demotion
        $this->post(route('ad.user.save'), ['name' => 'Dup', 'email' => 'new.user@oil.test', 'role' => 'exec', 'status' => 'Active'])->assertSessionHas('toast.kind', 'bad');
        $this->post(route('ad.user.save'), ['name' => 'X', 'email' => 'x@oil.test', 'role' => 'nope', 'status' => 'Active'])->assertSessionHas('toast.kind', 'bad');
        $me = User::where('role', 'admin')->first();
        $this->post(route('ad.user.save'), ['id' => $me->id, 'name' => $me->name, 'email' => $me->email, 'role' => 'exec', 'status' => 'Active'])->assertSessionHas('toast.kind', 'bad');
        // edit + reset
        $this->post(route('ad.user.save'), ['id' => $u->id, 'name' => 'New User', 'email' => 'new.user@oil.test', 'role' => 'fin', 'status' => 'Inactive'])->assertSessionMissing('temp_password');
        $this->assertSame('fin', $u->fresh()->role);
        $this->post(route('ad.user.pw', $u->id))->assertSessionHas('temp_password');
        $this->asRole('exec')->post(route('ad.user.save'), ['name' => 'Z', 'email' => 'z@oil.test', 'role' => 'exec', 'status' => 'Active'])->assertSessionHas('toast.kind', 'warn');
    }

    public function test_materials_and_reasons_and_settings(): void
    {
        $this->asRole('admin')->post(route('ad.mat.save'), ['name' => 'Test Fabric', 'uom' => 'm', 'type' => 'fabric'])->assertSessionHas('toast.kind', 'ok');
        $this->assertDatabaseHas('materials', ['name' => 'Test Fabric']);
        $id = \App\Models\Material::where('name', 'Test Fabric')->value('id');
        $this->post(route('ad.mat.save'), ['id' => $id, 'name' => 'Test Fabric 2', 'uom' => 'kg', 'type' => 'powder']);
        $this->assertDatabaseHas('materials', ['id' => $id, 'name' => 'Test Fabric 2', 'uom' => 'kg']);

        $this->post(route('ad.reason.add'), ['r' => 'Budget freeze']);
        $this->assertContains('Budget freeze', Masters::setting('reasons'));
        $this->post(route('ad.reason.add'), ['r' => 'budget FREEZE'])->assertSessionHas('toast.kind', 'bad');
        $i = array_search('Budget freeze', Masters::setting('reasons'));
        $this->post(route('ad.reason.del'), ['i' => $i]);
        $this->assertNotContains('Budget freeze', Masters::setting('reasons'));

        $this->post(route('ad.settings.save'), ['billTo' => 'A', 'shipTo' => 'B', 'tol' => '±3%', 'packing' => 'P', 'docs' => 'D', 'freightPerCbm' => '12.5']);
        $this->assertSame(12.5, (float) Masters::setting('settings')['freightPerCbm']);
        $this->assertSame('±3%', Masters::setting('settings')['tol']);

        $this->asRole('sup')->post(route('ad.mat.save'), ['name' => 'Nope', 'uom' => 'm', 'type' => 'fabric'])->assertSessionHas('toast.kind', 'warn');
        $this->assertDatabaseMissing('materials', ['name' => 'Nope']);
    }

    public function test_calendars_drive_deadline_calc(): void
    {
        $issue = 1791172800000;
        $this->asRole('admin');
        $this->assertArrayHasKey('err', Deadline::calc('KR', $issue, 3));
        $this->post(route('ad.cal.on', 'KR'));
        Masters::flush();
        $this->assertArrayHasKey('ts', Deadline::calc('KR', $issue, 3));
        $this->post(route('ad.cal.off', 'KR'));
        Masters::flush();
        $this->assertArrayHasKey('err', Deadline::calc('KR', $issue, 3));
        // holiday adds + verifies year; duplicate rejected; year un-verify blocks calc
        $this->post(route('ad.cal.day', 'CN'), ['date' => '2027-01-01', 'kind' => 'holiday'])->assertSessionHas('toast.kind', 'ok');
        $this->assertContains(2027, \App\Models\Calendar::find('CN')->years);
        $this->post(route('ad.cal.day', 'CN'), ['date' => '2027-01-01', 'kind' => 'holiday'])->assertSessionHas('toast.kind', 'bad');
        $this->post(route('ad.cal.year', 'CN'), ['year' => 2026, 'op' => 'unverify']);
        Masters::flush();
        $this->assertArrayHasKey('err', Deadline::calc('CN', $issue, 3));
        $this->asRole('exec')->post(route('ad.cal.off', 'CN'))->assertSessionHas('toast.kind', 'warn');
    }

    public function test_templates_policy_authority_tests(): void
    {
        $this->asRole('admin');
        $t = \App\Models\EmailTemplate::first();
        $this->post(route('ad.tpl.save', $t->code), ['s' => 'New subject', 'b' => 'Body without placeholders'])->assertSessionHas('toast.kind', 'warn');
        $this->assertSame('New subject', $t->fresh()->subject);
        $this->post(route('ad.policy.save', 1), ['owner' => 'CFO', 'date' => '2026-12-01', 'out' => 'Decided', 'st' => 'Confirmed']);
        $this->assertDatabaseHas('policy_items', ['n' => 1, 'owner' => 'CFO', 'status' => 'Confirmed']);
        $this->post(route('ad.auth.save', 0), ['lim' => '[LIMIT]', 'who' => 'ED']);
        $this->assertSame('ED', Masters::setting('authority')[0]['who']);
        $this->post(route('ad.auth.save', 99), ['lim' => 'x', 'who' => 'y'])->assertSessionHas('toast.kind', 'bad');
        $this->post(route('ad.test.set', 'AC-01'), ['res' => 'Pass']);
        $this->assertDatabaseHas('acceptance_tests', ['code' => 'AC-01', 'result' => 'Pass']);
        $this->post(route('ad.test.set', 'AC-01'), ['res' => 'Fail']);
        $this->assertDatabaseHas('acceptance_tests', ['code' => 'AC-01', 'result' => 'Fail']);
        $this->post(route('ad.test.set', 'AC-01'), ['res' => '']);
        $this->assertDatabaseHas('acceptance_tests', ['code' => 'AC-01', 'result' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Acceptance result recorded', 'ref' => 'AC-01']);
        $this->asRole('exec')->post(route('ad.policy.save', 1), ['st' => 'Open'])->assertSessionHas('toast.kind', 'warn');
    }

    public function test_demo_utilities(): void
    {
        $this->asRole('admin')->post(route('ad.demo.mail'), ['on' => '1']);
        $this->assertTrue(Masters::setting('flags')['emailFail']);

        $resp = $this->post(route('ad.demo.export'));
        $resp->assertOk();
        $json = $resp->getContent();
        $this->assertStringNotContainsString('password', strtolower(json_encode(array_keys(json_decode($json, true)['users'][0]))));
        foreach (User::pluck('password') as $hash) {
            $this->assertStringNotContainsString($hash, $json);
        }
        $this->assertStringNotContainsString('2ff40cb498', $json);   // supplier link token from the seed
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertGreaterThan(0, count(json_decode($json, true)['purchase_cases']));

        // reset: blocked outside demo, needs confirm text, SoD
        config(['oilpms.demo_login' => false]);
        $this->post(route('ad.demo.reset'), ['confirm' => 'RESET'])->assertSessionHas('toast.kind', 'bad');
        config(['oilpms.demo_login' => true]);
        $this->post(route('ad.demo.reset'), ['confirm' => 'no'])->assertSessionHas('toast.kind', 'bad');
        $this->asRole('exec')->post(route('ad.demo.reset'), ['confirm' => 'RESET'])->assertSessionHas('toast.kind', 'warn');
        $this->assertTrue(Masters::setting('flags')['emailFail']);
        $this->asRole('admin')->post(route('ad.demo.reset'), ['confirm' => 'RESET'])->assertRedirect(route('home'));
        Masters::flush();
        $this->assertFalse(Masters::setting('flags')['emailFail']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Demo data reset']);
    }

    public function test_admin_pages_blocked_for_other_roles(): void
    {
        $this->asRole('exec')->get(route('ad.roles'))->assertRedirect();
    }
}
