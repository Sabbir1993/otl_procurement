<?php

namespace Tests\Feature;

use App\Domain\AdminCatalog;
use App\Domain\CaseBook;
use App\Domain\Masters;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiParityAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Masters::flush();
    }

    public function test_demo_page_shows_enabled_stage_loader(): void
    {
        $this->asRole('admin')->get(route('ad.demo'))->assertOk()->assertSee('Load stage')->assertSee(route('ad.demo.stage'), false);
    }

    public function test_every_stage_loads_on_every_case(): void
    {
        $this->asRole('admin');
        foreach (['IC-2026-10', 'IC-2026-11', 'IC-2026-09'] as $cid) {
            foreach (AdminCatalog::STAGES as $st) {
                $this->post(route('ad.demo.stage'), ['c' => $cid, 's' => $st[0]])->assertRedirect(route('case.show', $cid))->assertSessionHas('toast.kind', 'ok');
            }
        }
        $c = CaseBook::findOrFail('IC-2026-10');
        $this->assertNotEmpty($c->s['pos']);
        $this->assertNotEmpty(array_filter($c->s['lc'], fn ($l) => !empty($l['open'])));
        $this->assertSame('approved', $c->s['appr']['status']);
    }

    public function test_quotes_stage_matches_seed_state(): void
    {
        $this->asRole('admin')->post(route('ad.demo.stage'), ['c' => 'IC-2026-11', 's' => 'quotes']);
        $c = CaseBook::findOrFail('IC-2026-11');
        $this->assertTrue($c->s['booking']['confirmed']);
        $this->assertCount(3, array_filter($c->s['inv'], fn ($i) => $i['submitted']));
        $this->get(route('src.cs'))->assertOk();
    }

    public function test_stage_load_is_admin_only(): void
    {
        $this->asRole('exec')->post(route('ad.demo.stage'), ['c' => 'IC-2026-10', 's' => 'req'])->assertSessionHas('toast.kind', 'warn');
        $this->asRole('admin')->post(route('ad.demo.stage'), ['c' => 'IC-2026-10', 's' => 'nope'])->assertSessionHas('toast.kind', 'bad');
    }

    public function test_error_and_login_pages(): void
    {
        $this->get('/no-such-page')->assertNotFound();
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }
}
