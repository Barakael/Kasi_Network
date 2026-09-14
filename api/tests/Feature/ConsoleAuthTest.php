<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ConsoleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login:owner@kasi.test|127.0.0.1');
    }

    public function test_a_user_can_sign_in_and_receive_a_token(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->for($tenant)->owner()->create([
            'email' => 'owner@kasi.test',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test device',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role', 'tenant' => ['uuid']]]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_the_response_never_includes_payment_credentials(): void
    {
        $tenant = Tenant::factory()->withPayments()->create();
        User::factory()->for($tenant)->owner()->create(['email' => 'owner@kasi.test']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test device',
        ]);

        /*
         * These are the operator's live mobile money keys. The console needs to
         * know whether they are set, never what they are.
         */
        $response->assertOk()
            ->assertJsonMissingPath('user.tenant.snippe_api_key')
            ->assertJsonMissingPath('user.tenant.snippe_webhook_secret')
            ->assertJsonPath('user.tenant.accepts_online_payments', true);

        $this->assertStringNotContainsString('snp_test_', $response->getContent());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        User::factory()->owner()->create(['email' => 'owner@kasi.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'not-the-password',
            'device_name' => 'Test device',
        ])->assertStatus(422);
    }

    public function test_an_unknown_email_gives_the_same_error_as_a_wrong_password(): void
    {
        User::factory()->owner()->create(['email' => 'owner@kasi.test']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'not-the-password',
            'device_name' => 'Test device',
        ]);

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@kasi.test',
            'password' => 'not-the-password',
            'device_name' => 'Test device',
        ]);

        // Identical responses, so the endpoint cannot be used to discover which
        // addresses have accounts.
        $this->assertSame($wrongPassword->status(), $unknownEmail->status());
        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $unknownEmail->json('errors.email'),
        );
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        User::factory()->owner()->inactive()->create(['email' => 'owner@kasi.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test device',
        ])->assertStatus(422);
    }

    public function test_a_user_of_a_suspended_operator_cannot_use_the_api(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $user = User::factory()->for($tenant)->owner()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        User::factory()->owner()->create(['email' => 'owner@kasi.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'owner@kasi.test',
                'password' => 'wrong',
                'device_name' => 'Test device',
            ])->assertStatus(422);
        }

        // Voucher codes and console passwords are the two guessable secrets in
        // the system, so the login endpoint locks out rather than merely logging.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test device',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Too many attempts', (string) $response->json('errors.email.0'));
    }

    public function test_signing_in_again_from_the_same_device_replaces_the_previous_token(): void
    {
        $user = User::factory()->owner()->create(['email' => 'owner@kasi.test']);

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Shared counter tablet',
        ])->json('token');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Shared counter tablet',
        ])->assertOk();

        $this->assertSame(1, $user->tokens()->count());

        // The superseded token must not still work.
        $this->withHeader('Authorization', 'Bearer '.$first)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_an_agent_token_is_limited_to_printing(): void
    {
        User::factory()->agent()->create(['email' => 'agent@kasi.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'agent@kasi.test',
            'password' => 'password',
            'device_name' => 'Counter phone',
        ])->assertOk();

        $token = PersonalAccessToken::query()->firstOrFail();

        // Abilities mirror the role, so a stolen agent token stays useless for
        // management endpoints even if route middleware is later loosened.
        $this->assertSame(['vouchers:print'], $token->abilities);
    }

    public function test_signing_out_revokes_only_the_current_token(): void
    {
        $user = User::factory()->owner()->create();
        $keep = $user->createToken('Other device');
        $current = $user->createToken('This device');

        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertNull($user->tokens()->find($current->accessToken->id));
        $this->assertNotNull($user->tokens()->find($keep->accessToken->id));
    }

    public function test_the_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_signing_in_is_recorded_in_the_audit_log(): void
    {
        $user = User::factory()->owner()->create(['email' => 'owner@kasi.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@kasi.test',
            'password' => 'password',
            'device_name' => 'Test device',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login',
        ]);
    }
}
