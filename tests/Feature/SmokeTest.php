<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/cases')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    /** Every page route renders for the administrator (who can see every menu) without a server error. */
    public function test_every_page_renders(): void
    {
        $this->asRole('admin');
        $skip = ['login', 'logout', 'demo.login', 'demo.switch', 'case.select', 'sanctum.csrf-cookie'];
        $sample = ['case.show' => 'IC-2026-10', 'rfq.show' => 'IC-2026-10', 'quote.show' => 'IC-2026-10|SUP-00001', 'po.show' => 'PO-2026-00008', 'pi.show' => 'PO-2026-00008', 'sup.show' => 'SUP-00001', 'award.show' => 'IC-2026-09', 'cs.csv' => 'IC-2026-10'];
        $n = 0;
        foreach (Route::getRoutes() as $r) {
            if (!in_array('GET', $r->methods()) || !$r->getName() || in_array($r->getName(), $skip) || str_starts_with($r->getName(), 'ignition') || str_starts_with($r->getName(), 'sanctum')) {
                continue;
            }
            if (str_contains($r->uri(), '{token}')) {
                continue; // public supplier portal: covered by its own tests (needs a live link token)
            }
            $url = isset($sample[$r->getName()]) ? route($r->getName(), $sample[$r->getName()]) : route($r->getName());
            $this->get($url)->assertOk("{$r->getName()} -> {$url}");
            $n++;
        }
        $this->assertGreaterThan(35, $n);
    }

    public function test_exec_can_create_a_case_and_audit_is_written(): void
    {
        $this->asRole('exec')->post('/cases', ['name' => 'Dec test', 'month' => 'Dec 2026'])->assertRedirect(route('rq.board'));
        $this->assertDatabaseHas('purchase_cases', ['id' => 'IC-2026-12', 'name' => 'Dec test']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Case created', 'case_id' => 'IC-2026-12']);
    }

    public function test_segregation_of_duties_blocks_other_roles(): void
    {
        $this->asRole('mkt')->from('/cases')->post('/cases', ['name' => 'X', 'month' => 'Dec 2026'])->assertRedirect('/cases')->assertSessionHas('toast.kind', 'warn');
        $this->assertDatabaseMissing('purchase_cases', ['name' => 'X']);
    }
}
