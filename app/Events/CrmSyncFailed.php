<?
namespace App\Events;

use App\Models\Conversation;
use App\Models\CrmIntegration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmSyncFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;
    public CrmIntegration $integration;
    public string $error;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation, CrmIntegration $integration, string $error)
    {
        $this->conversation = $conversation;
        $this->integration = $integration;
        $this->error = $error;
    }
}