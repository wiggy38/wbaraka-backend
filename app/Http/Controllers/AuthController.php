<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private OtpService $otpService) {}

    #[OA\Post(
        path: '/api/v1/auth/otp/request',
        summary: 'Demander un OTP par SMS',
        tags: ['Authentification'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['telephone'],
                properties: [
                    new OA\Property(property: 'telephone', type: 'string', example: '+22370000000'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP envoyé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'telephone' => ['required', 'string'],
        ]);

        $this->otpService->generateAndSend($request->telephone);

        return response()->json(['success' => true]);
    }

    #[OA\Post(
        path: '/api/v1/auth/otp/verify',
        summary: "Vérifier l'OTP et obtenir un token JWT",
        tags: ['Authentification'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['telephone', 'code'],
                properties: [
                    new OA\Property(property: 'telephone', type: 'string', example: '+22370000000'),
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
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
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                                new OA\Property(property: 'user', type: 'object'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Code invalide ou expiré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Code invalide ou expiré.'),
                    ]
                )
            ),
        ]
    )]
    #[OA\Put(
        path: '/api/v1/auth/me/nom',
        summary: "Mettre à jour le nom de l'utilisateur connecté",
        security: [['bearerAuth' => []]],
        tags: ['Authentification'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Amadou Koné'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nom mis à jour avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function updateNom(Request $request): JsonResponse
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
        ]);

        $user = JWTAuth::parseToken()->authenticate();
        $user->update(['nom' => $request->nom]);

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'telephone' => ['required', 'string'],
            'code'      => ['required', 'string', 'size:6'],
        ]);

        if (! $this->otpService->verify($request->telephone, $request->code)) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré.',
            ], 401);
        }

        $user = User::firstOrCreate(
            ['telephone' => $request->telephone],
            ['statut' => 'actif'],
        );

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => $user,
            ],
        ]);
    }
}
