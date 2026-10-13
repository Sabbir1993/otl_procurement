<?php

namespace Tests\Feature;

use App\Domain\CaseBook;
use App\Domain\Clock;
use App\Services\ApprovalService;
use App\Services\CsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Module D: comparative statement, samples & QA, negotiation, recommendation, approvals (AC-10..AC-13). */
class CsAwardsTest extends TestCase
{
    use RefreshDatabase;

    private const CID = 'IC-2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Clock::freeze(1791878400000);                // the prototype's "now": quotes valid, RFQ deadline passed
    }

    protected function tearDown(): void
    {
        Clock::freeze(null);
        parent::tearDown();
    }

    private function c(): CaseBook
    {
        return CaseBook::findOrFail(self::CID);
    }

    /** Award every material by lowest landed cost (exclude those without an eligible offer) + write justifications. */
    private function decideAll(): void
    {
        $this->asRole('exec');
        $cs = app(CsService::class);
        $c = $this->c();
        foreach ($c->s['booking']['lines'] as $l) {
            $b = $c->pick($l['pid'], 'cost');
            if ($b) {
                $cs->allocate(self::CID, ['pid' => $l['pid'], 'sid' => $b['sid'], 'mode' => 'full']);
            } else {
                $cs->exclude(self::CID, $l['pid'], 'No valid quotation — re-tender');
            }
        }
        foreach ($this->c()->s['booking']['lines'] as $l) {
            if ($this->c()->csStat($l['pid']) !== 'Excluded' && $this->c()->needsJust($l['pid'])) {
                $cs->justify(self::CID, $l['pid'], 'Test rationale');
            }
        }
    }

    private function submit(): void
    {
        $this->asRole('exec')->post(route('aw.submit', self::CID), ['s' => 'Lowest eligible landed cost.'])->assertSessionHas('toast.kind', 'ok');
    }

    private function decide(string $role, string $d, ?string $r = null)
    {
        return $this->asRole($role)->post(route('appr.decide', self::CID), ['d' => $d, 'r' => $r]);
    }

    public function test_pages_render_for_every_role_before_and_after_submission(): void
    {
        $this->decideAll();
        foreach ([false, true] as $submitted) {
            if ($submitted) {
                $this->submit();
            }
            foreach (['exec', 'sup', 'mkt', 'qa', 'fin', 'ed', 'audit', 'admin'] as $role) {
                $this->asRole($role);
                foreach (['src.cs', 'src.samples', 'aw.neg', 'aw.rec', 'aw.appr'] as $r) {
                    $this->get(route($r))->assertOk();
                }
                foreach (['product', 'tech', 'bulk'] as $tab) {
                    $this->get(route('src.cs', ['tab' => $tab, 'pid' => 'P-1002']))->assertOk();
                }
                $this->get(route('src.cs', ['preview' => 1, 'strat' => 'index']))->assertOk();
                $this->get(route('award.show', self::CID))->assertOk();
            }
        }
        $this->get(route('award.show', 'IC-2026-09'))->assertOk()->assertSee('AWD-2026-00012');
        $this->get(route('award.show', 'IC-2026-11'))->assertOk()->assertSee('No award package');
    }

    public function test_full_chain_allocate_submit_mkt_fin_ed_generates_pos(): void
    {
        $this->decideAll();
        $c = $this->c();
        $this->assertSame(0, $c->csCounts()['pend']);
        $this->assertNotEmpty($c->lineAwards());

        $this->submit();
        $A = $this->c()->s['appr'];
        $this->assertSame('mkt', $A['status']);
        $this->assertSame(1, $A['version']);
        $this->assertMatchesRegularExpression('/^AWD-2026-\d{5}$/', $A['id']);
        $this->assertSame($this->c()->awardChk(), $A['chk']);
        $this->assertNotEmpty($A['snap']);
        $this->assertTrue($this->c()->s['csLocked']);
        $this->assertDatabaseHas('mails', ['code' => 'H', 'case_id' => self::CID, 'to' => 'Marketing approver']);
        $this->assertDatabaseHas('app_notifications', ['role' => 'mkt']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Award submitted', 'ref' => $A['id']]);

        // no skipped step / wrong role: ED, Finance and Procurement cannot act at Marketing
        foreach (['ed', 'fin', 'exec'] as $role) {
            $this->decide($role, 'ok')->assertSessionHas('toast.kind', 'warn');
        }
        $this->assertSame('mkt', $this->c()->s['appr']['status']);

        $this->decide('mkt', 'ok', 'Fits requirement')->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('fin', $this->c()->s['appr']['status']);
        $this->decide('mkt', 'ok')->assertSessionHas('toast.kind', 'warn');         // Marketing cannot approve at Finance

        // Finance remark while at Finance stage (fin.remark)
        $pid = $this->c()->s['booking']['lines'][0]['pid'];
        $this->asRole('fin')->post(route('cs.fin', self::CID), ['pid' => $pid, 'fin' => 'FX rate to be confirmed'])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('FX rate to be confirmed', $this->c()->csOf($pid)['fin']);
        $this->asRole('exec')->post(route('cs.fin', self::CID), ['pid' => $pid, 'fin' => 'x'])->assertSessionHas('toast.kind', 'warn');

        $this->decide('fin', 'ok', 'Budget ok')->assertSessionHas('toast.kind', 'ok');
        $this->assertSame('ed', $this->c()->s['appr']['status']);
        $this->assertCount(0, $this->c()->s['pos']);

        $this->decide('ed', 'ok')->assertSessionHas('toast.kind', 'ok');             // AC-11
        $c = $this->c();
        $A = $c->s['appr'];
        $this->assertSame('approved', $A['status']);
        $this->assertSame($A['chk'], $A['approvedChk']);
        $this->assertTrue($c->s['csLocked']);
        $this->assertNotEmpty($c->s['pos']);
        $this->assertSame(count(array_unique(array_column($c->lineAwards(), 'sid'))), count($c->s['pos']));
        $this->assertSame($A['chk'], $c->s['pos'][0]['chk']);
        $this->assertCount(4, $A['hist']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'POs generated']);
        $this->assertSame('Purchase order', $c->caseStatus());

        // approved + locked: CS can no longer be edited
        $this->asRole('exec')->post(route('cs.clear', self::CID), ['pid' => $pid, 'sid' => array_key_first($c->csOf($pid)['alloc'] ?: ['x' => 1])])->assertSessionHas('toast.kind', 'bad');
    }

    public function test_remarks_mandatory_and_return_restarts_as_new_version(): void   // AC-12
    {
        $this->decideAll();
        $this->submit();
        $this->decide('mkt', 'ok');
        $this->decide('fin', 'ret', '')->assertSessionHas('toast.kind', 'bad');
        $this->assertSame('fin', $this->c()->s['appr']['status']);
        $this->decide('fin', 'rej', '  ')->assertSessionHas('toast.kind', 'bad');
        $this->decide('fin', 'ret', 'Clarify freight')->assertSessionHas('toast.kind', 'warn');
        $c = $this->c();
        $this->assertSame('returned', $c->s['appr']['status']);
        $this->assertFalse($c->s['csLocked']);
        $this->assertDatabaseHas('app_notifications', ['role' => 'exec', 'sev' => 'bad']);
        $this->decide('ed', 'ok')->assertSessionHas('toast.kind', 'bad');             // nothing waiting

        $id = $c->s['appr']['id'];
        $this->submit();
        $A = $this->c()->s['appr'];
        $this->assertSame(2, $A['version']);
        $this->assertSame($id, $A['id']);
        $this->assertSame('mkt', $A['status']);
        $this->assertNull($A['mkt']);
        $this->assertNull($A['fin']);

        // rejected at Finance as well
        $this->decide('mkt', 'ok');
        $this->decide('fin', 'rej', 'Over budget');
        $this->assertSame('rejected', $this->c()->s['appr']['status']);
        // Marketing may only acknowledge or return
        $this->submit();
        $this->decide('mkt', 'rej', 'no')->assertSessionHas('toast.kind', 'bad');
    }

    public function test_pack_changed_after_submission_cannot_be_approved(): void
    {
        $this->decideAll();
        $this->submit();
        CaseBook::tx(self::CID, function (CaseBook $c) {                  // another module revises a quotation after submission
            $x = $c->lineAwards()[0];
            $c->s['quotes'][$x['sid']][$x['pid']]['price'] += 0.05;
            $c->s['quotes'][$x['sid']][$x['pid']]['listPrice'] += 0.05;
        });
        $this->decide('mkt', 'ok')->assertSessionHas('toast.kind', 'bad');
        $this->assertSame('mkt', $this->c()->s['appr']['status']);
        $this->decide('mkt', 'ret', 'Pack changed')->assertSessionHas('toast.kind', 'warn');   // returning is still possible
    }

    public function test_self_approval_is_blocked(): void
    {
        $this->decideAll();
        $this->submit();                                               // by Imran K. (exec)
        CaseBook::tx(self::CID, function (CaseBook $c) {                // simulate the submitter being the Marketing reviewer
            $c->s['appr']['rec']['by'] = \App\Models\User::where('role', 'mkt')->value('name');
        });
        $this->decide('mkt', 'ok')->assertSessionHas('toast.kind', 'bad');
        $this->assertSame('mkt', $this->c()->s['appr']['status']);
    }

    public function test_change_after_approval_freezes_pos_and_raises_exception(): void   // AC-13
    {
        $this->decideAll();
        $this->submit();
        $this->decide('mkt', 'ok');
        $this->decide('fin', 'ok');
        $this->decide('ed', 'ok');
        $A = $this->c()->s['appr'];
        $this->assertNotEmpty($this->c()->s['pos']);

        $this->asRole('mkt')->post(route('appr.change', self::CID), ['f' => 'Unit price', 'r' => 'x'])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('ed')->post(route('appr.change', self::CID), ['f' => 'Unit price', 'r' => ''])->assertSessionHasErrors('r');
        $this->asRole('ed')->post(route('appr.change', self::CID), ['f' => 'Unit price', 'r' => 'Supplier revised the rate'])->assertSessionHas('toast.kind', 'warn');
        $c = $this->c();
        $this->assertSame('returned', $c->s['appr']['status']);
        $this->assertFalse($c->s['csLocked']);
        foreach ($c->s['pos'] as $po) {
            $this->assertTrue($po['frozen']);
        }
        $this->assertDatabaseHas('exceptions', ['type' => 'Change after approval', 'ref' => $A['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Change request', 'ref' => $A['id']]);
        // after the return the CS can be revised and resubmitted as v2
        $this->submit();
        $this->assertSame(2, $this->c()->s['appr']['version']);
    }

    public function test_submit_rules(): void
    {
        $this->asRole('exec');
        $this->post(route('aw.submit', self::CID), ['s' => 'x'])->assertSessionHas('toast.kind', 'bad');         // products still pending
        $this->asRole('mkt')->post(route('aw.submit', self::CID), ['s' => 'x'])->assertSessionHas('toast.kind', 'warn');
        $this->decideAll();
        // justification required where needsJust() and none recorded
        $pid = null;
        foreach ($this->c()->s['booking']['lines'] as $l) {
            if ($this->c()->csStat($l['pid']) !== 'Excluded' && $this->c()->needsJust($l['pid'])) {
                $pid = $l['pid'];
            }
        }
        if ($pid) {
            $this->asRole('exec')->post(route('cs.justify', self::CID), ['pid' => $pid, 'just' => ''])->assertSessionHas('toast.kind', 'ok');
            $this->asRole('exec')->post(route('aw.submit', self::CID), ['s' => 'x'])->assertSessionHas('toast.kind', 'bad');
            $this->assertSame('none', $this->c()->s['appr']['status']);
        }
        // RFQ still open blocks submission
        Clock::freeze(1791622800000 - 86400000);
        $this->asRole('exec')->post(route('aw.submit', self::CID), ['s' => 'x'])->assertSessionHas('toast.kind', 'bad');
    }

    public function test_sample_fail_blocks_award_and_raises_exception(): void   // AC-10
    {
        $c = $this->c();
        $pid = 'P-1001';
        $b = $c->pick($pid, 'cost');
        $this->assertNotNull($b);
        $this->asRole('exec')->post(route('cs.allocate', self::CID), ['pid' => $pid, 'sid' => $b['sid'], 'mode' => 'full'])->assertSessionHas('toast.kind', 'ok');
        $this->assertSame($c->reqQty($pid), $this->c()->allocSum($pid));

        $this->asRole('exec')->post(route('smp.save'), ['cid' => self::CID, 'sid' => $b['sid'], 'pid' => $pid, 'res' => 'Fail'])->assertSessionHas('toast.kind', 'warn'); // SoD: only QA
        $this->assertSame('Pass', $this->c()->s['quotes'][$b['sid']][$pid]['sample']);

        $this->asRole('qa')->post(route('smp.save'), ['cid' => self::CID, 'sid' => $b['sid'], 'pid' => $pid, 'res' => 'Fail', 'rep' => 'QA-REPORT-1'])->assertSessionHas('toast.kind', 'warn');
        $c = $this->c();
        $this->assertSame('Fail', $c->s['quotes'][$b['sid']][$pid]['sample']);
        $this->assertSame('QA-REPORT-1', $c->s['quotes'][$b['sid']][$pid]['report']);
        $this->assertArrayNotHasKey($b['sid'], $c->csOf($pid)['alloc']);
        $this->assertDatabaseHas('exceptions', ['type' => 'Sample rejected', 'ref' => $pid, 'case_id' => self::CID]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Sample result set', 'ref' => $pid]);

        $this->asRole('exec')->post(route('cs.allocate', self::CID), ['pid' => $pid, 'sid' => $b['sid'], 'mode' => 'full'])->assertSessionHas('toast.kind', 'bad');
        $this->assertNotSame($b['sid'], $this->c()->pick($pid, 'cost')['sid'] ?? null);

        $this->asRole('qa')->post(route('smp.save'), ['cid' => self::CID, 'sid' => $b['sid'], 'pid' => $pid, 'res' => 'Maybe'])->assertSessionHasErrors('res');
    }

    public function test_moq_waiver_needs_supervisor_and_reason(): void
    {
        $c = $this->c();
        $pid = null;
        foreach ($c->s['booking']['lines'] as $l) {
            foreach ($c->bids($l['pid']) as $b) {
                if ($c->elig($b, $l['pid'])['ok'] && $b['q']['moq'] > 1 && $b['q']['moq'] <= $l['qty']) {
                    [$pid, $sid, $moq] = [$l['pid'], $b['sid'], $b['q']['moq']];
                    break 2;
                }
            }
        }
        $this->assertNotNull($pid);
        $partial = ['pid' => $pid, 'sid' => $sid, 'mode' => 'partial', 'qty' => $moq - 1];
        $this->asRole('exec')->post(route('cs.allocate', self::CID), $partial)->assertSessionHas('toast.kind', 'bad');
        $this->assertSame(0, $this->c()->allocSum($pid));
        $this->asRole('sup')->post(route('cs.allocate', self::CID), $partial)->assertSessionHas('toast.kind', 'bad');       // reason missing
        $this->asRole('sup')->post(route('cs.allocate', self::CID), $partial + ['waiver' => 'Supplier accepted in writing'])->assertSessionHas('toast.kind', 'ok');
        $x = $this->c()->csOf($pid);
        $this->assertSame('Supplier accepted in writing', $x['waiver'][$sid]);
        $this->assertStringContainsString('MOQ waiver', $this->c()->needsJust($pid));
        $this->assertDatabaseHas('exceptions', ['type' => 'MOQ waiver', 'ref' => $pid]);
        // over-allocation is rejected
        $this->asRole('sup')->post(route('cs.allocate', self::CID), array_merge($partial, ['waiver' => 'x', 'qty' => $this->c()->reqQty($pid) + 1]))->assertSessionHas('toast.kind', 'bad');
        // clearing the allocation removes the waiver
        $this->asRole('sup')->post(route('cs.clear', self::CID), ['pid' => $pid, 'sid' => $sid])->assertSessionHas('toast.kind', '');
        $this->assertSame([], $this->c()->csOf($pid)['waiver']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CS allocation cleared', 'ref' => $pid]);
    }

    public function test_negotiation_reprices_and_is_logged(): void
    {
        $c = $this->c();
        $pid = 'P-1001';
        $b = $c->bids($pid)[0];
        $old = $b['q']['price'];
        $new = round($old * 0.95, 3);
        $this->asRole('mkt')->post(route('cs.negotiate', self::CID), ['pid' => $pid, 'sid' => $b['sid'], 'np' => $new, 'r' => 'x'])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('exec')->post(route('cs.negotiate', self::CID), ['pid' => $pid, 'sid' => $b['sid'], 'np' => $old, 'r' => 'same'])->assertSessionHas('toast.kind', 'bad');
        $this->asRole('exec')->post(route('cs.negotiate', self::CID), ['pid' => $pid, 'sid' => $b['sid'], 'np' => $new, 'r' => 'Volume commitment'])->assertSessionHas('toast.kind', 'ok');
        $c = $this->c();
        $q = $c->s['quotes'][$b['sid']][$pid];
        $this->assertEquals($new, $q['price']);
        $this->assertEquals($old, $q['orig']);
        $this->assertEquals($b['q']['rev'] + 1, $q['rev']);
        $this->assertEqualsWithDelta($new, $q['listPrice'], 0.001);
        $n = $c->csOf($pid)['neg'];
        $this->assertCount(1, $n);
        $this->assertSame(['from' => $old, 'to' => $new, 'reason' => 'Volume commitment'], ['from' => $n[0]['from'], 'to' => $n[0]['to'], 'reason' => $n[0]['reason']]);
        $this->assertMatchesRegularExpression('/^NEG-2026-\d{5}$/', $n[0]['id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Negotiation round', 'ref' => $pid]);
        $this->asRole('exec')->get(route('aw.neg'))->assertOk()->assertSee('Volume commitment');
    }

    public function test_exclude_include_and_bulk_apply_and_csv(): void
    {
        $this->asRole('exec');
        $this->post(route('cs.exclude', self::CID), ['pid' => 'P-1007', 'r' => 'No valid quotation'])->assertSessionHas('toast.kind', 'warn');
        $this->assertSame('Excluded', $this->c()->csStat('P-1007'));
        $this->post(route('cs.include', self::CID), ['pid' => 'P-1007'])->assertSessionHas('toast.kind', '');
        $this->assertSame('Pending', $this->c()->csStat('P-1007'));
        $this->post(route('cs.exclude', self::CID), ['pid' => 'P-1007', 'r' => ''])->assertSessionHasErrors('r');

        $expect = count($this->decideCount('cost'));
        $this->assertGreaterThan(0, $expect);
        $this->asRole('mkt')->post(route('cs.bulk', self::CID), ['strat' => 'cost'])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('exec')->post(route('cs.bulk', self::CID), ['strat' => 'cost'])->assertRedirect(route('src.cs'));
        $c = $this->c();
        $this->assertSame($expect, count(array_filter($c->s['booking']['lines'], fn ($l) => $c->csStat($l['pid']) === 'Complete')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Bulk supplier select', 'ref' => self::CID]);
        $this->asRole('exec')->post(route('cs.bulk', self::CID), ['strat' => 'nope'])->assertSessionHas('toast.kind', 'bad');

        $csv = $this->get(route('cs.csv', self::CID));
        $csv->assertOk();
        $this->assertStringContainsString('product,supplier,qty,unit_price,material_amount,est_landed', str_replace('"', '', $csv->getContent()));
    }

    private function decideCount(string $k): array
    {
        $c = $this->c();
        return array_filter($c->s['booking']['lines'], fn ($l) => $c->bids($l['pid']) && $c->pick($l['pid'], $k));
    }

    public function test_service_contracts_for_the_demo_loader(): void
    {
        // the cross-module signatures, called directly without a signed-in role
        $cs = app(CsService::class);
        $ap = app(ApprovalService::class);
        CaseBook::tx(self::CID, function (CaseBook $c) use ($cs, $ap) {
            foreach ($c->s['booking']['lines'] as $l) {
                $b = $c->pick($l['pid'], 'cost');
                if ($b) {
                    $cs->setFull($c, $l['pid'], $b['sid'], 'System');
                } else {
                    $x = $c->csOf($l['pid']);
                    $x['excl'] = true;
                    $c->csSet($l['pid'], $x);
                }
            }
            $w = array_key_first($c->csOf('P-1001')['alloc']);
            $cs->negotiate($c, 'P-1001', $w, round($c->s['quotes'][$w]['P-1001']['price'] * .98, 3), 'Volume', 'Imran K.');
            $ap->submitAward($c, 'Imran K.', 'summary');
            $ap->decide($c, 'ok', 'a', 'Nasrin A.');
            $ap->decide($c, 'ok', 'b', 'Kamal U.');
            $ap->decide($c, 'ok', 'c', 'ED (delegate)');
        });
        $A = $this->c()->s['appr'];
        $this->assertSame('approved', $A['status']);
        $this->assertNotEmpty($this->c()->s['pos']);
        $this->assertSame('Nasrin A.', $A['mkt']['by']);
    }
}
