<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiParityCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_topbar_has_global_search_dropdown(): void
    {
        $this->asRole('exec')->get(route('home'))->assertOk()->assertSee('id="gsr"', false)->assertSee('data-gs=', false);
    }

    public function test_global_search_groups_results(): void
    {
        $r = $this->asRole('admin')->getJson(route('gs.search', ['q' => 'IC-2026']))->assertOk();
        $this->assertSame('Purchase cases', $r->json('groups.0.title'));
        $this->assertNotEmpty($r->json('groups.0.items.0.u'));
        $this->assertSame([], $this->asRole('admin')->getJson(route('gs.search', ['q' => '']))->json('groups'));
        $this->assertSame([], $this->asRole('admin')->getJson(route('gs.search', ['q' => 'zzz-nothing-xyz']))->json('groups'));
    }

    public function test_search_hides_groups_the_role_cannot_open(): void
    {
        $titles = collect($this->asRole('mkt')->getJson(route('gs.search', ['q' => 'a']))->json('groups'))->pluck('title');
        $this->assertFalse($titles->contains('Suppliers'));
        $this->assertFalse($titles->contains('Materials'));
    }

    public function test_read_only_banner_for_audit_role_only(): void
    {
        $this->asRole('audit')->get(route('cases'))->assertSee('Read-only role');
        $this->asRole('exec')->get(route('cases'))->assertDontSee('Read-only role');
    }
}
