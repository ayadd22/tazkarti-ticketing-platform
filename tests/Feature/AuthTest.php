<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Registration
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_buyer_can_register_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Alice Buyer',
            'email'                 => 'alice@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('user.email', 'alice@example.com')
            ->assertJsonPath('user.role', UserRoleEnum::Buyer->value);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'role'  => UserRoleEnum::Buyer->value,
        ]);
    }

    #[Test]
    public function test_registration_requires_name_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Another User',
            'email'                 => 'dup@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function test_registration_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Bob',
            'email'                 => 'bob@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function test_registration_rejects_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Bob',
            'email'                 => 'bob@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function test_public_registration_always_creates_buyer_even_if_admin_role_sent(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Hacker',
            'email'                 => 'hacker@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => UserRoleEnum::Admin->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', UserRoleEnum::Buyer->value);

        $this->assertDatabaseHas('users', [
            'email' => 'hacker@example.com',
            'role'  => UserRoleEnum::Buyer->value,
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'hacker@example.com',
            'role'  => UserRoleEnum::Admin->value,
        ]);
    }

    #[Test]
    public function test_public_registration_always_creates_buyer_even_if_organizer_role_sent(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'FakeOrganizer',
            'email'                 => 'fake@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => UserRoleEnum::Organizer->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', UserRoleEnum::Buyer->value);

        $this->assertDatabaseMissing('users', [
            'email' => 'fake@example.com',
            'role'  => UserRoleEnum::Organizer->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Login
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->buyer()->create([
            'email'    => 'buyer@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'buyer@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', UserRoleEnum::Buyer->value);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function test_login_returns_401_for_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('correct'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    #[Test]
    public function test_login_returns_401_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function test_login_response_does_not_expose_password_hash(): void
    {
        User::factory()->create([
            'email'    => 'safe@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'safe@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);

        $responseBody = json_encode($response->json());
        $this->assertStringNotContainsString('$2y$', $responseBody);
        $this->assertStringNotContainsString('remember_token', $responseBody);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Logout
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_authenticated_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();

        // Issue two tokens so we can confirm only the current one is deleted
        $otherToken   = $user->createToken('other-device')->plainTextToken;
        $currentToken = $user->createToken('current-device')->plainTextToken;

        $this->assertSame(2, $user->tokens()->count());

        $response = $this->withToken($currentToken)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully.');

        // Current token must be gone
        $this->assertSame(1, $user->fresh()->tokens()->count());

        // The other token must still exist
        [$tokenId] = explode('|', $otherToken, 2);
        $this->assertNotNull(
            PersonalAccessToken::find($tokenId),
            'The other device token should not have been deleted.',
        );
    }

    #[Test]
    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Me (authenticated user endpoint)
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_me_returns_authenticated_user_data(): void
    {
        $user  = User::factory()->organizer()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', UserRoleEnum::Organizer->value);
    }

    #[Test]
    public function test_me_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    #[Test]
    public function test_me_does_not_expose_sensitive_fields(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);

        $userPayload = $response->json('user');
        $this->assertArrayNotHasKey('password', $userPayload);
        $this->assertArrayNotHasKey('remember_token', $userPayload);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Original /api/user route (preserved from scaffold)
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_api_user_route_returns_authenticated_user(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('id', $user->id);
    }

    #[Test]
    public function test_api_user_route_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }
}
