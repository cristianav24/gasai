<?php

namespace Tests\Feature;

use App\Filament\Pages\Despacho;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
        $this->owner = User::factory()->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function token(User $user): void
    {
        DeviceToken::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'token' => 'ExponentPushToken[' . $user->id . ']',
        ]);
    }

    public function test_un_pedido_de_whatsapp_avisa_al_dueno(): void
    {
        $this->token($this->owner);

        Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'pendiente',
            'channel' => 'whatsapp',
            'total' => 30,
        ]);

        $sent = $this->pushNotifier()->sent;
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Nuevo pedido', $sent[0]['title']);
        $this->assertContains('ExponentPushToken[' . $this->owner->id . ']', $sent[0]['tokens']);
    }

    public function test_un_pedido_del_playground_no_avisa(): void
    {
        $this->token($this->owner);

        Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'pendiente',
            'channel' => 'playground',
            'total' => 30,
        ]);

        $this->assertCount(0, $this->pushNotifier()->sent);
    }

    public function test_asignar_repartidor_le_avisa(): void
    {
        $courier = User::factory()->create();
        $this->tenant->users()->attach($courier->id, ['role' => 'courier']);
        $this->token($courier);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'confirmado',
            'channel' => 'manual',
            'total' => 30,
        ]);

        // Limpiamos lo que el pedido "manual" ya envió al dueño.
        $this->pushNotifier()->sent = [];

        Livewire::test(Despacho::class)->call('assignCourier', $order->id, $courier->id);

        $sent = $this->pushNotifier()->sent;
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Nuevo reparto', $sent[0]['title']);
        $this->assertContains('ExponentPushToken[' . $courier->id . ']', $sent[0]['tokens']);
    }

    public function test_un_mensaje_nuevo_avisa_a_duenos_y_operadores_no_repartidores(): void
    {
        $operator = User::factory()->create();
        $this->tenant->users()->attach($operator->id, ['role' => 'operator']);
        $courier = User::factory()->create();
        $this->tenant->users()->attach($courier->id, ['role' => 'courier']);

        $this->token($this->owner);
        $this->token($operator);
        $this->token($courier);

        $conv = Conversation::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'status' => 'bot',
            'contact_name' => 'Javier', 'phone' => '+51999888777',
        ]);

        app(\App\Services\Push\PushDispatcher::class)->notifyNewMessage($conv, 'Hola, quiero 2 bidones', true);

        $sent = $this->pushNotifier()->sent;
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Nuevo chat: Javier', $sent[0]['title']);
        $this->assertContains('ExponentPushToken[' . $this->owner->id . ']', $sent[0]['tokens']);
        $this->assertContains('ExponentPushToken[' . $operator->id . ']', $sent[0]['tokens']);
        $this->assertNotContains('ExponentPushToken[' . $courier->id . ']', $sent[0]['tokens']);
    }
}
