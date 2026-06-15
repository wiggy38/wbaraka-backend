<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Baraka API',
    version: '1.0.0',
    description: 'API backend pour la plateforme Baraka de microcrédit'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Serveur local'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token JWT obtenu via POST /api/auth/otp/verify'
)]
class ApiDoc
{
}
