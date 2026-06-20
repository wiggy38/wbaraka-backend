<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateImfRequest;
use App\Http\Requests\RejeterOffreRequest;
use App\Models\Agent;
use App\Models\Imf;
use App\Models\JournalAdmin;
use App\Models\Offre;
use App\Models\Slider;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;

class AdminController extends Controller
{
    public function __construct(private JournalService $journal) {}

    #[OA\Get(
        path: '/api/v1/admin/dashboard',
        summary: "Tableau de bord de l'administration",
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Données du tableau de bord admin'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $imfsParStatut = Imf::selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $offresParStatut = Offre::selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $offresEnAttente = $offresParStatut['en_validation'] ?? 0;

        $seuil48h = now()->subHours(48);
        $alertesUrgentes = Offre::where('statut', 'en_validation')
            ->where('date_mise_a_jour', '<=', $seuil48h)
            ->get(['id_offre', 'nom_produit', 'id_imf', 'date_mise_a_jour'])
            ->map(fn($offre) => [
                'id_offre'    => $offre->id_offre,
                'nom_produit' => $offre->nom_produit,
                'id_imf'      => $offre->id_imf,
                'en_attente_depuis' => $offre->date_mise_a_jour?->toIso8601String(),
                'heures_attente'    => (int) $offre->date_mise_a_jour?->diffInHours(now()),
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'imfs'                => [
                    'par_statut' => $imfsParStatut,
                    'total'      => array_sum($imfsParStatut),
                ],
                'offres'              => [
                    'par_statut' => $offresParStatut,
                    'total'      => array_sum($offresParStatut),
                ],
                'offres_en_attente'   => $offresEnAttente,
                'alertes_urgentes'    => $alertesUrgentes,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/admin/moderation/offres',
        summary: 'Liste paginée des offres en attente de validation',
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des offres en_validation'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function indexOffresModeration(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $offres = Offre::with('imf:id,nom')
            ->where('statut', 'en_validation')
            ->orderBy('date_mise_a_jour', 'asc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $offres,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/moderation/offres/{id}/approuver',
        summary: "Approuver une offre (passe en statut actif)",
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Offre approuvée'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
            new OA\Response(response: 422, description: "L'offre n'est pas en attente de validation"),
        ]
    )]
    public function approuverOffre(Request $request, string $id): JsonResponse
    {
        $offre = Offre::where('id_offre', $id)->firstOrFail();

        if ($offre->statut !== 'en_validation') {
            return response()->json([
                'success' => false,
                'message' => "Seules les offres en statut 'en_validation' peuvent être approuvées.",
            ], 422);
        }

        $offre->update([
            'statut'          => 'actif',
            'motif_rejet'     => null,
            'date_mise_a_jour' => now(),
        ]);

        $this->journal->log(
            $request->attributes->get('admin'),
            'approuver_offre',
            'offre',
            $offre->id_offre,
            ['nom_produit' => $offre->nom_produit, 'id_imf' => $offre->id_imf]
        );

        return response()->json([
            'success' => true,
            'message' => 'Offre approuvée avec succès.',
            'data'    => ['id_offre' => $offre->id_offre, 'statut' => $offre->statut],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/moderation/offres/{id}/rejeter',
        summary: "Rejeter une offre (repasse en brouillon avec motif)",
        security: [['bearerAuth' => []]],
        tags: ['Admin'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['motif'],
                properties: [
                    new OA\Property(property: 'motif', type: 'string', minLength: 10, maxLength: 1000),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Offre rejetée'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
            new OA\Response(response: 422, description: "L'offre n'est pas en attente de validation ou motif manquant"),
        ]
    )]
    public function rejeterOffre(RejeterOffreRequest $request, string $id): JsonResponse
    {
        $offre = Offre::where('id_offre', $id)->firstOrFail();

        if ($offre->statut !== 'en_validation') {
            return response()->json([
                'success' => false,
                'message' => "Seules les offres en statut 'en_validation' peuvent être rejetées.",
            ], 422);
        }

        $offre->update([
            'statut'           => 'brouillon',
            'motif_rejet'      => $request->validated()['motif'],
            'date_mise_a_jour' => now(),
        ]);

        $this->journal->log(
            $request->attributes->get('admin'),
            'rejeter_offre',
            'offre',
            $offre->id_offre,
            ['nom_produit' => $offre->nom_produit, 'id_imf' => $offre->id_imf, 'motif' => $offre->motif_rejet]
        );

        return response()->json([
            'success' => true,
            'message' => 'Offre rejetée et repassée en brouillon.',
            'data'    => ['id_offre' => $offre->id_offre, 'statut' => $offre->statut],
        ]);
    }

    // ─── Gestion des IMFs ───────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/imfs',
        summary: 'Liste paginée des IMFs, filtrable par statut',
        security: [['bearerAuth' => []]],
        tags: ['Admin - IMFs'],
        parameters: [
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['actif', 'suspendu'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des IMFs'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function indexImfs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = Imf::withCount(['agents', 'offres']);

        if ($statut = $request->query('statut')) {
            $query->where('statut', $statut);
        }

        $imfs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $imfs,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/imfs',
        summary: "Créer une IMF et son premier agent admin_imf",
        security: [['bearerAuth' => []]],
        tags: ['Admin - IMFs'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'email_contact', 'zones_couverture', 'agent_nom', 'agent_email', 'agent_password'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'email_contact', type: 'string', format: 'email'),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'zones_couverture', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'logo_url', type: 'string', nullable: true),
                    new OA\Property(property: 'agent_nom', type: 'string'),
                    new OA\Property(property: 'agent_email', type: 'string', format: 'email'),
                    new OA\Property(property: 'agent_password', type: 'string', minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'IMF créée'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function createImf(CreateImfRequest $request): JsonResponse
    {
        $data = $request->validated();

        $imf = Imf::create([
            'nom'              => $data['nom'],
            'email_contact'    => $data['email_contact'],
            'telephone'        => $data['telephone'] ?? null,
            'description'      => $data['description'] ?? null,
            'zones_couverture' => $data['zones_couverture'],
            'logo_url'         => $data['logo_url'] ?? null,
            'statut'           => 'actif',
        ]);

        $agent = Agent::create([
            'id_imf'   => $imf->id,
            'nom'      => $data['agent_nom'],
            'email'    => $data['agent_email'],
            'password' => Hash::make($data['agent_password']),
            'role'     => 'admin_imf',
            'statut'   => 'actif',
        ]);

        $this->envoyerEmailBienvenue($imf, $agent, $data['agent_password']);

        $this->journal->log(
            $request->attributes->get('admin'),
            'creer_imf',
            'imf',
            $imf->id,
            ['nom' => $imf->nom, 'agent_email' => $agent->email]
        );

        return response()->json([
            'success' => true,
            'message' => 'IMF créée avec succès.',
            'data'    => [
                'imf'   => $imf,
                'agent' => $agent,
            ],
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/admin/imfs/{id}/suspendre',
        summary: "Suspendre une IMF et dépublier toutes ses offres actives",
        security: [['bearerAuth' => []]],
        tags: ['Admin - IMFs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'IMF suspendue'),
            new OA\Response(response: 404, description: 'IMF introuvable'),
            new OA\Response(response: 422, description: "L'IMF est déjà suspendue"),
        ]
    )]
    public function suspendrImf(Request $request, string $id): JsonResponse
    {
        $imf = Imf::findOrFail($id);

        if ($imf->statut === 'suspendu') {
            return response()->json([
                'success' => false,
                'message' => "L'IMF est déjà suspendue.",
            ], 422);
        }

        $imf->update(['statut' => 'suspendu']);

        $offresDesactivees = Offre::where('id_imf', $imf->id)
            ->where('statut', 'actif')
            ->count();

        Offre::where('id_imf', $imf->id)
            ->where('statut', 'actif')
            ->update(['statut' => 'inactif', 'date_mise_a_jour' => now()]);

        $this->journal->log(
            $request->attributes->get('admin'),
            'suspendre_imf',
            'imf',
            $imf->id,
            ['nom' => $imf->nom, 'offres_desactivees' => $offresDesactivees]
        );

        return response()->json([
            'success' => true,
            'message' => 'IMF suspendue. ' . $offresDesactivees . ' offre(s) dépubliée(s).',
            'data'    => ['id' => $imf->id, 'statut' => $imf->statut, 'offres_desactivees' => $offresDesactivees],
        ]);
    }

    #[OA\Put(
        path: '/api/v1/admin/imfs/{id}/reactiver',
        summary: "Réactiver une IMF suspendue",
        security: [['bearerAuth' => []]],
        tags: ['Admin - IMFs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'IMF réactivée'),
            new OA\Response(response: 404, description: 'IMF introuvable'),
            new OA\Response(response: 422, description: "L'IMF est déjà active"),
        ]
    )]
    public function reactiverImf(Request $request, string $id): JsonResponse
    {
        $imf = Imf::findOrFail($id);

        if ($imf->statut === 'actif') {
            return response()->json([
                'success' => false,
                'message' => "L'IMF est déjà active.",
            ], 422);
        }

        $imf->update(['statut' => 'actif']);

        $this->journal->log(
            $request->attributes->get('admin'),
            'reactiver_imf',
            'imf',
            $imf->id,
            ['nom' => $imf->nom]
        );

        return response()->json([
            'success' => true,
            'message' => 'IMF réactivée avec succès.',
            'data'    => ['id' => $imf->id, 'statut' => $imf->statut],
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/admin/imfs/{id}',
        summary: "Supprimer (soft delete) une IMF — super_admin uniquement",
        security: [['bearerAuth' => []]],
        tags: ['Admin - IMFs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'IMF supprimée'),
            new OA\Response(response: 403, description: 'Réservé au super_admin'),
            new OA\Response(response: 404, description: 'IMF introuvable'),
        ]
    )]
    public function supprimerImf(Request $request, string $id): JsonResponse
    {
        $admin = $request->attributes->get('admin');

        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée au super administrateur.',
            ], 403);
        }

        $imf = Imf::findOrFail($id);

        $this->journal->log(
            $admin,
            'supprimer_imf',
            'imf',
            $imf->id,
            ['nom' => $imf->nom, 'statut' => $imf->statut]
        );

        $imf->delete();

        return response()->json([
            'success' => true,
            'message' => 'IMF supprimée.',
        ]);
    }

    // ─── Journal d'administration ───────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/journal',
        summary: "Liste paginée du journal d'administration — super_admin uniquement",
        security: [['bearerAuth' => []]],
        tags: ['Admin - Journal'],
        parameters: [
            new OA\Parameter(name: 'id_admin', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'action', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Journal des actions admin'),
            new OA\Response(response: 403, description: 'Réservé au super_admin'),
        ]
    )]
    public function indexJournal(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('admin');

        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée au super administrateur.',
            ], 403);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = JournalAdmin::with('admin:id,email,role')
            ->orderBy('created_at', 'desc');

        if ($idAdmin = $request->query('id_admin')) {
            $query->where('id_admin', $idAdmin);
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    // ─── Slider ─────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/admin/slider',
        summary: 'Liste des slides du carousel — super_admin uniquement',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Slider'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des 3 slides'),
            new OA\Response(response: 403, description: 'Réservé au super_admin'),
        ]
    )]
    public function indexSlider(Request $request): JsonResponse
    {
        //$admin = $request->attributes->get('admin');

        //if (!$admin || $admin->role !== 'super_admin') {
        //    return response()->json([
        //        'success' => false,
        //        'message' => 'Action réservée au super administrateur.',
        //    ], 403);
        //}

        $slides = Slider::orderBy('ordre')->take(3)->get();

        return response()->json([
            'success' => true,
            'data'    => $slides,
        ]);
    }

    #[OA\Put(
        path: '/api/v1/admin/slider/{id}',
        summary: 'Mettre à jour un slide — super_admin uniquement',
        security: [['bearerAuth' => []]],
        tags: ['Admin - Slider'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'ordre', type: 'integer', minimum: 1, maximum: 3),
                    new OA\Property(property: 'image_url', type: 'string'),
                    new OA\Property(property: 'titre', type: 'string', nullable: true),
                    new OA\Property(property: 'lien', type: 'string', nullable: true),
                    new OA\Property(property: 'actif', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Slide mis à jour'),
            new OA\Response(response: 403, description: 'Réservé au super_admin'),
            new OA\Response(response: 404, description: 'Slide introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function updateSlider(Request $request, string $id): JsonResponse
    {
        $admin = $request->attributes->get('admin');

        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action réservée au super administrateur.',
            ], 403);
        }

        $slider = Slider::findOrFail($id);

        $validated = $request->validate([
            'ordre'     => 'sometimes|integer|min:1|max:3',
            'image_url' => 'sometimes|string|url',
            'titre'     => 'sometimes|nullable|string|max:255',
            'lien'      => 'sometimes|nullable|string|url',
            'actif'     => 'sometimes|boolean',
        ]);

        $slider->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Slide mis à jour.',
            'data'    => $slider,
        ]);
    }

    private function envoyerEmailBienvenue(Imf $imf, Agent $agent, string $motDePasse): void
    {
        try {
            Mail::raw(
                "Bienvenue sur Baraka !\n\n"
                . "Votre IMF « {$imf->nom} » a été créée.\n\n"
                . "Identifiants de votre compte administrateur :\n"
                . "  Email    : {$agent->email}\n"
                . "  Mot de passe : {$motDePasse}\n\n"
                . "Veuillez vous connecter et changer votre mot de passe dès que possible.",
                function ($message) use ($agent, $imf) {
                    $message->to($agent->email, $agent->nom)
                            ->subject("Bienvenue sur Baraka — {$imf->nom}");
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Email de bienvenue IMF non envoyé.', [
                'imf_id'      => $imf->id,
                'agent_email' => $agent->email,
                'erreur'      => $e->getMessage(),
            ]);
        }
    }
}
