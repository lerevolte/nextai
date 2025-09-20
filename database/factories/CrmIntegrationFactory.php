<?php

namespace Database\Factories;

use App\Models\CrmIntegration;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrmIntegration>
 */
class CrmIntegrationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CrmIntegration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['bitrix24', 'amocrm', 'avito', 'salebot']);
        
        return [
            'organization_id' => Organization::factory(),
            'type' => $type,
            'name' => $this->faker->company() . ' ' . ucfirst($type),
            'credentials' => $this->getCredentialsForType($type),
            'settings' => $this->getSettingsForType($type),
            'field_mapping' => [],
            'is_active' => $this->faker->boolean(80),
            'last_sync_at' => $this->faker->optional()->dateTimeThisMonth(),
            'sync_status' => [
                'last_status' => 'success',
                'last_message' => null,
            'salebot' => [
                'api_key' => $this->faker->sha256(),
                'bot_id' => $this->faker->uuid(),
            'salebot' => [
                'default_funnel_id' => $this->faker->optional()->uuid(),
                'auto_start_funnel' => $this->faker->boolean(),
                'sync_variables' => $this->faker->boolean(),
            ],
        ];
    /**
     * Indicate that the integration is for Salebot.
     */
    public function salebot(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'salebot',
            'name' => 'Salebot Integration',
            'credentials' => $this->getCredentialsForType('salebot'),
            'settings' => $this->getSettingsForType('salebot'),
        ]);
    }

    /**
     * Get credentials for specific CRM type
     */
    protected function getCredentialsForType(string $type): array
    {
        return match($type) {
            'bitrix24' => [
                'webhook_url' => 'https://test.bitrix24.ru/rest/1/' . $this->faker->uuid() . '/',
            ],
            'amocrm' => [
                'subdomain' => $this->faker->domainWord(),
                'client_id' => $this->faker->uuid(),
                'client_secret' => $this->faker->sha256(),
                'access_token' => $this->faker->sha256(),
                'refresh_token' => $this->faker->sha256(),
                'redirect_uri' => $this->faker->url(),
            ],
            'avito' => [
                'client_id' => $this->faker->uuid(),
                'client_secret' => $this->faker->sha256(),
            ],
            default => [],
        };
    }

    /**
     * Get settings for specific CRM type
     */
    protected function getSettingsForType(string $type): array
    {
        return match($type) {
            'bitrix24' => [
                'default_responsible_id' => $this->faker->numberBetween(1, 100),
                'openline_config_id' => $this->faker->optional()->numberBetween(1, 10),
            ],
            'amocrm' => [
                'default_pipeline_id' => $this->faker->numberBetween(1, 10),
            ],
            'avito' => [
                'welcome_message' => $this->faker->sentence(),
                'auto_reply' => $this->faker->boolean(),
            ],
            default => [],
        };
    }

    /**
     * Indicate that the integration is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Indicate that the integration is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the integration is for Bitrix24.
     */
    public function bitrix24(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bitrix24',
            'name' => 'Bitrix24 Integration',
            'credentials' => $this->getCredentialsForType('bitrix24'),
            'settings' => $this->getSettingsForType('bitrix24'),
        ]);
    }

    /**
     * Indicate that the integration is for AmoCRM.
     */
    public function amocrm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'amocrm',
            'name' => 'AmoCRM Integration',
            'credentials' => $this->getCredentialsForType('amocrm'),
            'settings' => $this->getSettingsForType('amocrm'),
        ]);
    }

    /**
     * Indicate that the integration is for Avito.
     */
    public function avito(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'avito',
            'name' => 'Avito Integration',
            'credentials' => $this->getCredentialsForType('avito'),
            'settings' => $this->getSettingsForType('avito'),
        ]);
    }
}