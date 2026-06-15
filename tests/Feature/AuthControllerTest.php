<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\AfricasTalkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $telephone = '+22370000000';

    // -------------------------------------------------------------------------
    // Test 1 : POST /api/v1/auth/otp/request → 200 + enregistre l'OTP en DB
    // -------------------------------------------------------------------------

    public function test_otp_request_returns_success_and_persists_otp(): void
    {
        $this->mock(AfricasTalkingService::class)
            ->shouldReceive('sendSms')
            ->once()
            ->andReturn([]);

        Redis::shouldReceive('setex')
            ->once()
            ->with(\Mockery::pattern('/^otp:/'), 300, \Mockery::type('string'))
            ->andReturn(true);

        $response = $this->postJson('/api/v1/auth/otp/request', [
            'telephone' => $this->telephone,
        ]);

        $response->assertStatus(200)
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseHas('otps', [
            'telephone' => $this->telephone,
            'used'      => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 2a : POST /api/v1/auth/otp/verify — code trop court → 422 (validation)
    // -------------------------------------------------------------------------

    public function test_otp_verify_with_invalid_format_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'telephone' => $this->telephone,
            'code'      => '123',   // size:6 non respecté
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    // -------------------------------------------------------------------------
    // Test 2b : POST /api/v1/auth/otp/verify — code correct en forme mais faux → 401
    // -------------------------------------------------------------------------

    public function test_otp_verify_with_wrong_code_returns_401(): void
    {
        Redis::shouldReceive('get')
            ->once()
            ->with("otp:{$this->telephone}")
            ->andReturn('123456');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'telephone' => $this->telephone,
            'code'      => '999999',   // mauvais code
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Code invalide ou expiré.']);
    }

    // -------------------------------------------------------------------------
    // Test 3 : POST /api/v1/auth/otp/verify — bon code → crée User + token JWT
    // -------------------------------------------------------------------------

    public function test_otp_verify_with_correct_code_creates_user_and_returns_jwt(): void
    {
        $code = '482917';

        Otp::create([
            'telephone'  => $this->telephone,
            'code'       => $code,
            'expires_at' => now()->addMinutes(5),
            'used'       => false,
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->with("otp:{$this->telephone}")
            ->andReturn($code);

        Redis::shouldReceive('del')
            ->once()
            ->with("otp:{$this->telephone}")
            ->andReturn(1);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'telephone' => $this->telephone,
            'code'      => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'user'],
            ]);

        $this->assertDatabaseHas('users', [
            'telephone' => $this->telephone,
            'statut'    => 'actif',
        ]);

        $this->assertNotEmpty($response->json('data.token'));

        // Le token doit avoir la structure JWT (3 segments séparés par ".")
        $this->assertCount(3, explode('.', $response->json('data.token')));
    }
}
