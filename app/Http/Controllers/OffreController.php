<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOffreRequest;
use App\Http\Requests\UpdateOffreRequest;
use App\Models\Offre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class OffreController extends Controller
{
    #[OA\Get(
        path: '/api/offres',
        summary: 'Lister les offres (paginées)',
        security: [['bearerAuth' => []]],
        tags: ['Offres'],
        parameters: [
            new OA\Parameter(name: 'id_imf', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['brouillon', 'publié', 'archivé'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des offres'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Offre::query();

        if ($request->filled('id_imf')) {
            $query->where('id_imf', $request->query('id_imf'));
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }

        $offres = $query->paginate(20);

        return response()->json(['success' => true, 'data' => $offres]);
    }

    #[OA\Get(
        path: '/api/offres/{id}',
        summary: 'Afficher une offre',
        security: [['bearerAuth' => []]],
        tags: ['Offres'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: "Détails de l'offre"),
            new OA\Response(response: 404, description: 'Offre introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $offre = Offre::find($id);

        if (! $offre) {
            return response()->json(['success' => false, 'error' => 'Offre introuvable.'], 404);
        }

        return response()->json(['success' => true, 'data' => $offre]);
    }

    #[OA\Get(
        path: '/api/offres/{id}/simulation-params',
        summary: 'Paramètres pré-remplis pour le simulateur à partir d\'une offre',
        tags: ['Offres'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paramètres de simulation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id_offre', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'montant_min', type: 'integer', example: 50000),
                        new OA\Property(property: 'montant_max', type: 'integer', example: 5000000),
                        new OA\Property(property: 'duree_min_mois', type: 'integer', example: 3),
                        new OA\Property(property: 'duree_max_mois', type: 'integer', example: 24),
                        new OA\Property(property: 'taux_interet_mensuel', type: 'number', format: 'float', example: 2.5),
                        new OA\Property(property: 'frais_dossier', type: 'number', format: 'float', nullable: true, example: 5000),
                        new OA\Property(property: 'assurance', type: 'number', format: 'float', nullable: true, example: 1000),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Offre introuvable'),
        ]
    )]
    public function simulationParams(string $id): JsonResponse
    {
        $offre = Offre::find($id);

        if (! $offre) {
            return response()->json(['success' => false, 'error' => 'Offre introuvable.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id_offre'            => $offre->id_offre,
                'montant_min'         => $offre->montant_min,
                'montant_max'         => $offre->montant_max,
                'duree_min_mois'      => $offre->duree_min_mois,
                'duree_max_mois'      => $offre->duree_max_mois,
                'taux_interet_mensuel' => $offre->taux_interet_mensuel,
                'frais_dossier'       => $offre->frais_dossier,
                'assurance'           => $offre->assurance,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/offres',
        summary: 'Créer une nouvelle offre',
        security: [['bearerAuth' => []]],
        tags: ['Offres'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom_produit', 'taux_interet_mensuel', 'montant_min', 'montant_max', 'duree_min_mois', 'duree_max_mois', 'garantie_requise', 'delai_traitement_jours', 'zones_couverture'],
                properties: [
                    new OA\Property(property: 'nom_produit', type: 'string', example: 'Crédit PME'),
                    new OA\Property(property: 'taux_interet_mensuel', type: 'number', format: 'float', example: 2.5),
                    new OA\Property(property: 'montant_min', type: 'integer', example: 50000),
                    new OA\Property(property: 'montant_max', type: 'integer', example: 5000000),
                    new OA\Property(property: 'duree_min_mois', type: 'integer', example: 3),
                    new OA\Property(property: 'duree_max_mois', type: 'integer', example: 24),
                    new OA\Property(property: 'frais_dossier', type: 'number', format: 'float', example: 5000, nullable: true),
                    new OA\Property(property: 'assurance', type: 'number', format: 'float', example: 1000, nullable: true),
                    new OA\Property(property: 'garantie_requise', type: 'string', enum: ['aucune', 'caution', 'neant', 'bien'], example: 'caution'),
                    new OA\Property(property: 'delai_traitement_jours', type: 'integer', example: 7),
                    new OA\Property(property: 'cible_specifique', type: 'array', items: new OA\Items(type: 'string'), example: ['femmes', 'jeunes'], nullable: true),
                    new OA\Property(property: 'zones_couverture', type: 'array', items: new OA\Items(type: 'string'), example: ['Bamako', 'Kayes']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Offre créée'),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(StoreOffreRequest $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $offre = Offre::create(array_merge($request->validated(), [
            'id_imf'          => $agent->id_imf,
            'statut'          => 'brouillon',
            'date_mise_a_jour' => now(),
        ]));

        return response()->json(['success' => true, 'data' => $offre], 201);
    }

    #[OA\Put(
        path: '/api/offres/{id}',
        summary: 'Mettre à jour une offre',
        security: [['bearerAuth' => []]],
        tags: ['Offres'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom_produit', type: 'string'),
                    new OA\Property(property: 'taux_interet_mensuel', type: 'number', format: 'float'),
                    new OA\Property(property: 'montant_min', type: 'integer'),
                    new OA\Property(property: 'montant_max', type: 'integer'),
                    new OA\Property(property: 'duree_min_mois', type: 'integer'),
                    new OA\Property(property: 'duree_max_mois', type: 'integer'),
                    new OA\Property(property: 'frais_dossier', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'assurance', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'garantie_requise', type: 'string', enum: ['aucune', 'caution', 'neant', 'bien']),
                    new OA\Property(property: 'delai_traitement_jours', type: 'integer'),
                    new OA\Property(property: 'cible_specifique', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                    new OA\Property(property: 'zones_couverture', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Offre mise à jour'),
            new OA\Response(response: 403, description: 'Action non autorisée'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function update(UpdateOffreRequest $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $offre = Offre::find($id);

        if (! $offre) {
            return response()->json(['success' => false, 'error' => 'Offre introuvable.'], 404);
        }

        if ($offre->id_imf !== $agent->id_imf) {
            return response()->json(['success' => false, 'error' => 'Action non autorisée.'], 403);
        }

        $offre->update(array_merge($request->validated(), [
            'date_mise_a_jour' => now(),
        ]));

        return response()->json(['success' => true, 'data' => $offre]);
    }

    #[OA\Delete(
        path: '/api/offres/{id}',
        summary: 'Supprimer une offre',
        security: [['bearerAuth' => []]],
        tags: ['Offres'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offre supprimée'),
            new OA\Response(response: 403, description: 'Action non autorisée'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $offre = Offre::find($id);

        if (! $offre) {
            return response()->json(['success' => false, 'error' => 'Offre introuvable.'], 404);
        }

        if ($offre->id_imf !== $agent->id_imf) {
            return response()->json(['success' => false, 'error' => 'Action non autorisée.'], 403);
        }

        $offre->delete();

        return response()->json(['success' => true, 'data' => null]);
    }
}
