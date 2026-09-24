<?php

namespace Tests\Feature\Notification;

use App\Models\AppNotification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_user_sees_only_their_notifications_and_unread_count(): void
    {
        $owner = $this->userWithRole('LANDLORD', 'owner@example.com');
        $other = $this->userWithRole('LANDLORD', 'other@example.com');
        $this->notification($owner, 'LISTING_APPROVED', 'Đã duyệt', null);
        $this->notification($owner, 'LISTING_REJECTED', 'Cần bổ sung', now());
        $this->notification($other, 'LISTING_APPROVED', 'Tin của người khác', null);

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Đã duyệt')
            ->assertSee('Cần bổ sung')
            ->assertSee('1 chưa đọc')
            ->assertDontSee('Tin của người khác');
    }

    public function test_user_can_mark_their_own_notification_read_but_cannot_access_another_users_notification(): void
    {
        $owner = $this->userWithRole('LANDLORD', 'owner@example.com');
        $other = $this->userWithRole('LANDLORD', 'other@example.com');
        $ownNotification = $this->notification($owner, 'LISTING_APPROVED', 'Đã duyệt', null);
        $otherNotification = $this->notification($other, 'LISTING_REJECTED', 'Cần bổ sung', null);

        $this->actingAs($owner)
            ->patch(route('notifications.read', $ownNotification))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($ownNotification->fresh()->read_at);

        $this->actingAs($owner)
            ->patch(route('notifications.read', $otherNotification))
            ->assertNotFound();

        $this->assertNull($otherNotification->fresh()->read_at);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach(Role::query()->where('code', $role)->value('id'), ['assigned_at' => now()]);

        return $user;
    }

    private function notification(User $user, string $type, string $title, mixed $readAt): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => $user->id,
            'notification_type' => $type,
            'title' => $title,
            'message' => 'Nội dung thông báo',
            'entity_type' => 'listing',
            'entity_id' => 1,
            'read_at' => $readAt,
            'created_at' => now(),
        ]);
    }
}
