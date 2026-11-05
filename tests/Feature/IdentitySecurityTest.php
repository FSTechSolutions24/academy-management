<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class IdentitySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function registerOwner(string $email = 'owner@example.test'): User
    {
        $this->postJson('/api/register', [
            'name' => 'Mariam Beshoy', 'email' => $email, 'academy_name' => 'Future Leaders',
            'phone' => '+201000000000', 'password' => 'Academy2026', 'password_confirmation' => 'Academy2026',
        ])->assertCreated()->assertJsonPath('verification_required', true);

        return User::where('email', $email)->firstOrFail();
    }

    public function test_registration_requires_email_verification_before_workspace_access(): void
    {
        Notification::fake();
        $user = $this->registerOwner();

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_signed_verification_link_unlocks_workspace(): void
    {
        Notification::fake();
        $user = $this->registerOwner();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect('/dashboard?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->getJson('/api/dashboard')->assertOk();
    }

    public function test_unverified_login_reports_the_required_next_step(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'Academy2026']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Academy2026'])
            ->assertOk()->assertJsonPath('verification_required', true)->assertJsonPath('user.verified', false);
    }

    public function test_password_recovery_is_nonenumerating_and_resets_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recover@example.test', 'password' => 'OldPassword2026']);

        $this->postJson('/api/forgot-password', ['email' => 'missing@example.test'])->assertOk();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'NewPassword2026', 'password_confirmation' => 'NewPassword2026',
        ])->assertOk();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'NewPassword2026'])
            ->assertOk()->assertJsonPath('verification_required', false);
    }
}
