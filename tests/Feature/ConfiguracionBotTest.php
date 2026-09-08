<?php

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracionBot;
use App\Models\BotConfig;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracionBotTest extends TestCase
{
    use RefreshDatabase;

    private function actingInTenant(Tenant $tenant): void
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);
    }

    public function test_la_pagina_carga_y_guarda_la_config_del_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->actingInTenant($tenant);

        Livewire::test(ConfiguracionBot::class)
            ->fillForm([
                'agent_name' => 'Aguita',
                'tone' => 'amable',
                'temperature' => 0.3,
                'welcome_message' => 'Hola, soy Aguita. ¿Qué necesitas hoy?',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $config = BotConfig::withoutGlobalScopes()->firstWhere('tenant_id', $tenant->id);

        $this->assertNotNull($config);
        $this->assertSame('Aguita', $config->agent_name);
        $this->assertSame($tenant->id, $config->tenant_id);
    }

    public function test_guardar_no_crea_un_segundo_registro_para_el_mismo_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->actingInTenant($tenant);

        Livewire::test(ConfiguracionBot::class)
            ->fillForm(['agent_name' => 'Uno', 'tone' => 'amable', 'temperature' => 0.3])
            ->call('save');

        Livewire::test(ConfiguracionBot::class)
            ->fillForm(['agent_name' => 'Dos', 'tone' => 'formal', 'temperature' => 0.5])
            ->call('save');

        $this->assertSame(1, BotConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame('Dos', BotConfig::withoutGlobalScopes()->firstWhere('tenant_id', $tenant->id)->agent_name);
    }
}
