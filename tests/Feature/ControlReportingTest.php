<?php

namespace Tests\Feature;

use App\Domain\CaseBook;
use App\Domain\Fmt;
use App\Domain\Perm;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\ExceptionRecord;
use App\Models\MailRecord;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlReportingTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['exec', 'sup', 'mkt', 'qa', 'fin', 'ed', 'imp', 'admin', 'audit'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_pages_render_for_every_role_that_can_see_them(): void
    {
        $pages = ['home' => ['cc', 'home'], 'an.perf' => ['an', 'an.perf'], 'an.pipe' => ['an', 'an.pipe'], 'exc' => ['ctl', 'exc'], 'notif' => ['ctl', 'notif'], 'audit' => ['ctl', 'audit']];
        foreach (self::ROLES as $role) {
            foreach ($pages as $route => [$g, $p]) {
                $this->asRole($role);
                $res = $this->get(route($route));
                if (Perm::navAllows($g, $p, $role)) {
                    $res->assertOk();
                } else {
                    $res->assertRedirect(route('home'));
                }
            }
            if (Perm::navAllows('ctl', 'audit', $role)) {
                $this->get(route('audit', ['tab' => 'mail']))->assertOk();
                $this->get(route('audit', ['tab' => 'integ']))->assertOk();
            }
        }
    }

    public function test_command_center_shows_role_queue_and_drilldowns(): void
    {
        $this->asRole('sup')->get(route('home'))->assertOk()->assertSee('Needs your attention')->assertSee('Case pipeline')
            ->assertSee(route('exc') . '?exc%5Bf%5D%5Bst%5D=Open', false);
        // QA cannot open Exceptions -> the KPI is not a link
        $this->asRole('qa')->get(route('home'))->assertOk()->assertDontSee('href="' . route('exc'), false);
    }

    public function test_go_case_switches_working_case_and_redirects(): void
    {
        $this->asRole('exec')->post(route('home.goCase'), ['c' => 'IC-2026-11', 'to' => 'rq.board'])->assertRedirect(route('rq.board'));
        $this->assertSame('IC-2026-11', session('case_id'));
        $this->post(route('home.goCase'), ['c' => 'IC-2026-11', 'to' => 'nope'])->assertSessionHas('toast.kind', 'bad');
        $this->post(route('home.goCase'), ['c' => 'IC-9999-99', 'to' => 'rq.board'])->assertSessionHas('toast.kind', 'bad');
        $this->asRole('qa')->post(route('home.goCase'), ['c' => 'IC-2026-10', 'to' => 'exc'])->assertSessionHas('toast.kind', 'bad');
    }

    public function test_supervisor_resolves_exception_and_audit_is_written(): void
    {
        $e = ExceptionRecord::where('status', 'Open')->firstOrFail();
        $this->asRole('sup')->post(route('exc.save'), ['id' => $e->id, 'owner' => 'Finance', 'status' => 'Resolved', 'res' => 'Re-tendered with new deadline'])->assertSessionHas('toast.kind', 'ok');
        $e->refresh();
        $this->assertSame('Resolved', $e->status);
        $this->assertSame('Finance', $e->owner);
        $this->assertSame('Re-tendered with new deadline', $e->res);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Exception resolved', 'ref' => $e->ref]);
    }

    public function test_exception_can_be_reassigned_without_resolving(): void
    {
        $e = ExceptionRecord::where('status', 'Open')->firstOrFail();
        $this->asRole('fin')->post(route('exc.save'), ['id' => $e->id, 'owner' => 'QA / Marketing', 'status' => 'Open', 'res' => 'Waiting for QA'])->assertSessionHas('toast.kind', 'ok');
        $e->refresh();
        $this->assertSame('Open', $e->status);
        $this->assertSame('QA / Marketing', $e->owner);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Exception updated', 'ref' => $e->ref]);
    }

    public function test_exception_rules_are_enforced(): void
    {
        $e = ExceptionRecord::where('status', 'Open')->firstOrFail();
        $this->asRole('sup');
        // note mandatory to resolve
        $this->post(route('exc.save'), ['id' => $e->id, 'status' => 'Resolved', 'res' => ''])->assertSessionHas('toast.kind', 'bad');
        $this->assertSame('Open', $e->fresh()->status);
        // invalid status
        $this->post(route('exc.save'), ['id' => $e->id, 'status' => 'Closed', 'res' => 'x'])->assertSessionHas('toast.kind', 'bad');
        // nothing to update
        $this->post(route('exc.save'), ['id' => $e->id, 'status' => 'Open', 'owner' => $e->owner, 'res' => ''])->assertSessionHas('toast.kind', 'bad');
        // unknown id
        $this->post(route('exc.save'), ['id' => 'EXC-0000-00000', 'status' => 'Resolved', 'res' => 'x'])->assertSessionHas('toast.kind', 'bad');
        // already resolved
        $this->post(route('exc.save'), ['id' => $e->id, 'status' => 'Resolved', 'res' => 'done']);
        $this->post(route('exc.save'), ['id' => $e->id, 'status' => 'Resolved', 'res' => 'again'])->assertSessionHas('toast.kind', 'bad');
        $this->assertSame('done', $e->fresh()->res);
    }

    public function test_segregation_of_duties_blocks_resolving_exceptions(): void
    {
        $e = ExceptionRecord::where('status', 'Open')->firstOrFail();
        foreach (['exec', 'admin', 'audit'] as $role) {
            $this->asRole($role)->post(route('exc.save'), ['id' => $e->id, 'status' => 'Resolved', 'res' => 'try'])->assertSessionHas('toast.kind', 'warn');
        }
        $this->assertSame('Open', $e->fresh()->status);
        // button hidden for roles without exc.resolve
        $this->asRole('exec')->get(route('exc'))->assertOk()->assertDontSee('data-open="exc-res"', false);
        $this->asRole('sup')->get(route('exc'))->assertOk()->assertSee('data-open="exc-res"', false);
    }

    public function test_notifications_mark_read_and_open(): void
    {
        $n = AppNotification::where('read', false)->where(fn ($q) => $q->whereNull('role')->orWhere('role', 'sup'))->whereNotNull('link')->where('link', '!=', '')->firstOrFail();
        $this->asRole('sup')->post(route('notif.open', $n->id))->assertRedirect();
        $this->assertTrue($n->fresh()->read);

        $this->post(route('notif.readAll'))->assertSessionHas('toast.kind', 'ok');
        $this->assertSame(0, AppNotification::where('read', false)->where(fn ($q) => $q->whereNull('role')->orWhere('role', 'sup'))->count());

        // a notification addressed to another role is not reachable
        $other = AppNotification::create(['id' => 'NTEST1', 'ts_ms' => 1, 'sev' => 'info', 'txt' => 'fin only', 'link' => 'exc', 'read' => false, 'role' => 'fin']);
        $this->post(route('notif.open', $other->id))->assertSessionHas('toast.kind', 'bad');
        $this->assertFalse($other->fresh()->read);
        $this->post(route('notif.readAll'));
        $this->assertFalse($other->fresh()->read);   // role-scoped
    }

    public function test_audit_tabs_mail_view_and_csv_export(): void
    {
        $m = MailRecord::firstOrFail();
        $this->asRole('audit')->get(route('audit'))->assertOk()->assertSee('Audit log');
        $this->get(route('audit', ['tab' => 'mail', 'view' => $m->id]))->assertOk()->assertSee('Archived message · ' . $m->id)->assertSee($m->subject);
        $this->get(route('audit', ['tab' => 'mail', 'view' => 'MSG-NOPE']))->assertSessionHas('toast.kind', 'bad');

        $before = AuditLog::count();
        $expectRows = AuditLog::where('case_id', 'IC-2026-10')->count();
        $res = $this->get(route('audit.export', ['tab' => 'log', 'case' => 'IC-2026-10']));
        $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="oilpms_audit_', $res->headers->get('Content-Disposition'));
        $body = $res->getContent();
        $this->assertStringContainsString('"Time (BST)","User","Role","Action"', $body);
        $this->assertSame($expectRows, count(explode("\r\n", $body)) - 1);
        $this->assertSame($before + 1, AuditLog::count());   // export itself is audited
        $this->assertDatabaseHas('audit_logs', ['action' => 'Audit exported']);

        $mail = $this->get(route('audit.export', ['tab' => 'mail']));
        $this->assertStringContainsString('"Message ID"', $mail->getContent());
        $this->assertStringContainsString($m->id, $mail->getContent());
    }

    public function test_audit_export_is_blocked_for_roles_without_the_audit_page(): void
    {
        $this->asRole('qa')->get(route('audit.export'))->assertSessionHas('toast.kind', 'bad');
        $this->asRole('imp')->get(route('audit'))->assertRedirect(route('home'));
    }

    public function test_reports_are_computed_from_case_aggregates(): void
    {
        $cases = CaseBook::all();
        $awarded = array_sum(array_map(fn ($c) => array_sum(array_column($c->lineAwards(), 'amt')), $cases));
        $sv = array_sum(array_map(fn ($c) => $c->savings(), $cases));
        $this->assertGreaterThan(0, $awarded);
        $this->asRole('audit')->get(route('an.perf'))->assertOk()->assertSee(Fmt::usd($awarded))->assertSee($sv ? Fmt::usd($sv) : '—')->assertSee('RFQ response rate');
        $res = $this->get(route('an.pipe'))->assertOk();
        foreach ($cases as $c) {
            $res->assertSee($c->id);
        }
        // the completed case has a measurable case -> PO cycle time
        $res->assertSee(' d');
    }

    public function test_command_center_task_queues_follow_the_role(): void
    {
        $svc = app(\App\Services\CommandCenterService::class);
        $cases = CaseBook::all();
        $this->assertSame([], $svc->tasksFor('audit', $cases));
        $this->assertNotEmpty($svc->tasksFor('admin', $cases));
        $this->assertNotEmpty($svc->tasksFor('sup', $cases));
        foreach ($svc->tasksFor('exec', $cases) as $t) {
            $this->assertArrayHasKey('go', $t);
        }
    }
}
