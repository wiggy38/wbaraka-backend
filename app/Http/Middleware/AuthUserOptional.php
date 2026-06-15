<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthUserOptional
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if ($user && $user->statut === 'actif') {
                $request->attributes->set('user', $user);
            }
        } catch (JWTException) {
            // Pas de token ou token invalide — on continue sans utilisateur
        }

        return $next($request);
    }
}
