<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiParitySourcingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_shortlist_uses_trash_icon_for_remove(): void
    {
        $this->asRole('exec')->get(route('src.supp'))->assertOk()->assertSee('Remove this supplier-material mapping?', false);
    }

    public function test_rfq_show_has_status_filter(): void
    {
        $this->asRole('exec')->get(route('rfq.show', 'IC-2026-10'))->assertOk()->assertSee('drf-status', false)->assertSee('data-st=', false);
    }
}
