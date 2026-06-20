<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'Token manquant.'], 401);
        }

        $payload = $this->decodeJwt($token);

        if (! $payload) {
            return response()->json(['success' => false, 'message' => 'Token invalide ou expiré::.'], 401);
        }

        $admin = Admin::find($payload['sub']);

        if (! $admin) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $request->attributes->set('admin', $admin);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

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

        $secret   = config('services.jwt.admin_secret');

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', "$header64.$payload64", $secret, true)
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
