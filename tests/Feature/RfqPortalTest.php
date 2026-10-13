<?php

namespace Tests\Feature;

use App\Domain\CaseBook;
use App\Services\RfqService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Module C: RFQ pages/actions + public supplier portal (BRD stages 04-09, AC-03..AC-08)
class RfqPortalTest extends TestCase
{
    use RefreshDatabase;

    private const CASE = 'IC-2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Give one invitation a known link token (seed tokens are hashes only) and return [plain, sid]. */
    private function openLink(bool $open = true): array
    {
        $plain = str_repeat('ab12', 10);
        $sid = '';
        CaseBook::tx(self::CASE, function (CaseBook $c) use ($plain, &$sid, $open) {
            foreach ($c->s['inv'] as &$iv) {
                $iv['tok'] = RfqService::hashToken($plain);
                $iv['revoked'] = false;
                $iv['submitted'] = false;
                $iv['deadline'] = ($open ? time() + 5 * 86400 : time() - 86400) * 1000;
                $sid = $iv['sid'];
                break;
            }
            unset($iv);
        });
        return [$plain, $sid];
    }

    public function test_rfq_pages_render_for_every_role_and_show_create_buttons_by_permission(): void
    {
        foreach (['exec', 'sup', 'mkt', 'qa', 'fin', 'ed', 'imp', 'admin', 'audit'] as $role) {
            foreach (['/sourcing/suppliers', '/sourcing/rfqs', '/sourcing/tracking', '/sourcing/quotes', '/rfq/' . self::CASE] as $u) {
                $this->asRole($role)->get($u)->assertOk();
            }
        }
        $this->asRole('exec')->get('/sourcing/rfqs')->assertSee('New RFQ');
        $this->asRole('exec')->get('/sourcing/tracking')->assertSee('Link &amp; Deadline Admin', false)->assertDontSee('&amp;amp;', false);
        $this->asRole('audit')->get('/sourcing/rfqs')->assertDontSee('New RFQ');
    }

    public function test_create_menu_items_open_their_dialogs(): void
    {
        $this->asRole('exec')->get('/sourcing/rfqs?new=1')->assertSee('data-open-on-new', false);
        $this->asRole('exec')->get('/sourcing/suppliers')->assertOk();
        $this->asRole('exec')->get('/suppliers?new=1')->assertSee('data-open-on-new', false);
    }

    public function test_flow_bar_uses_styled_steps(): void
    {
        $this->asRole('exec')->get('/sourcing/rfqs')->assertSee('class="steps flow"', false)->assertSee('data-href=', false);
    }

    public function test_public_portal_opens_by_token_and_rejects_unknown_tokens(): void
    {
        [$plain] = $this->openLink();
        $this->get('/s/' . $plain)->assertOk()->assertSee('Request for Quotation');
        $this->get('/s/' . str_repeat('0', 40))->assertNotFound();
    }

    public function test_revoked_link_blocks_the_supplier_and_is_audited(): void
    {
        [$plain, $sid] = $this->openLink();
        $this->asRole('sup')->post('/rfq/' . self::CASE . '/revoke', ['sid' => $sid])->assertSessionHas('toast.kind', 'ok');
        $this->get('/s/' . $plain)->assertSee('This link has been revoked');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Link revoked', 'case_id' => self::CASE]);
    }

    public function test_segregation_of_duties_on_rfq_actions(): void
    {
        [, $sid] = $this->openLink();
        $this->asRole('mkt')->from('/sourcing/tracking')->post('/rfq/' . self::CASE . '/revoke', ['sid' => $sid])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('mkt')->from('/sourcing/tracking')->post('/rfq/' . self::CASE . '/close')->assertSessionHas('toast.kind', 'warn');
    }

    public function test_extension_needs_a_reason_and_future_date(): void
    {
        [, $sid] = $this->openLink();
        $this->asRole('sup')->from('/sourcing/tracking')->post('/rfq/' . self::CASE . '/extend', ['sid' => $sid, 'd' => '2030-01-02', 'r' => ''])->assertSessionHasErrors('r');
        $this->asRole('sup')->post('/rfq/' . self::CASE . '/extend', ['sid' => $sid, 'd' => '2030-01-02', 'r' => 'Supplier holiday'])->assertSessionHas('toast.kind', 'ok');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Deadline extended', 'case_id' => self::CASE]);
    }

    public function test_submission_after_cutoff_is_refused_server_side(): void
    {
        [$plain] = $this->openLink(false);
        $this->post('/s/' . $plain . '/submit')->assertRedirect();
        $this->assertFalse(CaseBook::find(self::CASE)->s['inv'][0]['submitted'] ?? false);
    }

    public function test_typed_values_survive_a_failed_save_and_are_not_reported_missing(): void
    {
        [$plain] = $this->openLink();
        $bad = \Illuminate\Http\UploadedFile::fake()->create('x.exe', 10);
        // bad attachment + typed text in one request: the text must still be saved, the file error reported
        $this->post('/s/' . $plain . '/save', ['tab' => 'commercial', 'h' => ['payDetail' => 'TT 30 days after BL', 'place' => 'Shanghai', 'tdsFile' => $bad]])->assertRedirect();
        $this->get('/s/' . $plain . '?tab=commercial')->assertSee('TT 30 days after BL')->assertSee('Shanghai');
        // values given on a tab must no longer be listed as required on the review tab
        $this->post('/s/' . $plain . '/save', ['tab' => 'commercial', 'goto' => 'review', 'h' => ['claims' => 'Standard warranty policy']])->assertRedirect();
        $r = $this->get('/s/' . $plain . '?tab=review');
        $r->assertDontSee('Detailed payment terms is required')->assertDontSee('Named place / port is required')->assertDontSee('Claims, replacement &amp; warranty policy is required', false);
    }

    public function test_per_material_values_are_saved_for_every_material(): void
    {
        [$plain] = $this->openLink();
        $l = [];
        foreach (CaseBook::find(self::CASE)->s['inv'][0]['pids'] as $pid) {
            $l[$pid] = ['listPrice' => '2.5', 'moq' => '1000', 'prodDays' => '20', 'shipDays' => '5'];
        }
        $this->post('/s/' . $plain . '/save', ['tab' => 'commercial', 'goto' => 'review', 'l' => $l])->assertRedirect();
        $html = $this->get('/s/' . $plain . '?tab=review')->getContent();
        $this->assertStringNotContainsString('Unit price (USD) is required', $html);
        $this->assertStringNotContainsString('Minimum order quantity (MOQ) is required', $html);
    }

    public function test_rfq_can_be_closed_early_with_a_single_quotation(): void
    {
        // 1 submitted, the others still open
        CaseBook::tx(self::CASE, function (CaseBook $c) {
            foreach ($c->s['inv'] as $k => &$iv) {
                $iv['revoked'] = false;
                $iv['deadline'] = (time() + 5 * 86400) * 1000;
                $iv['submitted'] = $k === 0;
            }
            unset($iv);
        });
        $this->assertFalse(CaseBook::find(self::CASE)->rfqClosed());
        // the comparison page offers the button to the supervisor only
        $this->asRole('sup')->get('/sourcing/comparative-statement')->assertSee('Close RFQ now (1 of ', false);
        $this->asRole('exec')->get('/sourcing/comparative-statement')->assertDontSee('Close RFQ now');
        $this->asRole('sup')->post('/rfq/' . self::CASE . '/close')->assertSessionHas('toast.kind', 'ok');
        $c = CaseBook::find(self::CASE);
        $this->assertTrue($c->rfqClosed());
        $this->assertTrue((bool) $c->s['inv'][0]['submitted']);
        $this->assertNotEmpty(array_filter($c->s['inv'], fn ($i) => !empty($i['revoked'])));
    }

    public function test_outgoing_mail_links_are_built_from_app_url(): void
    {
        config(['app.url' => 'https://pms.example.test/app']);
        $this->assertSame('https://pms.example.test/app/award/IC-2026-10', \App\Support\Link::route('award.show', 'IC-2026-10'));
        $c = CaseBook::find('IC-2026-09');
        $po = $c->s['pos'][0];
        $vars = app(\App\Services\PoService::class)->vars($c, $po);
        $this->assertMatchesRegularExpression('#^https://pms\.example\.test/app/s/[a-f0-9]{40}/po$#', $vars['PO_ACK_LINK']);
        $this->assertStringNotContainsString('oil-domain', $vars['PO_ACK_LINK']);
        $m = \App\Domain\Workflow::mail('IC-2026-10', 'H', 'x', ['REQUEST_ID' => 'AWD-1', 'STAGE' => 's', 'SUPPLIER_NAME' => 'n', 'AMOUNT' => '1', 'ACTION_LABEL' => 'a', 'APPROVER_NAME' => 'm']);
        $this->assertStringContainsString('https://pms.example.test/app/award/IC-2026-10', $m->body);
    }
}
