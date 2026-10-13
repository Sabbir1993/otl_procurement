<?php

namespace Tests\Feature;

use App\Domain\CaseBook;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiParityPoTest extends TestCase
{
    use RefreshDatabase;

    public function test_disputes_page_has_change_request_dialog(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->asRole('exec')->get(route('po.disp'))->assertOk()->assertSee('id="dlg-appr-chg"', false)->assertSee('Field to change');
    }

    public function test_pi_show_record_button_visible_for_importer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $po = collect(\App\Services\PoService::rows())->first();
        if (!$po) {
            $this->markTestSkipped('no PO in seed');
        }
        $this->asRole('imp')->get(route('pi.show', $po['no']))->assertOk()->assertSee('data-open="pi-form"', false);
    }
}
