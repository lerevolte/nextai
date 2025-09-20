<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Models\Organization;
use App\Models\User;
use App\Services\CRM\Providers\SalebotProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalebotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Bot $bot;
    protected CrmIntegration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();
        $this->user->update(['organization_id' => $this->organization->id]);
        
        $this->bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->integration = CrmIntegration::factory()->salebot()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_create_salebot_integration()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('crm.store', $this->organization), [
            'type' => 'salebot',
            'name' => 'Test Salebot',
            'credentials' => [
                'api_key' => 'test-api-key',
                'bot_id' => 'test-bot-id',
            ],
            'settings' => [
                'default_funnel_id' => 'funnel-123',
                'auto_start_funnel' => true,
                'sync_variables' => true,
            ],
            'bot_ids' => [$this->bot->id],
        ]);

        $this->assertDatabaseHas('crm_integrations', [
            'organization_id' => $this->organization->id,
            'type' => 'salebot',
            'name' => 'Test Salebot',
        ]);
    }

    public function test_can_start_funnel_for_conversation()
    {
        $this->actingAs($this->user);

        $this->integration->bots()->attach($this->bot->id, [
            'is_active' => true,
        ]);

        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
            'metadata' => ['salebot_client_id' => 'client-123'],
        ]);

        // Mock Salebot Provider
        $provider = $this->mock(SalebotProvider::class);
        $provider->shouldReceive('startFunnel')
            ->with('client-123', 'funnel-456', null)
            ->once()
            ->andReturn(true);

        $response = $this->postJson(route('crm.salebot.start-funnel', [$this->organization, $this->integration]), [
            'conversation_id' => $conversation->id,
            'funnel_id' => 'funnel-456',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_can_send_broadcast_message()
    {
        $this->actingAs($this->user);

        // Mock Salebot Provider
        $provider = $this->mock(SalebotProvider::class);
        $provider->shouldReceive('broadcastMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'broadcast_id' => 'broadcast-123',
                'recipients_count' => 42,
            ]);

        $response = $this->postJson(route('crm.salebot.broadcast', [$this->organization, $this->integration]), [
            'message' => 'Test broadcast message',
            'filters' => ['city' => 'Moscow'],
            'delay' => 60,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'recipients_count' => 42,
        ]);
    }

    public function test_can_transfer_to_operator()
    {
        $this->actingAs($this->user);

        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
            'metadata' => ['salebot_client_id' => 'client-123'],
            'status' => 'active',
        ]);

        // Mock Salebot Provider
        $provider = $this->mock(SalebotProvider::class);
        $provider->shouldReceive('transferToOperator')
            ->with('client-123', 'operator-456')
            ->once()
            ->andReturn(true);

        $response = $this->postJson(route('crm.salebot.transfer-operator', [$this->organization, $this->integration]), [
            'conversation_id' => $conversation->id,
            'operator_id' => 'operator-456',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $conversation->refresh();
        $this->assertEquals('waiting_operator', $conversation->status);
    }

    public function test_webhook_creates_conversation_from_salebot()
    {
        $webhookData = [
            'event' => 'message',
            'api_key' => 'test-api-key',
            'client_id' => 'client-789',
            'message' => 'Привет, мне нужна помощь',
            'platform' => 'telegram',
        ];

        $response = $this->postJson('/webhooks/crm/salebot', $webhookData);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_can_get_funnel_statistics()
    {
        $this->actingAs($this->user);

        // Mock Salebot Provider
        $provider = $this->mock(SalebotProvider::class);
        $provider->shouldReceive('getFunnelStats')
            ->with('funnel-123')
            ->once()
            ->andReturn([
                'total_clients' => 100,
                'active_funnels' => 25,
                'completed' => 75,
                'conversion' => 75,
            ]);

        $response = $this->getJson(route('crm.salebot.funnel-stats', [$this->organization, $this->integration]) . '?funnel_id=funnel-123');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'stats' => [
                'total_clients' => 100,
                'active_funnels' => 25,
                'completed' => 75,
                'conversion' => 75,
            ],
        ]);
    }

    public function test_can_sync_client_variables()
    {
        $this->actingAs($this->user);

        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
            'metadata' => ['salebot_client_id' => 'client-123'],
        ]);

        // Mock Salebot Provider
        $provider = $this->mock(SalebotProvider::class);
        $provider->shouldReceive('updateLead')
            ->with('client-123', ['name' => 'John Doe', 'city' => 'Moscow'])
            ->once()
            ->andReturn(['client_id' => 'client-123', 'updated' => true]);

        $response = $this->postJson(route('crm.salebot.sync-variables', [$this->organization, $this->integration]), [
            'conversation_id' => $conversation->id,
            'variables' => [
                'name' => 'John Doe',
                'city' => 'Moscow',
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'result' => [
                'client_id' => 'client-123',
                'updated' => true,
            ],
        ]);
    }

    public function test_can_stop_funnel()
    {
        $this->actingAs($this->user);

        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
            'metadata' => ['salebot_client_id' => 'client-123'],
        ]);

        // Mock Salebot Provider
        $provider = $this->mock(SalebotProvider::class);
        $provider->shouldReceive('stopFunnel')
            ->with('client-123', 'funnel-456')
            ->once()
            ->andReturn(true);

        $response = $this->postJson(route('crm.salebot.stop-funnel', [$this->organization, $this->integration]), [
            'conversation_id' => $conversation->id,
            'funnel_id' => 'funnel-456',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_salebot_webhook_handles_funnel_completed()
    {
        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
            'status' => 'active',
        ]);

        $this->integration->createSyncEntity(
            'lead',
            $conversation->id,
            'client-123',
            []
        );

        $webhookData = [
            'event' => 'funnel_completed',
            'api_key' => 'test-api-key',
            'client_id' => 'client-123',
            'funnel_id' => 'funnel-456',
        ];

        $response = $this->postJson('/webhooks/crm/salebot', $webhookData);
        $response->assertOk();

        $conversation->refresh();
        $this->assertEquals('closed', $conversation->status);
    }
}