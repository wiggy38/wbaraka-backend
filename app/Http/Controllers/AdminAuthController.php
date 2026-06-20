<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AdminAuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/admin/auth/login',
        summary: 'Authentification administrateur',
        tags: ['Admin Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@baraka.ml'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token JWT retourné',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'token', type: 'string'),
                            new OA\Property(property: 'admin', type: 'object'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
        ]
    )]
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
        $secret = config('services.jwt.admin_secret');
        $ttl    = (int) config('services.jwt.admin_ttl', 60);

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
