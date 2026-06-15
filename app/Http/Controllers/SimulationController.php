<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSimulationRequest;
use App\Models\Simulation;
use App\Services\AmortissementService;
use App\Services\TEGService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class SimulationController extends Controller
{
    public function __construct(
        private AmortissementService $amortissementService,
        private TEGService $tegService,
    ) {}

    #[OA\Post(
        path: '/api/v1/simulations',
        summary: 'Créer une simulation de crédit',
        tags: ['Simulations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['montant_emprunte', 'duree_mois', 'taux_utilise'],
                properties: [
                    new OA\Property(property: 'montant_emprunte', type: 'integer', example: 500000),
                    new OA\Property(property: 'duree_mois', type: 'integer', example: 12),
                    new OA\Property(property: 'taux_utilise', type: 'number', format: 'float', example: 2.5),
                    new OA\Property(property: 'id_offre', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'frais_dossier', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'assurance', type: 'number', format: 'float', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Simulation créée'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(StoreSimulationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $montant   = (float) $data['montant_emprunte'];
        $duree     = (int)   $data['duree_mois'];
        $tauxPct   = (float) $data['taux_utilise'];
        $frais     = (float) ($data['frais_dossier'] ?? 0);
        $assurance = (float) ($data['assurance'] ?? 0);

        $tauxMensuel = $tauxPct / 100;

        $amortissement = $this->amortissementService->calculer($montant, $tauxMensuel, $duree, $frais, $assurance);
        $teg           = $this->tegService->calculer($montant, $amortissement['mensualite'], $duree, $frais);

        $user = null;
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException) {
            // Anonymous simulation
        }

        $simulation = Simulation::create([
            'id_utilisateur'        => $user?->id,
            'id_offre'              => $data['id_offre'] ?? null,
            'montant_emprunte'      => (int) $montant,
            'duree_mois'            => $duree,
            'taux_utilise'          => $tauxPct,
            'cout_total'            => (int) $amortissement['cout_total'],
            'mensualite'            => $amortissement['mensualite'],
            'tableau_amortissement' => $amortissement['tableau_amortissement'],
            'date_creation'         => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => array_merge($simulation->toArray(), [
                'montant_net' => $amortissement['montant_net'],
                'teg'         => round($teg, 6),
            ]),
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/simulations/preview',
        summary: 'Calculer une simulation sans persistance',
        tags: ['Simulations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['montant_emprunte', 'duree_mois', 'taux_utilise'],
                properties: [
                    new OA\Property(property: 'montant_emprunte', type: 'integer', example: 500000),
                    new OA\Property(property: 'duree_mois', type: 'integer', example: 24),
                    new OA\Property(property: 'taux_utilise', type: 'number', format: 'float', example: 2.5),
                    new OA\Property(property: 'id_offre', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'frais_dossier', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'assurance', type: 'number', format: 'float', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Résultat de simulation (non persisté)'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function preview(StoreSimulationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $montant   = (float) $data['montant_emprunte'];
        $duree     = (int)   $data['duree_mois'];
        $tauxPct   = (float) $data['taux_utilise'];
        $frais     = (float) ($data['frais_dossier'] ?? 0);
        $assurance = (float) ($data['assurance'] ?? 0);

        $tauxMensuel   = $tauxPct / 100;
        $amortissement = $this->amortissementService->calculer($montant, $tauxMensuel, $duree, $frais, $assurance);
        $teg           = $this->tegService->calculer($montant, $amortissement['mensualite'], $duree, $frais);

        return response()->json([
            'success' => true,
            'data'    => [
                'montant_emprunte'      => (int) $montant,
                'duree_mois'            => $duree,
                'taux_utilise'          => $tauxPct,
                'montant_net'           => $amortissement['montant_net'],
                'mensualite'            => $amortissement['mensualite'],
                'cout_total'            => (int) $amortissement['cout_total'],
                'teg'                   => round($teg, 6),
                'tableau_amortissement' => $amortissement['tableau_amortissement'],
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/simulations/{id}',
        summary: 'Afficher une simulation',
        tags: ['Simulations'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de la simulation'),
            new OA\Response(response: 403, description: 'Accès non autorisé'),
            new OA\Response(response: 404, description: 'Simulation introuvable'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $simulation = Simulation::find($id);

        if (! $simulation) {
            return response()->json(['success' => false, 'error' => 'Simulation introuvable.'], 404);
        }

        if ($simulation->id_utilisateur !== null) {
            $user = null;
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (JWTException) {}

            if (! $user || $user->id !== $simulation->id_utilisateur) {
                return response()->json(['success' => false, 'error' => 'Accès non autorisé.'], 403);
            }
        }

        return response()->json(['success' => true, 'data' => $simulation]);
    }

    #[OA\Get(
        path: '/api/v1/users/me/simulations',
        summary: 'Lister les simulations de l\'utilisateur authentifié',
        security: [['bearerAuth' => []]],
        tags: ['Simulations'],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des simulations'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function mySimulations(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user');

        $simulations = Simulation::where('id_utilisateur', $user->id)
            ->orderByDesc('date_creation')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $simulations]);
    }
}
