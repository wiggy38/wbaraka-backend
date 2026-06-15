<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AgentAuthController extends Controller
{
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
