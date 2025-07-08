<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Notification; 
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade; 
use Carbon\Carbon; 

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;
    /** @var \App\Models\User */
    protected $memberUser; // User who will receive notifications

    protected function setUp(): void
    {
        parent::setUp();

        NotificationFacade::fake(); // Fake Laravel's Notification system

        // Create users
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'role' => 'admin']);
        $this->memberUser = User::factory()->create(['email' => 'member@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
    }

    // Helper to create a notification for a specific user
    private function createNotificationForUser(User $user, bool $read = false): Notification
    {
        return Notification::create([
            'id' => \Illuminate\Support\Str::uuid(), 
            'type' => 'App\\Notifications\\TestNotification',
            'user_id' => $user->id,
            'data' => ['message' => 'This is a test notification for ' . $user->name],
            'read_at' => $read ? Carbon::now() : null,
        ]);
    }

    // --- Get All Notifications Tests ---

    public function test_admin_can_view_all_notifications(): void
    {
        $this->createNotificationForUser($this->adminUser);
        $this->createNotificationForUser($this->memberUser);

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/notifications');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'notifications'); 
    }

    public function test_admin_can_view_all_unread_notifications(): void
    {
        $this->createNotificationForUser($this->adminUser, true); // Read
        $this->createNotificationForUser($this->adminUser, false); // Unread
        $this->createNotificationForUser($this->memberUser, false); // Unread for another user

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/notifications?status=unread');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'notifications'); // Should see 2 unread (1 for admin, 1 for member)
    }

    public function test_member_can_view_only_their_notifications(): void
    {
        $this->createNotificationForUser($this->adminUser); // Admin's notification
        $notificationForMember = $this->createNotificationForUser($this->memberUser); // Member's notification

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/notifications');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'notifications') // Should see only their notification
                 ->assertJsonFragment(['id' => $notificationForMember->id]);
    }

    public function test_member_can_view_only_their_unread_notifications(): void
    {
        $this->createNotificationForUser($this->memberUser, true); // Member's read
        $notificationForMemberUnread = $this->createNotificationForUser($this->memberUser, false); // Member's unread
        $this->createNotificationForUser($this->adminUser, false); // Admin's unread

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/notifications?status=unread');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'notifications')
                 ->assertJsonFragment(['id' => $notificationForMemberUnread->id]);
    }

    // --- Get Specific Notification Tests ---

    public function test_admin_can_view_any_specific_notification(): void
    {
        $notification = $this->createNotificationForUser($this->memberUser); // Member's notification
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/notifications/' . $notification->id);

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $notification->id]);
    }

    public function test_member_can_view_their_specific_notification(): void
    {
        $notification = $this->createNotificationForUser($this->memberUser); // Member's notification
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/notifications/' . $notification->id);

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $notification->id]);
    }

    public function test_unauthorized_user_cannot_view_specific_notification(): void
    {
        $notification = $this->createNotificationForUser($this->adminUser); // Admin's notification
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/notifications/' . $notification->id);

        $response->assertStatus(403);
    }

    // --- Update Notification (Mark as Read) Tests ---

    public function test_member_can_mark_their_notification_as_read(): void
    {
        $notification = $this->createNotificationForUser($this->memberUser, false); // Unread notification
        $this->assertNull($notification->read_at); // Ensure it's unread

        $response = $this->actingAs($this->memberUser, 'sanctum')->putJson('/api/notifications/' . $notification->id, []); // Empty body is fine for mark as read

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'Notification marked as read']);

        $this->assertNotNull($notification->fresh()->read_at); // Reload from DB and check
    }

    public function test_admin_can_mark_any_notification_as_read(): void
    {
        $notification = $this->createNotificationForUser($this->memberUser, false); // Unread notification for member
        $this->assertNull($notification->read_at);

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson('/api/notifications/' . $notification->id, []);

        $response->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_unauthorized_user_cannot_mark_notification_as_read(): void
    {
        $notification = $this->createNotificationForUser($this->adminUser, false);
        $response = $this->actingAs($this->memberUser, 'sanctum')->putJson('/api/notifications/' . $notification->id, []);

        $response->assertStatus(403);
        $this->assertNull($notification->fresh()->read_at); // Should remain unread
    }

    // --- Mark All Notifications as Read Tests ---

    public function test_member_can_mark_all_their_notifications_as_read(): void
    {
        $this->createNotificationForUser($this->memberUser, false);
        $this->createNotificationForUser($this->memberUser, false);
        $this->createNotificationForUser($this->memberUser, true); // Already read
        $this->createNotificationForUser($this->adminUser, false); // Admin's notification

        $this->assertEquals(2, $this->memberUser->unreadNotifications->count());

        $response = $this->actingAs($this->memberUser, 'sanctum')->postJson('/api/notifications/mark-all-as-read');

        $response->assertStatus(200)
                 ->assertJsonFragment(['message' => 'All notifications marked as read', 'unread_count' => 0]);

        $this->assertEquals(0, $this->memberUser->fresh()->unreadNotifications->count()); // Reload and check
    }

    // --- Get Unread Count Tests ---

    public function test_member_can_get_their_unread_notifications_count(): void
    {
        $this->createNotificationForUser($this->memberUser, false);
        $this->createNotificationForUser($this->memberUser, true);
        $this->createNotificationForUser($this->adminUser, false);

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
                 ->assertJsonFragment(['unread_count' => 1]); // Only 1 unread for this member
    }

    // --- Delete Notification Tests ---

    public function test_member_can_delete_their_notification(): void
    {
        $notification = $this->createNotificationForUser($this->memberUser);
        $response = $this->actingAs($this->memberUser, 'sanctum')->deleteJson('/api/notifications/' . $notification->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_admin_can_delete_any_notification(): void
    {
        $notification = $this->createNotificationForUser($this->memberUser); // Member's notification
        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/notifications/' . $notification->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_unauthorized_user_cannot_delete_notification(): void
    {
        $notification = $this->createNotificationForUser($this->adminUser); // Admin's notification
        $response = $this->actingAs($this->memberUser, 'sanctum')->deleteJson('/api/notifications/' . $notification->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]); // Should not be deleted
    }
}
