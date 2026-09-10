<?php

namespace Tests\Feature\Notification;

use App\Models\AppNotification;
use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationMessageTest extends TestCase
{
    use RefreshDatabase;

    /** System notifications (no from_user_id) are excluded from the direct-message list. */
    public function test_system_notifications_excluded_from_messages_index(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);

        AppNotification::create([
            'user_id' => $tenant->id,
            'message' => 'Système: paiement reçu',
            'is_read' => false,
        ]);

        $res = $this->actingAs($tenant, 'sanctum')->getJson('/api/messages');

        $res->assertOk();
        $this->assertCount(0, $res->json('data'));
    }

    /** Domiciliataire can send a direct message to one of their clients. */
    public function test_domiciliataire_can_send_message_to_own_client(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $clientUser = User::factory()->create(['role' => 'client']);
        Entreprise::create([
            'domiciliataire_id' => $tenant->id,
            'client_user_id' => $clientUser->id,
            'raison_sociale' => 'CLIENT SARL',
        ]);

        $res = $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'client_user_id' => $clientUser->id,
            'message' => 'Votre contrat va expirer bientôt.',
            'subject' => 'Renouvellement',
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('notifications', [
            'from_user_id' => $tenant->id,
            'user_id' => $clientUser->id,
        ]);
    }

    /** A domiciliataire cannot message a client that isn't theirs. */
    public function test_cannot_message_a_client_not_belonging_to_tenant(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $otherClient = User::factory()->create(['role' => 'client']);

        $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'client_user_id' => $otherClient->id,
            'message' => 'Hello',
        ])->assertStatus(404);
    }

    /** Client can mark a received message as read; sender can then see the read receipt. */
    public function test_client_marks_message_read_and_sender_sees_receipt(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $clientUser = User::factory()->create(['role' => 'client']);

        $msg = AppNotification::create([
            'user_id' => $clientUser->id,
            'from_user_id' => $tenant->id,
            'message' => 'Bonjour',
            'is_read' => false,
        ]);

        $this->actingAs($clientUser, 'sanctum')
            ->postJson("/api/messages/{$msg->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->actingAs($tenant, 'sanctum')
            ->getJson("/api/messages/{$msg->id}/receipt")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);
    }

    /** Client unread message count only includes unread direct messages received by that client. */
    public function test_client_unread_message_count_counts_received_unread_direct_messages(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $clientUser = User::factory()->create(['role' => 'client']);
        $otherClient = User::factory()->create(['role' => 'client']);

        AppNotification::create([
            'user_id' => $clientUser->id,
            'from_user_id' => $tenant->id,
            'message' => 'Unread',
            'is_read' => false,
        ]);
        AppNotification::create([
            'user_id' => $clientUser->id,
            'from_user_id' => $tenant->id,
            'message' => 'Read',
            'is_read' => true,
        ]);
        AppNotification::create([
            'user_id' => $otherClient->id,
            'from_user_id' => $tenant->id,
            'message' => 'Other client',
            'is_read' => false,
        ]);
        AppNotification::create([
            'user_id' => $clientUser->id,
            'message' => 'System notification',
            'is_read' => false,
        ]);

        $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_messages_count', 1);
    }

    /** Visiting messages can clear every unread direct message for the current client. */
    public function test_client_can_mark_all_received_messages_read(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);
        $clientUser = User::factory()->create(['role' => 'client']);
        $otherClient = User::factory()->create(['role' => 'client']);

        $message = AppNotification::create([
            'user_id' => $clientUser->id,
            'from_user_id' => $tenant->id,
            'message' => 'Unread',
            'is_read' => false,
        ]);
        $otherMessage = AppNotification::create([
            'user_id' => $otherClient->id,
            'from_user_id' => $tenant->id,
            'message' => 'Other client',
            'is_read' => false,
        ]);
        $systemNotification = AppNotification::create([
            'user_id' => $clientUser->id,
            'message' => 'System notification',
            'is_read' => false,
        ]);

        $this->actingAs($clientUser, 'sanctum')
            ->postJson('/api/messages/read-all')
            ->assertOk();

        $this->assertTrue($message->fresh()->is_read);
        $this->assertFalse($otherMessage->fresh()->is_read);
        $this->assertFalse($systemNotification->fresh()->is_read);
    }

    /** Notification preferences save & read back correctly, with graceful fallback default. */
    public function test_notification_preferences_roundtrip(): void
    {
        $tenant = User::factory()->create(['role' => 'domiciliataire']);

        $this->actingAs($tenant, 'sanctum')
            ->putJson('/api/notifications/preferences', ['delays' => [1, 3, 6]])
            ->assertOk()
            ->assertJsonPath('data.delays', [1, 3, 6]);

        $res = $this->actingAs($tenant, 'sanctum')->getJson('/api/notifications/preferences');
        $res->assertOk()->assertJsonPath('data.delays', [1, 3, 6]);
    }
}
