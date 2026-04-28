<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AltCallCenterPageTest extends TestCase
{
    public function test_alt_call_center_page_renders_automation_window_controls(): void
    {
        Storage::fake('local');

        $this->get('/alt/call-center')
            ->assertOk()
            ->assertSee('id="automationWindowStartInput"', false)
            ->assertSee('id="automationWindowEndInput"', false)
            ->assertSee('Вікно автозапуску')
            ->assertSee('id="automationWindowTimezone"', false);
    }
}
