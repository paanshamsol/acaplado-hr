<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function notify(User $user, string $message = 'Test notification'): string
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\LeaveDecided',
            'data' => [
                'message' => $message,
                'link' => route('notifications.index'),
            ],
            'read_at' => null,
        ])->id;
    }

    public function test_staff_can_remove_a_single_notification(): void
    {
        $staff = User::factory()->create();
        $id = $this->notify($staff);

        $this->actingAs($staff)
            ->delete(route('notifications.destroy', $id))
            ->assertRedirect()
            ->assertSessionHas('status', 'notification-deleted');

        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_staff_cannot_remove_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->notify($owner);

        $this->actingAs($other)
            ->delete(route('notifications.destroy', $id))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }

    public function test_staff_can_clear_their_notification_history(): void
    {
        $staff = User::factory()->create();
        $owner = User::factory()->create();

        $this->notify($staff, 'First');
        $this->notify($staff, 'Second');
        $otherId = $this->notify($owner, 'Someone else');

        $this->actingAs($staff)
            ->delete(route('notifications.destroy-all'))
            ->assertRedirect()
            ->assertSessionHas('status', 'notifications-cleared');

        $this->assertSame(0, $staff->notifications()->count());
        $this->assertDatabaseHas('notifications', ['id' => $otherId]);
    }

    public function test_notifications_page_shows_the_remove_and_clear_actions(): void
    {
        $staff = User::factory()->create();
        $id = $this->notify($staff);

        $this->actingAs($staff)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Clear all')
            ->assertSee(route('notifications.destroy-all'))
            ->assertSee(route('notifications.destroy', $id));
    }

    public function test_notifications_page_hides_the_clear_action_when_history_is_empty(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Clear all')
            ->assertSee('No notifications yet.');
    }
}
