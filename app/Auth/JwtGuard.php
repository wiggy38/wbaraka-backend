<?php

namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class JwtGuard implements Guard
{
    use GuardHelpers;

    private Request $request;
    private string  $secret;

    public function __construct(UserProvider $provider, Request $request, string $secret)
    {
        $this->provider = $provider;
        $this->request  = $request;
        $this->secret   = $secret;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->bearerToken();
        if (! $token) {
            return null;
        }

        $payload = $this->decodeJwt($token);
        if (! $payload) {
            return null;
        }

        $this->user = $this->provider->retrieveById($payload['sub']);

        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    // ──────────────────────────────────────────────────────────────
    //  JWT helpers (HS256 only, no package dependency)
    // ──────────────────────────────────────────────────────────────

    private function bearerToken(): ?string
    {
        $header = $this->request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $sig64] = $parts;

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', "$header64.$payload64", $this->secret, true)
        );

        if (! hash_equals($expected, $sig64)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payload64), true);
        if (! is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        if (empty($payload['sub'])) {
            return null;
        }

        return $payload;
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
