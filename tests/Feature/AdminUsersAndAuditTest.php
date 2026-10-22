<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUsersAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['role' => $role] + $attributes);
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_lists_and_filters_accounts(): void
    {
        $admin = $this->user('administrator');
        $this->user('educator', ['email' => 'priya@example.com', 'first_name' => 'Priya']);
        $this->user('childcare_centre');

        $this->actingAs($admin)->getJson('/api/v1/admin/users?role=educator')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'priya@example.com')
            ->assertJsonPath('meta.counts.childcare_centre', 1);
        $this->actingAs($admin)->getJson('/api/v1/admin/users?q=priya')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_suspended_account_loses_access_until_reinstated(): void
    {
        $admin = $this->user('administrator');
        $educator = $this->user('educator');

        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$educator->id}/suspend")->assertOk()->assertJsonPath('data.status', 'suspended');

        Sanctum::actingAs($educator->refresh());
        $this->getJson('/api/v1/auth/me')->assertStatus(403)->assertJsonPath('message', 'This account is not active.');
        $this->getJson('/api/v1/notifications')->assertStatus(403);

        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$educator->id}/reinstate")->assertOk()->assertJsonPath('data.status', 'active');
        Sanctum::actingAs($educator->refresh());
        $this->getJson('/api/v1/auth/me')->assertOk();

        $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?action=user.')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'user.reinstated')
            ->assertJsonPath('data.1.action', 'user.suspended')
            ->assertJsonPath('data.1.actor.id', $admin->id)
            ->assertJsonPath('data.1.new_values.status', 'suspended');
    }

    public function test_admin_cannot_suspend_self_or_other_admins_and_others_cannot_reach_admin(): void
    {
        $admin = $this->user('administrator');
        $other = $this->user('administrator');

        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$admin->id}/suspend")->assertStatus(422);
        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$other->id}/suspend")->assertStatus(422);
        $this->actingAs($this->user('educator'))->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_moderation_and_security_changes_are_audited(): void
    {
        $admin = $this->user('administrator');
        $educator = $this->user('educator', ['password' => 'OldPass123!']);

        $this->actingAs($educator)->putJson('/api/v1/account/password', [
            'current_password' => 'OldPass123!', 'password' => 'NewPass456!@', 'password_confirmation' => 'NewPass456!@',
        ])->assertOk();

        $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs?action=account.password_changed')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor.id', $educator->id)
            ->assertJsonPath('data.0.new_values', null);

        $this->actingAs($admin)->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('data.totals.users', 2);
    }

    public function test_health_check_reports_each_part(): void
    {
        $this->getJson('/api/v1/health')->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.migrations.pending', 0)
            ->assertJsonPath('checks.public_uploads.ok', true)
            ->assertJsonPath('checks.queue.ok', true);
    }
}
