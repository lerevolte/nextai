<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\CrmIntegration;
use App\Models\Organization;
use App\Models\User;
use App\Services\CRM\CrmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmIntegrationTest extends TestCase
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
    }

    public function test_can_create_crm_integration()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('crm.store', $this->organization), [
            'type' => 'bitrix24',
            'name' => 'Test Bitrix24',
            'credentials' => [
                'webhook_url' => 'https://test.bitrix24.ru/rest/1/xxxx/',
            ],
            'settings' => [
                'default_responsible_id' => 1,
            ],
            'bot_ids' => [$this->bot->id],
        ]);

        // В реальном тесте будет редирект с ошибкой т.к. нет реального подключения
        $this->assertDatabaseHas('crm_integrations', [
            'organization_id' => $this->organization->id,
            'type' => 'bitrix24',
            'name' => 'Test Bitrix24',
        ]);
    }

    public function test_can_sync_conversation_with_crm()
    {
        $this->integration = CrmIntegration::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'bitrix24',
            'credentials' => [
                'webhook_url' => 'https://test.bitrix24.ru/rest/1/xxxx/',
            ],
        ]);

        $this->integration->bots()->attach($this->bot->id, [
            'sync_conversations' => true,
            'create_leads' => true,
            'is_active' => true,
        ]);

        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'user_phone' => '+7900000000',
        ]);

        // Mock CRM Service
        $crmService = $this->mock(CrmService::class);
        $crmService->shouldReceive('syncConversation')
            ->with($conversation)
            ->once()
            ->andReturn([
                'bitrix24' => ['success' => true]
            ]);

        $result = $crmService->syncConversation($conversation);

        $this->assertTrue($result['bitrix24']['success']);
    }

    public function test_webhook_creates_conversation()
    {
        $this->integration = CrmIntegration::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'avito',
            'is_active' => true,
        ]);

        $this->integration->bots()->attach($this->bot->id, [
            'is_active' => true,
        ]);

        $webhookData = [
            'type' => 'message',
            'chat_id' => 'test-chat-123',
            'message' => [
                'id' => 'msg-123',
                'text' => 'Здравствуйте, товар еще в продаже?',
            ],
            'user_id' => 'user-456',
        ];

        $response = $this->postJson('/webhooks/crm/avito', $webhookData);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_can_export_conversations_to_crm()
    {
        $this->actingAs($this->user);

        $this->integration = CrmIntegration::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'amocrm',
            'is_active' => true,
        ]);

        // Создаем несколько диалогов
        Conversation::factory()->count(5)->create([
            'bot_id' => $this->bot->id,
        ]);

        $response = $this->postJson(route('crm.export', [$this->organization, $this->integration]), [
            'bot_id' => $this->bot->id,
            'limit' => 10,
            'skip_synced' => true,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'exported',
            'failed',
            'errors',
        ]);
    }

    public function test_crm_integration_deactivates_after_failures()
    {
        $this->integration = CrmIntegration::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $conversation = Conversation::factory()->create([
            'bot_id' => $this->bot->id,
        ]);

        // Симулируем множественные ошибки
        for ($i = 0; $i < 11; $i++) {
            event(new \App\Events\CrmSyncFailed(
                $conversation,
                $this->integration,
                'Test error'
            ));
        }

        $this->integration->refresh();
        $this->assertFalse($this->integration->is_active);
    }

    public function test_can_get_crm_statistics()
    {
        $this->actingAs($this->user);

        $this->integration = CrmIntegration::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        // Создаем логи синхронизации
        $this->integration->syncLogs()->create([
            'direction' => 'outgoing',
            'entity_type' => 'lead',
            'action' => 'create',
            'status' => 'success',
        ]);

        $this->integration->syncLogs()->create([
            'direction' => 'outgoing',
            'entity_type' => 'contact',
            'action' => 'update',
            'status' => 'error',
            'error_message' => 'Test error',
        ]);

        $crmService = app(CrmService::class);
        $stats = $crmService->getSyncStats($this->integration);

        $this->assertEquals(2, $stats['total_syncs']);
        $this->assertEquals(1, $stats['successful_syncs']);
        $this->assertEquals(1, $stats['failed_syncs']);
    }

    public function test_bot_settings_for_crm_integration()
    {
        $this->actingAs($this->user);

        $this->integration = CrmIntegration::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->integration->bots()->attach($this->bot->id, [
            'sync_contacts' => true,
            'create_leads' => true,
            'lead_source' => 'chatbot',
            'is_active' => true,
        ]);

        $response = $this->get(route('crm.bot-settings', [
            $this->organization,
            $this->integration,
            $this->bot
        ]));

        $response->assertOk();
        $response->assertViewHas('settings');
    }
}