<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
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

    public function test_platform_academy_directory_supports_server_side_search_and_pagination(): void
    {
        foreach (array_merge(['Alpha Academy', 'Beta Academy', 'Gamma Academy'], array_map(fn ($index) => 'Academy '.$index, range(1, 10))) as $name) {
            Organization::create(['public_id' => (string) Str::ulid(), 'name_ar' => $name, 'name_en' => $name, 'slug' => Str::slug($name), 'subscription_status' => 'trial']);
        }
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->getJson('/api/platform/overview?per_page=10&page=2&search=Academy')
            ->assertOk()->assertJsonPath('stats.organizations', 13)->assertJsonPath('organizations_pagination.total', 13)->assertJsonPath('organizations_pagination.page', 2)->assertJsonCount(3, 'organizations');
    }

    public function test_platform_admin_can_manage_plan_limits_but_tenant_user_cannot(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = User::factory()->create(['is_platform_admin' => false]);
        $plan = SubscriptionPlan::where('slug', 'growth')->firstOrFail();

        $this->actingAs($admin)->getJson('/api/platform/plans')->assertOk()->assertJsonPath('plans.1.slug', 'growth');
        $this->actingAs($admin)->patchJson('/api/platform/plans/'.$plan->id, ['name' => 'Growth Plus', 'description' => $plan->description, 'monthly_price_minor' => 89900, 'yearly_price_minor' => 899000, 'centre_limit' => 4, 'student_limit' => 1800, 'staff_limit' => 15, 'group_limit' => 100, 'is_active' => true, 'is_featured' => true, 'features' => ['Parent portal']])->assertOk()->assertJsonPath('plan.name', 'Growth Plus');
        $this->actingAs($user)->getJson('/api/platform/plans')->assertForbidden();
    }
}
