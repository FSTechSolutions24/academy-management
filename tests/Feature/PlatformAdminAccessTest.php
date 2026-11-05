<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_open_global_overview_without_tenant_membership(): void
    {
        Organization::create([
            'public_id' => (string) Str::ulid(),
            'name_ar' => 'Test Academy',
            'name_en' => 'Test Academy',
            'slug' => 'test-academy',
            'subscription_status' => 'trial',
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->getJson('/api/platform/overview')
            ->assertOk()
            ->assertJsonPath('stats.organizations', 1)
            ->assertJsonPath('stats.platform_admins', 1)
            ->assertJsonPath('organizations.0.name_en', 'Test Academy');
    }

    public function test_tenant_user_cannot_open_platform_overview(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);

        $this->actingAs($user)->getJson('/api/platform/overview')->assertForbidden();
    }
}
