<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        $token = $this->generateToken($admin);

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'admin' => [
                    'id'    => $admin->id,
                    'email' => $admin->email,
                    'role'  => $admin->role,
                ],
            ],
        ]);
    }

    private function generateToken(Admin $admin): string
    {
        $secret = env('JWT_ADMIN_SECRET');
        $ttl    = (int) env('JWT_ADMIN_TTL', 60);

        $now     = time();
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub'  => $admin->id,
            'role' => $admin->role,
            'iat'  => $now,
            'exp'  => $now + ($ttl * 60),
        ]));
        $sig = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );

        return "$header.$payload.$sig";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
