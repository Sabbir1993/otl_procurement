<?php

namespace Tests\Feature;

use App\Domain\CaseBook;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    private const FRESH = 'IC-2026-11';   // seeded, not frozen
    private const DONE = 'IC-2026-10';    // frozen + confirmed

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $role, string $case = self::FRESH): static
    {
        return $this->asRole($role)->withSession(['case_id' => $case]);
    }

    private function demand(string $case = self::FRESH): array
    {
        return CaseBook::findOrFail($case)->s['demand'];
    }

    private function resetPending(): void
    {
        $c = CaseBook::findOrFail(self::FRESH);
        foreach ($c->s['demand'] as $i => $d) {
            $c->s['demand'][$i]['status'] = 'pending';
            $c->s['demand'][$i]['reason'] = '';
        }
        $c->save();
    }

    public function test_pages_render_for_every_role_that_sees_them(): void
    {
        foreach (['exec', 'sup', 'mkt', 'qa', 'fin', 'ed', 'imp', 'admin', 'audit'] as $role) {
            foreach (['rq.board', 'rq.booking', 'pr', 'ship', 'grn', 'pay'] as $r) {
                foreach ([self::FRESH, self::DONE] as $case) {
                    $this->as($role, $case)->get(route($r))->assertOk();
                }
            }
        }
        $this->as('exec')->get(route('rq.board') . '?new=1')->assertOk()->assertSee('data-open-on-new', false);
        $this->as('exec', self::DONE)->get(route('rq.booking.report'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_accept_revert_delete_and_audit(): void
    {
        $id = $this->demand()[0]['id'];
        $n = count($this->demand());
        $this->as('exec')->post(route('rq.line.accept', $id))->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('accepted', $this->demand()[0]['status']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Requisition accepted', 'ref' => $id, 'case_id' => self::FRESH]);
        $this->as('exec')->post(route('rq.line.revert', $id));
        $this->assertSame('pending', $this->demand()[0]['status']);
        $this->as('exec')->delete(route('rq.line.delete', $id));
        $this->assertCount($n - 1, $this->demand());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Requisition deleted', 'ref' => $id]);
    }

    public function test_bulk_accept_and_reject_with_reason(): void
    {
        $this->resetPending();
        $ids = array_column($this->demand(), 'id');
        $this->as('exec')->post(route('rq.bulk.accept'), ['ids' => array_slice($ids, 0, 3)]);
        $this->assertSame('accepted', $this->demand()[2]['status']);
        $this->as('exec')->post(route('rq.bulk.accept'), [])->assertSessionHas('toast.kind', 'warn');

        $reason = \App\Domain\Masters::setting('reasons')[0];
        $this->as('exec')->post(route('rq.bulk.reject'), ['ids' => [$ids[3]], 'reason' => $reason, 'note' => 'n'])->assertSessionHasNoErrors();
        $x = $this->demand()[3];
        $this->assertSame(['rejected', $reason], [$x['status'], $x['reason']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Bulk reject']);
        // reason must come from the settings list
        $this->as('exec')->post(route('rq.bulk.reject'), ['ids' => [$ids[4]], 'reason' => 'because'])->assertSessionHas('toast.kind', 'bad');
        $this->assertSame('pending', $this->demand()[4]['status']);
    }

    public function test_add_and_edit_line_rules(): void
    {
        $n = count($this->demand());
        $new = ['pid' => 'P-1001', 'buyer' => 'B', 'customer' => 'C', 'qty' => 500, 'mkt' => 'Nasrin A.'];
        $this->as('exec')->post(route('rq.line.save'), $new);
        $this->assertCount($n + 1, $this->demand());
        $line = collect($this->demand())->last();
        $this->assertSame(['pending', 500], [$line['status'], $line['qty']]);
        $this->as('exec')->post(route('rq.line.save'), ['id' => $line['id'], 'qty' => 700] + $new);
        $this->assertSame(700, collect($this->demand())->firstWhere('id', $line['id'])['qty']);
        // qty must be > 0
        $this->as('exec')->post(route('rq.line.save'), ['qty' => 0] + $new)->assertSessionHas('toast.kind', 'bad');
        $this->assertCount($n + 1, $this->demand());
    }

    public function test_cannot_freeze_with_pending_or_nothing_accepted(): void
    {
        $this->as('sup')->post(route('rq.freeze'))->assertSessionHas('toast.kind', 'bad');
        $this->assertFalse(CaseBook::findOrFail(self::FRESH)->s['frozen']);
        $ids = array_column($this->demand(), 'id');
        $this->as('sup')->post(route('rq.bulk.reject'), ['ids' => $ids, 'reason' => \App\Domain\Masters::setting('reasons')[0]]);
        $this->as('sup')->post(route('rq.freeze'))->assertSessionHas('toast.kind', 'bad');   // nothing accepted
        $this->assertFalse(CaseBook::findOrFail(self::FRESH)->s['frozen']);
    }

    public function test_freeze_builds_booking_then_locks_lines(): void
    {
        $ids = array_column($this->demand(), 'id');
        $this->as('sup')->post(route('rq.bulk.accept'), ['ids' => $ids]);
        $this->as('sup')->post(route('rq.freeze'))->assertRedirect(route('rq.booking'));
        $c = CaseBook::findOrFail(self::FRESH);
        $this->assertTrue($c->s['frozen']);
        $this->assertNotEmpty($c->s['booking']['lines']);
        $l = $c->s['booking']['lines'][0];
        $this->assertSame([100, $l['demand']], [$l['pct'], $l['qty']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Requisition frozen', 'ref' => self::FRESH]);
        // stage rules: no edits after freeze
        $this->as('sup')->post(route('rq.line.revert', $ids[0]))->assertSessionHas('toast.kind', 'bad');
        $this->as('sup')->post(route('rq.line.save'), ['pid' => 'P-1001', 'buyer' => 'B', 'customer' => 'C', 'qty' => 5])->assertSessionHas('toast.kind', 'bad');
        $this->as('sup')->post(route('rq.freeze'))->assertSessionHas('toast.kind', 'bad');
    }

    public function test_booking_edit_percentage_and_qty_rules(): void
    {
        $c = CaseBook::findOrFail(self::FRESH);
        $this->as('sup')->post(route('rq.bulk.accept'), ['ids' => array_column($c->s['demand'], 'id')]);
        $this->as('sup')->post(route('rq.freeze'));
        $l = CaseBook::findOrFail(self::FRESH)->s['booking']['lines'][0];
        $pid = $l['pid'];
        $get = fn () => collect(CaseBook::findOrFail(self::FRESH)->s['booking']['lines'])->firstWhere('pid', $pid);

        // pct changed, qty not matching -> qty recalculated from the pct
        $this->as('exec')->post(route('rq.bk.save'), ['pid' => $pid, 'pct' => 50, 'qty' => $l['qty']]);
        $this->assertSame((int) floor($l['demand'] * 0.5 + 0.5), $get()['qty']);
        $this->assertSame(50, $get()['pct']);
        // pct unchanged -> qty override kept
        $this->as('exec')->post(route('rq.bk.save'), ['pid' => $pid, 'pct' => 50, 'qty' => 123]);
        $this->assertSame(123, $get()['qty']);
        // zero qty rejected
        $this->as('exec')->post(route('rq.bk.save'), ['pid' => $pid, 'pct' => 50, 'qty' => 0])->assertSessionHas('toast.kind', 'bad');
        $this->assertSame(123, $get()['qty']);
        // delete + add back
        $this->as('exec')->delete(route('rq.bk.delete', $pid));
        $this->assertNull($get());
        $this->as('exec')->post(route('rq.bk.add'), ['pid' => $pid, 'qty' => 40]);
        $this->assertSame([0, 100, 40], [$get()['demand'], $get()['pct'], $get()['qty']]);
        $this->as('exec')->post(route('rq.bk.add'), ['pid' => $pid, 'qty' => 40])->assertSessionHas('toast.kind', 'bad');   // duplicate
        // CSV override
        $csv = UploadedFile::fake()->createWithContent('b.csv', "product_id,book_qty\n{$pid},777\nP-9999,5\n");
        $this->as('exec')->post(route('rq.bk.csv'), ['csv' => $csv])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame(777, $get()['qty']);
    }

    public function test_confirm_requires_signed_document_and_assigns_serial(): void
    {
        Storage::fake('local');
        $this->as('sup')->post(route('rq.bulk.accept'), ['ids' => array_column($this->demand(), 'id')]);
        $this->as('sup')->post(route('rq.freeze'));
        $doc = UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf');
        $ok = ['ref' => 'SIGN-REF-1', 'by' => 'Farhana S.', 'date' => '2026-10-13'];

        $this->as('sup')->post(route('rq.bk.confirm'), $ok)->assertSessionHasErrors('doc');                 // no file
        $this->as('sup')->post(route('rq.bk.confirm'), ['ref' => ''] + $ok + ['doc' => $doc])->assertSessionHasErrors('ref');
        $this->assertFalse(CaseBook::findOrFail(self::FRESH)->s['booking']['confirmed']);

        $this->as('sup')->post(route('rq.bk.confirm'), $ok + ['doc' => $doc])->assertSessionHas('toast.kind', 'ok');
        $b = CaseBook::findOrFail(self::FRESH)->s['booking'];
        $this->assertTrue($b['confirmed']);
        $this->assertMatchesRegularExpression('#^IMPBOK/2026/NOV/\d{2}$#', $b['serial']);
        $this->assertSame('SIGN-REF-1', $b['ref']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Booking confirmed', 'ref' => $b['serial']]);
        $this->assertDatabaseHas('app_notifications', ['role' => 'exec']);

        // locked afterwards
        $this->as('exec')->post(route('rq.bk.add'), ['pid' => 'P-1008', 'qty' => 1])->assertSessionHas('toast.kind', 'bad');
        $this->as('sup')->post(route('rq.bk.confirm'), $ok + ['doc' => $doc])->assertSessionHas('toast.kind', 'bad');
    }

    public function test_service_contracts_for_the_stage_loader(): void
    {
        $svc = app(\App\Services\RequisitionService::class);
        $c = CaseBook::findOrFail(self::FRESH);
        foreach ($c->s['demand'] as $i => $d) {
            $c->s['demand'][$i]['status'] = 'accepted';
        }
        $svc->freeze($c, 'System');
        $svc->confirmBooking($c, '[SIGNED-DOC-REF]', 'System', 1700000000000);
        $c->save();
        $c = CaseBook::findOrFail(self::FRESH);
        $this->assertTrue($c->s['booking']['confirmed']);
        $this->assertSame(1700000000000, $c->s['booking']['at']);
        $this->expectException(\App\Exceptions\BusinessRule::class);
        $svc->confirmBooking(CaseBook::findOrFail(self::FRESH), '', 'System');
    }

    public function test_segregation_of_duties(): void
    {
        $this->resetPending();
        $id = $this->demand()[0]['id'];
        $this->as('mkt')->post(route('rq.line.accept', $id))->assertSessionHas('toast.kind', 'warn');
        $this->assertSame('pending', $this->demand()[0]['status']);
        $this->as('mkt')->post(route('rq.line.save'), ['pid' => 'P-1001', 'buyer' => 'B', 'customer' => 'C', 'qty' => 5])->assertSessionHas('toast.kind', 'warn');
        $this->as('exec')->post(route('rq.freeze'))->assertSessionHas('toast.kind', 'warn');            // freeze = supervisor only
        $this->as('exec', self::DONE)->post(route('rq.bk.confirm'), ['ref' => 'x'])->assertSessionHas('toast.kind', 'warn');   // confirm = supervisor only
        $this->as('audit')->post(route('rq.bk.add'), ['pid' => 'P-1001', 'qty' => 1])->assertSessionHas('toast.kind', 'warn');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Requisition frozen', 'ref' => self::FRESH]);
    }
}
