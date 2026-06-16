<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AgentAuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/portail/auth/login',
        summary: 'Authentification agent IMF (portail)',
        tags: ['Portail Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'agent@imf.ml'),
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
                            new OA\Property(property: 'agent', type: 'object'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
            new OA\Response(response: 403, description: 'Compte inactif'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $agent = Agent::where('email', $request->email)->first();

        if (! $agent || ! Hash::check($request->password, $agent->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        if ($agent->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Compte inactif.',
            ], 403);
        }

        $token = $this->generateToken($agent);

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'agent' => [
                    'id'     => $agent->id,
                    'id_imf' => $agent->id_imf,
                    'nom'    => $agent->nom,
                    'email'  => $agent->email,
                    'role'   => $agent->role,
                    'statut' => $agent->statut,
                ],
            ],
        ]);
    }

    private function generateToken(Agent $agent): string
    {
        $secret = env('JWT_PORTAIL_SECRET');
        $ttl    = (int) env('JWT_PORTAIL_TTL', 60); // minutes

        $now     = time();
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub'    => $agent->id,
            'id_imf' => $agent->id_imf,
            'iat'    => $now,
            'exp'    => $now + ($ttl * 60),
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
