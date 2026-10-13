<?php

namespace Tests\Feature;

use App\Domain\CaseBook;
use App\Domain\Fmt;
use App\Domain\Masters;
use App\Models\ExceptionRecord;
use App\Models\MailRecord;
use App\Models\PurchaseCase;
use App\Models\Supplier;
use App\Services\PiService;
use App\Services\PoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module E: purchase orders, PI & banking documents, LC (AC-14 .. AC-19, segregation of duties). */
class PoImportTest extends TestCase
{
    use RefreshDatabase;

    private const CID = 'IC-2026-12';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Masters::flush();
    }

    /** A case at "ED approved": IC-2026-09's award/approval with the POs freshly generated (unsent). Returns sid => PO no. */
    private function approvedCase(bool $verifyAll = false): array
    {
        $src = PurchaseCase::findOrFail('IC-2026-09');
        $st = $src->state;
        $st['pos'] = [];
        $st['pis'] = [];
        $st['lc'] = [];
        PurchaseCase::create(['id' => self::CID, 'name' => 'Module E test', 'month' => 'Dec 2026', 'mon' => 'DEC', 't0_ms' => $src->t0_ms, 'created_by' => 'Test', 'state' => $st]);
        if ($verifyAll) {
            Supplier::query()->update(['email_verified' => true]);
            Masters::flush();
        }
        CaseBook::tx(self::CID, fn (CaseBook $c) => app(PoService::class)->generate($c));
        $map = [];
        foreach ($this->cb()->s['pos'] as $p) {
            $map[$p['sid']] = $p['no'];
        }
        return $map;
    }

    private function cb(): CaseBook
    {
        return CaseBook::findOrFail(self::CID);
    }

    private function po(string $no): array
    {
        $c = $this->cb();
        return $c->s['pos'][PoService::idx($c, $no)];
    }

    /** Mutate one PO of the test case directly (test fixture only). */
    private function tweak(string $no, callable $fn): void
    {
        CaseBook::tx(self::CID, function (CaseBook $c) use ($no, $fn) {
            $p = &$c->s['pos'][PoService::idx($c, $no)];
            $fn($p, $c);
        });
    }

    private function sendAll(array $map): void
    {
        $this->asRole('exec')->post(route('po.gen'), ['case' => self::CID]);
        $this->asRole('exec')->post(route('po.dispatch'), ['nos' => array_values($map)]);
    }

    private function piPayload(array $po, array $over = [], array $lineOver = []): array
    {
        $sp = Masters::supplier($po['sid']);
        $lines = [];
        foreach ($po['lines'] as $i => $l) {
            $lines[$i] = ['desc' => $l['name'], 'hs' => $l['hs'], 'qty' => $l['qty'], 'price' => $l['price']];
        }
        foreach ($lineOver as $i => $o) {
            $lines[$i] = $o + $lines[$i];
        }
        return $over + ['no' => $po['no'], 'pino' => '[SUPPLIER-PI-REF]', 'seller' => $sp['name'], 'buyer' => PiService::BUYER, 'cur' => 'USD', 'inc' => $po['inc'],
            'pl' => $po['place'], 'pay' => $po['payment'], 'sh' => Fmt::isoD($po['ship']), 'lines' => $lines];
    }

    private function accepted(string $no): void
    {
        $this->actingAs(\App\Models\User::where('role', 'exec')->first());
        PoService::with($no, fn ($c, &$po) => app(PoService::class)->acknowledge($c, $po, 'ok'));
    }

    private function excOpen(string $type, string $ref): bool
    {
        return ExceptionRecord::where('type', $type)->where('ref', $ref)->where('status', '!=', 'Resolved')->exists();
    }

    // ------------------------------------------------------------------ pages

    public function test_every_page_renders_for_every_role_that_can_see_it(): void
    {
        $map = $this->approvedCase(true);
        $this->sendAll($map);
        $no = reset($map);
        $this->accepted($no);
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)))->assertSessionHasNoErrors();
        $pages = [route('po.reg'), route('po.ack'), route('po.disp'), route('imp.pi'), route('imp.lc'), route('pi.show', $no)]
            + [10 => route('po.show', $no), 11 => route('po.show', $no) . '?tab=chk', 12 => route('po.show', $no) . '?tab=mail', 13 => route('po.show', $no) . '?tab=tl', 14 => route('po.show', 'PO-2026-00008'), 15 => route('pi.show', 'PO-2026-00008')];
        foreach (['exec', 'sup', 'mkt', 'qa', 'fin', 'ed', 'imp', 'admin', 'audit'] as $role) {
            foreach ($pages as $url) {
                $this->asRole($role)->get($url)->assertOk();
            }
        }
        $this->asRole('exec')->get(route('po.show', 'PO-NOPE'))->assertNotFound();
    }

    public function test_register_shows_the_correction_queue_and_unverified_supplier(): void
    {
        $map = $this->approvedCase();           // SUP-00003 contact is unverified in the seed
        $svc = app(PoService::class);
        $c = $this->cb();
        $po3 = $c->s['pos'][PoService::idx($c, $map['SUP-00003'])];
        $this->assertSame('Correction queue', $svc->status($c, $po3));
        $v = $svc->validate($c, $po3);
        $this->assertCount(5, $v);
        $this->assertFalse($v[3]['ok']);
        $this->assertSame('Ready to Dispatch', $svc->status($c, $c->s['pos'][PoService::idx($c, $map['SUP-00001'])]));
        $this->asRole('exec')->withSession(['case_id' => self::CID])->get(route('po.reg'))->assertOk()->assertSee('1 PO(s) in the correction queue', false);
    }

    public function test_buttons_are_hidden_for_roles_without_the_permission(): void
    {
        $map = $this->approvedCase(true);
        $no = reset($map);
        $w = ['case_id' => self::CID];
        $this->asRole('exec')->withSession($w)->get(route('po.reg'))->assertSee('Dispatch selected', false)->assertSee('Run due schedule', false)->assertSee('form="po-bulk"', false);
        foreach (['mkt', 'ed', 'fin', 'audit'] as $role) {
            $this->asRole($role)->withSession($w)->get(route('po.reg'))->assertDontSee('Dispatch selected', false);
        }
        $this->asRole('exec')->get(route('po.show', $no))->assertSee('Dispatch to supplier', false)->assertSee('Edit details', false);
        $this->asRole('mkt')->get(route('po.show', $no))->assertDontSee('Dispatch to supplier', false)->assertDontSee('Edit details', false);
        $this->asRole('exec')->get(route('imp.pi'))->assertSee('Record supplier PI', false);
        $this->asRole('mkt')->get(route('imp.pi'))->assertDontSee('Record supplier PI', false);
    }

    // ------------------------------------------------------------------ dispatch (AC-14, AC-15, AC-19)

    public function test_ac15_only_the_selected_supplier_receives_the_po(): void
    {
        $map = $this->approvedCase(true);
        $one = $map['SUP-00002'];
        $this->asRole('exec')->post(route('po.gen'), ['case' => self::CID]);
        $this->asRole('exec')->post(route('po.dispatch'), ['nos' => [$one]])->assertSessionHas('toast.kind', 'ok');
        $mails = MailRecord::where('case_id', self::CID)->where('code', 'PO')->get();
        $this->assertCount(1, $mails);
        $this->assertStringContainsString(Masters::supplier('SUP-00002')['email'], $mails[0]->to);
        $this->assertSame($one, $mails[0]->ref);
        $this->assertStringContainsString($one, $mails[0]->subject);          // template PO archived with this PO number
        $sent = array_filter($this->cb()->s['pos'], fn ($p) => $p['sent']);
        $this->assertCount(1, $sent);
        $this->assertSame('Awaiting', $this->po($one)['ack']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PO sent', 'ref' => $one]);
    }

    public function test_dispatch_needs_drafts_and_the_dispatch_permission(): void
    {
        $map = $this->approvedCase(true);
        $no = reset($map);
        $this->asRole('exec')->post(route('po.dispatch'), ['nos' => [$no]])->assertSessionHas('toast.kind', 'warn');   // drafts not generated
        $this->assertFalse($this->po($no)['sent']);
        foreach (['mkt', 'imp', 'fin', 'qa', 'ed', 'audit'] as $role) {
            $this->asRole($role)->post(route('po.dispatch1'), ['no' => $no])->assertSessionHas('toast.kind', 'warn');
            $this->asRole($role)->post(route('po.gen'), ['case' => self::CID])->assertSessionHas('toast.kind', 'warn');
        }
        $this->assertFalse($this->po($no)['sent']);
        $this->assertSame(0, MailRecord::where('case_id', self::CID)->count());
        $this->asRole('sup')->post(route('po.dispatch1'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');
        $this->assertTrue($this->po($no)['sent']);
    }

    public function test_ac14_missing_incoterms_place_blocks_dispatch_and_raises_an_exception(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->tweak($no, fn (&$p) => $p['place'] = '');
        $r = $this->asRole('exec')->post(route('po.dispatch1'), ['no' => $no]);
        $r->assertSessionHas('toast.kind', 'bad');
        $this->assertFalse($this->po($no)['sent']);
        $this->assertSame(0, MailRecord::where('case_id', self::CID)->where('code', 'PO')->count());
        $this->assertTrue($this->excOpen('Incoterms without place', $no));
        $this->assertDatabaseHas('audit_logs', ['action' => 'PO dispatch blocked', 'ref' => $no]);
        $svc = app(PoService::class);
        $this->assertSame('Correction queue', $svc->status($this->cb(), $this->po($no)));
        // bulk path is blocked too, other POs still go
        $this->asRole('exec')->post(route('po.gen'), ['case' => self::CID]);
        $this->asRole('exec')->post(route('po.dispatch'), ['nos' => [$no, $map['SUP-00002']]])->assertSessionHas('toast.kind', 'bad');
        $this->assertFalse($this->po($no)['sent']);
        $this->assertTrue($this->po($map['SUP-00002'])['sent']);
        // service-level gate holds even when called directly
        $this->asRole('exec');
        $this->assertFalse(PoService::with($no, fn ($c, &$p) => $svc->dispatch($c, $p)));
    }

    public function test_ac19_email_failure_holds_the_po_and_retry_reuses_the_same_po_number(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $n = count($this->cb()->s['pos']);
        $f = Masters::setting('flags', []);
        $f['emailFail'] = true;
        Masters::putSetting('flags', $f);
        $this->asRole('exec')->post(route('po.dispatch1'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');
        $p = $this->po($no);
        $this->assertSame('Delivery Failed', $p['status']);
        $this->assertNull($p['sentAt']);
        $this->assertTrue($this->excOpen('PO email failed', $no));
        $this->assertSame(0, MailRecord::where('case_id', self::CID)->where('code', 'PO')->count());
        $this->asRole('mkt')->post(route('po.retry'), ['no' => $no])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('exec')->post(route('po.retry'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');
        $p = $this->po($no);
        $this->assertSame('Sent', $p['status']);
        $this->assertSame($no, $p['no']);                                          // same PO number, no second PO
        $this->assertCount($n, $this->cb()->s['pos']);
        $this->assertSame(2, $p['tries']);
        $this->assertFalse((bool) (Masters::setting('flags', [])['emailFail'] ?? false));
        $this->assertFalse($this->excOpen('PO email failed', $no));
        $this->assertSame(1, MailRecord::where('case_id', self::CID)->where('code', 'PO')->count());
        $this->asRole('exec')->post(route('po.retry'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');   // nothing to retry now
    }

    public function test_schedule_and_run_due_remind_edit_and_moq_waiver(): void
    {
        $map = $this->approvedCase(true);
        [$a, $b] = [$map['SUP-00001'], $map['SUP-00002']];
        $this->asRole('exec')->post(route('po.schedule'), ['nos' => [$a], 'at' => '2026-10-14T10:00'])->assertSessionHas('toast.kind', 'bad');   // no drafts yet
        $this->asRole('exec')->post(route('po.gen'), ['case' => self::CID]);
        $this->asRole('exec')->post(route('po.schedule'), ['nos' => [$a], 'at' => '2026-10-14T10:00'])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('Scheduled', app(PoService::class)->status($this->cb(), $this->po($a)));
        $this->assertFalse($this->po($a)['sent']);
        $this->asRole('exec')->post(route('po.run'))->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('Sent', $this->po($a)['status']);
        $this->asRole('exec')->post(route('po.run'))->assertSessionHas('toast.kind', 'bad');             // nothing scheduled any more

        $this->asRole('exec')->post(route('po.remind'), ['no' => $a])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame(1, MailRecord::where('case_id', self::CID)->where('code', 'G')->count());
        $this->asRole('imp')->post(route('po.remind'), ['no' => $a])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('exec')->post(route('po.remind'), ['no' => $b])->assertSessionHas('toast.kind', 'bad');   // not sent yet

        // edit (operational fields only, unsent PO only)
        $this->asRole('exec')->post(route('po.update'), ['no' => $b, 'billTo' => 'B', 'shipTo' => '', 'tol' => '±3%', 'packing' => 'p', 'docs' => 'd', 'notes' => 'n']);
        $this->assertSame('±3%', $this->po($b)['tol']);
        $this->assertSame('Correction queue', app(PoService::class)->status($this->cb(), $this->po($b)));    // ship-to now missing
        $this->asRole('exec')->post(route('po.update'), ['no' => $a, 'tol' => 'x'])->assertSessionHas('toast.kind', 'bad');   // sent PO is locked
        $this->asRole('mkt')->post(route('po.update'), ['no' => $b, 'tol' => 'x'])->assertSessionHas('toast.kind', 'warn');

        // MOQ waiver needs the supplier's written acceptance (po.correct = supervisor)
        $c = $this->cb();
        $sid = $this->po($b)['sid'];
        $pid = $this->po($b)['lines'][0]['pid'];
        CaseBook::tx(self::CID, function (CaseBook $c) use ($pid, $sid) {
            $x = $c->csOf($pid);
            $x['waiver'] = [$sid => 12000];
            $c->csSet($pid, $x);
        });
        $this->assertFalse(app(PoService::class)->validate($this->cb(), $this->po($b))[4]['ok']);
        $this->asRole('exec')->post(route('po.devok'), ['no' => $b])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('sup')->post(route('po.devok'), ['no' => $b])->assertSessionHas('toast.kind', 'ok');
        $this->assertTrue(app(PoService::class)->validate($this->cb(), $this->po($b))[4]['ok']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'MOQ waiver accepted', 'ref' => $b]);
    }

    // ------------------------------------------------------------------ disputes (AC-16)

    public function test_ac16_dispute_with_material_change_freezes_po_and_requires_an_approved_revision(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->sendAll($map);
        $this->asRole('exec');
        PoService::with($no, fn ($c, &$p) => app(PoService::class)->acknowledge($c, $p, 'dispute', 'Unit price must be 0.85'));
        $this->assertSame('Disputed', $this->po($no)['ack']);
        $this->assertTrue($this->excOpen('PO dispute', $no));
        $this->asRole('exec')->post(route('po.resolve'), ['no' => $no, 'o' => 'chg', 'r' => 'ok'])->assertSessionHas('toast.kind', 'warn');   // SoD: exc.resolve
        $this->assertSame('Disputed', $this->po($no)['ack']);
        $this->asRole('sup')->post(route('po.resolve'), ['no' => $no, 'o' => 'chg', 'r' => ''])->assertSessionHasErrors('r');
        $this->asRole('sup')->post(route('po.resolve'), ['no' => $no, 'o' => 'chg', 'r' => 'Price change accepted'])->assertSessionHas('toast.kind', 'warn');
        $c = $this->cb();
        $this->assertTrue($c->s['pos'][PoService::idx($c, $no)]['frozen']);
        $this->assertSame('returned', $c->s['appr']['status']);
        $this->assertFalse($c->s['csLocked']);
        $this->assertTrue($this->excOpen('Change after approval', $c->s['appr']['id']));
        $this->assertFalse($this->excOpen('PO dispute', $no));
        $this->assertSame('Frozen', app(PoService::class)->status($c, $this->po($no)));
        $this->assertFalse(app(PoService::class)->validate($c, $this->po($no))[0]['ok']);
        $this->asRole('sup')->get(route('po.show', $no))->assertOk()->assertSee('PO frozen', false);

        // re-approval (module D) regenerates the POs: the frozen one comes back as revision R2, same number, unsent
        CaseBook::tx(self::CID, function (CaseBook $c) {
            $c->s['appr']['version']++;
            $c->s['appr']['status'] = 'approved';
            $c->s['appr']['chk'] = $c->s['appr']['approvedChk'] = 'a1b2c3d4';
            app(PoService::class)->generate($c);
        });
        $new = $this->po($no);
        $this->assertSame(2, $new['rev']);
        $this->assertSame("{$no}-R2", PoService::full($new));
        $this->assertFalse($new['sent']);
        $this->assertSame('—', $new['ack']);
        $this->assertSame(1, $new['prev'][0]['rev']);
        $this->assertSame(1, $this->po($map['SUP-00002'])['rev']);               // untouched suppliers keep rev 1
        $this->assertCount(4, array_filter($this->cb()->s['pos'], fn ($p) => str_starts_with($p['no'], 'PO-')));
        $this->asRole('sup')->get(route('po.show', $no))->assertOk()->assertSee("{$no}-R2", false);
    }

    public function test_dispute_rejected_returns_the_po_to_awaiting(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->sendAll($map);
        $this->asRole('exec');
        PoService::with($no, fn ($c, &$p) => app(PoService::class)->acknowledge($c, $p, 'dispute', 'Terms'));
        $this->asRole('fin')->post(route('po.resolve'), ['no' => $no, 'o' => 'stand', 'r' => 'PO terms are as approved'])->assertSessionHas('toast.kind', 'ok');
        $p = $this->po($no);
        $this->assertSame('Awaiting', $p['ack']);
        $this->assertSame('', $p['dispute']);
        $this->assertFalse($p['frozen']);
        $this->assertFalse($this->excOpen('PO dispute', $no));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Dispute rejected', 'ref' => $no]);
        $this->asRole('sup')->post(route('po.resolve'), ['no' => $no, 'o' => 'stand', 'r' => 'again'])->assertSessionHas('toast.kind', 'bad');   // no open dispute
    }

    // ------------------------------------------------------------------ PI (AC-17)

    public function test_pi_can_only_be_recorded_against_an_accepted_po(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->asRole('imp')->post(route('pi.pick'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)))->assertSessionHas('toast.kind', 'bad');
        $this->assertArrayNotHasKey($no, $this->cb()->s['pis']);
        $this->sendAll($map);
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)))->assertSessionHas('toast.kind', 'bad');   // sent, not yet accepted
        $this->accepted($no);
        $this->asRole('imp')->post(route('pi.pick'), ['no' => $no])->assertRedirect(route('pi.show', $no) . '?record=1');
        $this->asRole('mkt')->post(route('pi.record'), $this->piPayload($this->po($no)))->assertSessionHas('toast.kind', 'warn');
        $this->asRole('imp')->get(route('pi.show', $no) . '?record=1')->assertOk()->assertSee('Record supplier PI', false)->assertSee('[BENEFICIARY-ACCOUNT-PLACEHOLDER]', false);
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)))->assertSessionHas('toast.kind', 'ok');
        $pi = $this->cb()->s['pis'][$no];
        $this->assertSame(1, $pi['ver']);
        $this->assertFalse($pi['approved']);
        $this->assertTrue(app(PiService::class)->ok($this->cb(), $this->po($no), $pi));
        $this->assertCount(10, app(PiService::class)->match($this->cb(), $this->po($no), $pi));
        $this->assertDatabaseHas('audit_logs', ['action' => 'PI recorded', 'ref' => $no]);
    }

    public function test_pi_mismatch_blocks_approval_until_a_corrected_revision_is_recorded(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->sendAll($map);
        $this->accepted($no);
        $price = $this->po($no)['lines'][0]['price'] + 0.01;
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no), ['pay' => 'TT 100% advance'], [0 => ['price' => $price]]))->assertSessionHas('toast.kind', 'warn');
        $pi = $this->cb()->s['pis'][$no];
        $bad = array_column(array_filter(app(PiService::class)->match($this->cb(), $this->po($no), $pi), fn ($x) => !$x['ok']), 'n');
        $this->assertEqualsCanonicalizing(['Unit price', 'Totals', 'Payment terms'], $bad);
        $this->assertTrue($this->excOpen('PO vs PI mismatch', $no));
        $this->asRole('imp')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');
        $this->assertFalse($this->cb()->s['pis'][$no]['approved']);
        $this->asRole('imp')->post(route('pi.clarify'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Clarification sent', 'ref' => $no]);
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)))->assertSessionHas('toast.kind', 'ok');   // corrected rev 2
        $this->assertSame(2, $this->cb()->s['pis'][$no]['ver']);
        $this->asRole('mkt')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('imp')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');
        $this->assertTrue($this->cb()->s['pis'][$no]['approved']);
        $this->assertFalse($this->excOpen('PO vs PI mismatch', $no));
        $this->assertSame('PI matched', $this->cb()->lcState($this->po($no)));
    }

    public function test_ac17_beneficiary_bank_change_needs_dual_control_before_the_pi_can_be_approved(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->sendAll($map);
        $this->accepted($no);
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no), ['bc' => 1]))->assertSessionHas('toast.kind', 'warn');
        $this->assertTrue($this->excOpen('Bank details changed', $no));
        $pi = $this->cb()->s['pis'][$no];
        $this->assertTrue($pi['bankChanged']);
        $this->assertSame(['fin' => false, 'sup' => false], $pi['bankV']);
        $this->asRole('imp')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');          // processing restricted
        $this->asRole('exec')->post(route('pi.bank'), ['no' => $no, 'k' => 'fin'])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('sup')->post(route('pi.bank'), ['no' => $no, 'k' => 'fin'])->assertSessionHas('toast.kind', 'warn');  // supervisor is not Finance
        $this->asRole('fin')->post(route('pi.bank'), ['no' => $no, 'k' => 'sup'])->assertSessionHas('toast.kind', 'warn');  // Finance is not the 2nd check
        $this->asRole('fin')->post(route('pi.bank'), ['no' => $no, 'k' => 'fin'])->assertSessionHas('toast.kind', 'ok');
        $this->asRole('imp')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');          // 1/2 only
        $this->assertFalse($this->cb()->s['pis'][$no]['approved']);
        $this->asRole('sup')->post(route('pi.bank'), ['no' => $no, 'k' => 'sup'])->assertSessionHas('toast.kind', 'ok');
        $this->asRole('fin')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');             // fin holds pi.match too
        $this->assertTrue($this->cb()->s['pis'][$no]['approved']);
        $this->assertFalse($this->excOpen('Bank details changed', $no));
        $this->asRole('fin')->get(route('pi.show', $no))->assertOk();
    }

    // ------------------------------------------------------------------ import docs, LC chain, segregation of duties

    public function test_lc_chain_enforces_order_and_segregation_of_duties(): void
    {
        $map = $this->approvedCase(true);
        $no = $map['SUP-00001'];
        $this->sendAll($map);
        $this->accepted($no);
        // nothing works before the PI is approved
        $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => 0, 'checked' => 1])->assertSessionHas('toast.kind', 'bad');
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)));
        $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => 0, 'checked' => 1])->assertSessionHas('toast.kind', 'bad');   // PI recorded, not approved
        $this->asRole('exec')->post(route('pi.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');

        // import docs: perm imp.docs (Import only)
        foreach (['exec', 'sup', 'fin', 'mkt'] as $role) {
            $this->asRole($role)->post(route('pi.doc'), ['no' => $no, 'i' => 0, 'checked' => 1])->assertSessionHas('toast.kind', 'warn');
        }
        $this->asRole('imp')->post(route('pi.docsdone'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');           // checklist incomplete
        foreach (array_keys(PiService::IMPDOCS) as $i) {
            $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => $i, 'checked' => 1])->assertSessionHas('toast.kind', '');
        }
        $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => 3, 'checked' => 0]);                              // untick again
        $this->assertEmpty($this->cb()->s['lc'][$no]['docs'][3]);
        $this->asRole('imp')->post(route('pi.docsdone'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');
        $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => 3, 'checked' => 1]);
        $this->asRole('exec')->post(route('pi.docsdone'), ['no' => $no])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('imp')->post(route('pi.docsdone'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('Checks done', $this->cb()->lcState($this->po($no)));
        $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => 0, 'checked' => 0])->assertSessionHas('toast.kind', 'bad');   // locked once complete

        // LC approval = Finance only; opening = Import only and only after approval
        $this->asRole('imp')->post(route('lc.open'), ['no' => $no, 'b' => '[AD-BANK-NAME]', 'r' => '[LC-REFERENCE]', 'e' => '2027-01-15'])->assertSessionHas('toast.kind', 'bad');   // not approved yet
        foreach (['imp', 'exec', 'sup', 'ed', 'mkt'] as $role) {
            $this->asRole($role)->post(route('lc.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'warn');
        }
        $this->assertNull($this->cb()->s['lc'][$no]['appr']);
        $this->asRole('fin')->post(route('lc.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('LC approved', $this->cb()->lcState($this->po($no)));
        $this->assertDatabaseHas('app_notifications', ['role' => 'imp', 'link' => 'imp.lc']);
        $this->asRole('fin')->post(route('lc.approve'), ['no' => $no])->assertSessionHas('toast.kind', 'bad');              // already approved
        $this->asRole('fin')->post(route('lc.open'), ['no' => $no, 'b' => '[AD-BANK-NAME]', 'r' => '[LC-REFERENCE]', 'e' => '2027-01-15'])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('imp')->post(route('lc.open'), ['no' => $no, 'b' => '', 'r' => '[LC-REFERENCE]', 'e' => '2027-01-15'])->assertSessionHasErrors('b');
        $this->asRole('imp')->get(route('pi.show', $no))->assertOk()->assertSee('Open LC…', false);
        $this->asRole('imp')->post(route('lc.open'), ['no' => $no, 'b' => '[AD-BANK-NAME]', 'r' => '[LC-REFERENCE]', 'e' => '2027-01-15'])->assertSessionHas('toast.kind', 'ok');
        $lc = $this->cb()->s['lc'][$no];
        $po = $this->po($no);
        $this->assertSame('[LC-REFERENCE]', $lc['open']['ref']);
        $this->assertEquals($po['total'], $lc['open']['amt']);                                                              // amount comes from the validated PI
        $this->assertSame(strtotime('2027-01-15T00:00:00Z') * 1000, $lc['open']['exp']);
        $this->assertSame('LC opened', $this->cb()->lcState($po));
        $this->assertDatabaseHas('audit_logs', ['action' => 'LC opened', 'ref' => $no]);
        $this->asRole('imp')->post(route('lc.open'), ['no' => $no, 'b' => 'x', 'r' => 'y', 'e' => '2027-02-15'])->assertSessionHas('toast.kind', 'bad');   // already opened
        $this->asRole('imp')->post(route('pi.record'), $this->piPayload($po))->assertSessionHas('toast.kind', 'bad');        // PI frozen after LC
        $this->asRole('imp')->get(route('imp.lc'))->assertOk()->assertSee('[LC-REFERENCE]', false);
        $this->asRole('ed')->get(route('imp.lc'))->assertOk();
    }

    public function test_case_flow_completes_when_every_po_has_an_open_lc(): void
    {
        $map = $this->approvedCase(true);
        $this->sendAll($map);
        $svc = app(PiService::class);
        $this->asRole('exec');
        foreach ($map as $no) {
            $this->accepted($no);
            $this->asRole('imp')->post(route('pi.record'), $this->piPayload($this->po($no)));
            $this->asRole('imp')->post(route('pi.approve'), ['no' => $no]);
            foreach (array_keys(PiService::IMPDOCS) as $i) {
                $this->asRole('imp')->post(route('pi.doc'), ['no' => $no, 'i' => $i, 'checked' => 1]);
            }
            $this->asRole('imp')->post(route('pi.docsdone'), ['no' => $no]);
            $this->asRole('fin')->post(route('lc.approve'), ['no' => $no]);
            $this->asRole('imp')->post(route('lc.open'), ['no' => $no, 'b' => '[AD-BANK-NAME]', 'r' => '[LC-REFERENCE]', 'e' => '2027-01-15']);
        }
        $this->assertSame('Completed', $this->cb()->caseStatus());
        $seed = $svc->seed($this->cb(), $this->po(reset($map)), ['sys' => 'X']);
        $this->assertSame('[SUPPLIER-PI-REF]', $seed['no']);
        $this->assertSame('X', $seed['sys']);
        $this->assertSame(['fin' => false, 'sup' => false], $seed['bankV']);
    }
}
