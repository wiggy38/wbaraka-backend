<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthUser
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException) {
            return response()->json(['success' => false, 'message' => 'Token manquant ou invalide.'], 401);
        }

        if (! $user || $user->statut !== 'actif') {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $request->attributes->set('user', $user);

        return $next($request);
    }
}
