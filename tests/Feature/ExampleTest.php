<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** La raíz muestra la landing pública de GasAI. */
    public function test_the_root_shows_the_landing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('GasAI')
            ->assertSee('WhatsApp');
    }
}
