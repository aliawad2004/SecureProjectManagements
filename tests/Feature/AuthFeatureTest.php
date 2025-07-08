<?php

namespace Tests\Feature;

use Auth;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum; // <--- تأكد من استيراد Sanctum (الـ Facade)

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;
    /** @var \App\Models\User */
    protected $memberUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a default admin user for authentication
        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        $this->memberUser = User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->adminUser->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'access_token', 'token_type']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->adminUser->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_admin_can_create_new_user(): void
    {
        $newUser = [
            'name' => 'New Test User',
            'email' => 'newuser@example.com',
            'password' => 'password',
            'role' => 'member',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/users', $newUser);

        $response->assertStatus(201)
            ->assertJsonFragment(['email' => 'newuser@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_non_admin_cannot_create_new_user(): void
    {
        $newUser = [
            'name' => 'Unauthorized User',
            'email' => 'unauthuser@example.com',
            'password' => 'password',
            'role' => 'member',
        ];

        $response = $this->actingAs($this->memberUser, 'sanctum')
            ->postJson('/api/users', $newUser);

        $response->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_user_can_get_authenticated_user_details(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => $this->adminUser->email]);
    }

    public function test_user_can_logout(): void
    {
        // 1. Log in the user to get a token via the API call
        $loginResponse = $this->postJson('/api/login', [
            'email' => $this->memberUser->email,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('access_token');

        $hashedToken = hash('sha256', $token);

        // Assert that the token exists in the database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => get_class($this->memberUser),
            'tokenable_id' => $this->memberUser->id,
            'token' => $hashedToken,
        ]);


        // 2. Attempt to logout using the obtained token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        // 3. Assert that the token has been removed from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => get_class($this->memberUser),
            'tokenable_id' => $this->memberUser->id,
            'token' => $hashedToken,
        ]);

        // <--- التعديلات الجديدة هنا: بديل لـ Auth::setUser(null);
        // Forget the current user from all guards, which should clear the authenticated state.
        Auth::logout(); // This method specifically clears the authenticated user from the session and current request.
        // It's typically used for web guards, but in tests, it often helps reset the state.
        // Also, it implicitly calls Auth::forgetGuards() and similar reset methods.
        // If still fails, try explicitly setting resolved guards to empty array:
        // Auth::setResolvedGuards([]);
        // <--- نهاية التعديلات

        // 4. Verify the user is actually logged out by trying to access a protected route with the revoked token
        $responseProtected = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/user');

        $responseProtected->assertStatus(401); // Expect 401 Unauthorized
    }


    public function test_login_rate_limiting(): void
    {
        // Test too many login attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $this->memberUser->email,
                'password' => 'wrong-password', // Use wrong password to keep trying
            ]);
            $response->assertStatus(401);
        }

        // The 6th attempt should be rate limited
        $response = $this->postJson('/api/login', [
            'email' => $this->memberUser->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertArrayHasKey('Retry-After', $response->headers->all());
    }

    public function test_create_user_rate_limiting(): void
    {
        // First, create users to consume the limit
        for ($i = 0; $i < 2; $i++) {
            $response = $this->actingAs($this->adminUser, 'sanctum')
                ->postJson('/api/users', [
                    'name' => "Rate Limit User {$i}",
                    'email' => "ratelimit{$i}@example.com",
                    'password' => 'password',
                    'role' => 'member',
                ]);
            $response->assertStatus(201);
        }

        // The 3rd attempt should be rate limited
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/users', [
                'name' => "Rate Limit User 3",
                'email' => "ratelimit3@example.com",
                'password' => 'password',
                'role' => 'member',
            ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertArrayHasKey('Retry-After', $response->headers->all());
    }
}
