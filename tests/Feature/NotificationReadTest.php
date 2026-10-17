<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $otherMember;

    private User $adminOrder;

    private User $adminInventory;

    private User $owner;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        $adminOrderRole = Role::where('role_name', Role::ADMIN_ORDER)->firstOrFail();
        $adminInventoryRole = Role::where('role_name', Role::ADMIN_INVENTORY)->firstOrFail();
        $ownerRole = Role::where('role_name', Role::OWNER)->firstOrFail();
        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();

        $this->member = User::factory()->create([
            'name' => 'John Member',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->otherMember = User::factory()->create([
            'name' => 'Jane Member',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->adminOrder = User::factory()->create([
            'name' => 'Olivia Order Admin',
            'role_id' => $adminOrderRole->id,
            'is_active' => true,
        ]);

        $this->adminInventory = User::factory()->create([
            'name' => 'Ivan Inventory Admin',
            'role_id' => $adminInventoryRole->id,
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Oscar Owner',
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Clara Cashier',
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);
    }

    public function test_member_can_mark_notification_as_read_and_updates_audit_columns(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->member->id,
            'title' => 'Order processed',
            'message' => 'Your order is on the way',
            'type' => 'order_status',
            'link' => '/track?order=FS-1-ABC',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->member)
            ->from('/notifications')
            ->patch(route('notifications.read', $notification->id));

        $response->assertRedirect('/track?order=FS-1-ABC');

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertSame($this->member->id, $notification->modified_by);
        $this->assertNotNull($notification->modified_on);
    }

    public function test_marking_read_redirects_to_internal_path_link(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->member->id,
            'title' => 'Internal Path Notification',
            'message' => 'Click to check internal path',
            'type' => 'info',
            'link' => '/catalog',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->member)
            ->patch(route('notifications.read', $notification->id));

        $response->assertRedirect('/catalog');
    }

    public function test_marking_read_redirects_to_same_host_url_link(): void
    {
        $targetUrl = url('/catalog');

        $notification = AppNotification::create([
            'user_id' => $this->member->id,
            'title' => 'Same Host Notification',
            'message' => 'Click to check same host URL',
            'type' => 'info',
            'link' => $targetUrl,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->member)
            ->patch(route('notifications.read', $notification->id));

        $response->assertRedirect($targetUrl);
    }

    public function test_marking_read_with_no_link_redirects_back(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->member->id,
            'title' => 'No Link Notification',
            'message' => 'There is no link attached',
            'type' => 'info',
            'link' => null,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->member)
            ->from('/notifications')
            ->patch(route('notifications.read', $notification->id));

        $response->assertRedirect('/notifications');

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertSame($this->member->id, $notification->modified_by);
    }

    public function test_marking_read_with_open_redirect_link_redirects_back(): void
    {
        $badLinks = [
            'https://evil.com/phishing',
            'http://evil.com',
            '//evil.com/test',
            '/\\evil.com',
            'javascript:alert(1)',
        ];

        foreach ($badLinks as $badLink) {
            $notification = AppNotification::create([
                'user_id' => $this->member->id,
                'title' => 'Malicious Link',
                'message' => 'Do not follow evil links',
                'type' => 'info',
                'link' => $badLink,
                'is_read' => false,
            ]);

            $response = $this->actingAs($this->member)
                ->from('/notifications')
                ->patch(route('notifications.read', $notification->id));

            $response->assertRedirect('/notifications');

            $notification->refresh();
            $this->assertTrue($notification->is_read);
        }
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $otherNotification = AppNotification::create([
            'user_id' => $this->otherMember->id,
            'title' => 'Private update',
            'message' => 'Not for you',
            'type' => 'info',
            'link' => '/catalog',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->member)
            ->patch(route('notifications.read', $otherNotification->id));

        $response->assertNotFound();

        $otherNotification->refresh();
        $this->assertFalse($otherNotification->is_read);
    }

    public function test_staff_can_mark_notification_as_read_via_admin_route(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->adminOrder->id,
            'title' => 'New Order Alert',
            'message' => 'New online order paid',
            'type' => 'new_order',
            'link' => '/admin/orders/10',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->adminOrder)
            ->patch(route('admin.notifications.read', $notification->id));

        $response->assertRedirect('/admin/orders/10');

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertSame($this->adminOrder->id, $notification->modified_by);
        $this->assertNotNull($notification->modified_on);
    }

    public function test_staff_layout_renders_bell_for_staff_with_permission(): void
    {
        $response = $this->actingAs($this->adminOrder)
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.notifications'));
    }

    public function test_owner_and_cashier_have_no_bell(): void
    {
        // Owner should NOT see notification bell
        $ownerResponse = $this->actingAs($this->owner)
            ->get(route('admin.dashboard'));

        $ownerResponse->assertOk();
        $ownerResponse->assertDontSee(route('admin.notifications'));

        // Cashier should NOT see notification bell
        $cashierResponse = $this->actingAs($this->cashier)
            ->get(route('cashier.pos'));

        $cashierResponse->assertOk();
        $cashierResponse->assertDontSee(route('admin.notifications'));
        $cashierResponse->assertDontSee(route('notifications'));
    }

    public function test_api_controller_mark_read_keeps_existing_behavior(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->member->id,
            'title' => 'API notification',
            'message' => 'Testing API read',
            'type' => 'info',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->member)
            ->patchJson('/api/notifications/'.$notification->id.'/read');

        $response->assertOk();
        $response->assertJson(['message' => 'Marked as read.']);

        $notification->refresh();
        $this->assertTrue($notification->is_read);

        // Forbidden when touching other user's notification in API
        $otherNotification = AppNotification::create([
            'user_id' => $this->otherMember->id,
            'title' => 'Other API notification',
            'message' => 'Cannot touch',
            'type' => 'info',
            'is_read' => false,
        ]);

        $forbiddenResponse = $this->actingAs($this->member)
            ->patchJson('/api/notifications/'.$otherNotification->id.'/read');

        $forbiddenResponse->assertForbidden();
    }
}
