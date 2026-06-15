<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilImfRequest;
use App\Models\Evenement;
use App\Models\Imf;
use App\Models\Offre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortailController extends Controller
{
    #[OA\Get(
        path: '/api/v1/portail/dashboard',
        summary: "Tableau de bord de l'IMF connectée",
        security: [['bearerAuth' => []]],
        tags: ['Portail'],
        responses: [
            new OA\Response(response: 200, description: 'Données du tableau de bord'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $idImf = $agent->id_imf;

        $offresActives      = Offre::where('id_imf', $idImf)->where('statut', 'actif')->count();
        $offresEnValidation = Offre::where('id_imf', $idImf)->where('statut', 'en_validation')->count();

        $offreIds = Offre::where('id_imf', $idImf)->pluck('id_offre');
        $since30j = now()->subDays(30);

        $vues30j        = Evenement::whereIn('id_offre', $offreIds)->where('type', 'vue')->where('created_at', '>=', $since30j)->count();
        $simulations30j = Evenement::whereIn('id_offre', $offreIds)->where('type', 'simulation')->where('created_at', '>=', $since30j)->count();

        $tauxConversion = $vues30j > 0 ? round($simulations30j / $vues30j * 100, 2) : 0;

        $imf              = Imf::find($idImf);
        $completionProfil = $this->calculerCompletionProfil($imf);

        return response()->json([
            'success' => true,
            'data'    => [
                'offres_actives'       => $offresActives,
                'offres_en_validation' => $offresEnValidation,
                'vues_30j'             => $vues30j,
                'simulations_30j'      => $simulations30j,
                'taux_conversion'      => $tauxConversion,
                'ads_actives'          => 0,
                'completion_profil'    => $completionProfil,
                'alertes'              => [],
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/portail/stats',
        summary: 'Stats globales IMF sur 30j, détaillées par offre',
        security: [['bearerAuth' => []]],
        tags: ['Portail'],
        responses: [
            new OA\Response(response: 200, description: 'Stats par offre'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $agent    = $request->attributes->get('agent');
        $idImf    = $agent->id_imf;
        $since30j = now()->subDays(30);

        $offres   = Offre::where('id_imf', $idImf)->get(['id_offre', 'nom_produit', 'statut']);
        $offreIds = $offres->pluck('id_offre');

        $evenements = DB::table('evenements')
            ->select('id_offre', 'type', DB::raw('COUNT(*) as total'))
            ->whereIn('id_offre', $offreIds)
            ->where('created_at', '>=', $since30j)
            ->groupBy('id_offre', 'type')
            ->get()
            ->groupBy('id_offre');

        $detailOffres = $offres->map(function ($offre) use ($evenements) {
            $evOffre     = $evenements->get($offre->id_offre, collect());
            $vues        = (int) ($evOffre->firstWhere('type', 'vue')?->total ?? 0);
            $simulations = (int) ($evOffre->firstWhere('type', 'simulation')?->total ?? 0);
            $clics       = (int) ($evOffre->firstWhere('type', 'clic')?->total ?? 0);

            return [
                'id_offre'        => $offre->id_offre,
                'nom_produit'     => $offre->nom_produit,
                'statut'          => $offre->statut,
                'vues'            => $vues,
                'simulations'     => $simulations,
                'clics'           => $clics,
                'taux_conversion' => $vues > 0 ? round($simulations / $vues * 100, 2) : 0,
            ];
        })->values();

        $totalVues        = $detailOffres->sum('vues');
        $totalSimulations = $detailOffres->sum('simulations');

        return response()->json([
            'success' => true,
            'data'    => [
                'periode'       => '30j',
                'totaux'        => [
                    'vues'            => $totalVues,
                    'simulations'     => $totalSimulations,
                    'clics'           => $detailOffres->sum('clics'),
                    'taux_conversion' => $totalVues > 0 ? round($totalSimulations / $totalVues * 100, 2) : 0,
                ],
                'detail_offres' => $detailOffres,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/portail/stats/offre/{id}',
        summary: "Stats d'une offre groupées par semaine sur 12 semaines",
        security: [['bearerAuth' => []]],
        tags: ['Portail'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Stats hebdomadaires'),
            new OA\Response(response: 403, description: 'Action non autorisée'),
            new OA\Response(response: 404, description: 'Offre introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function statsOffre(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $offre = Offre::find($id);

        if (! $offre) {
            return response()->json(['success' => false, 'error' => 'Offre introuvable.'], 404);
        }

        if ($offre->id_imf !== $agent->id_imf) {
            return response()->json(['success' => false, 'error' => 'Action non autorisée.'], 403);
        }

        $debutPeriode = now()->startOfWeek()->subWeeks(11);

        $evenements = DB::table('evenements')
            ->select(DB::raw("TO_CHAR(created_at, 'IYYYIW') as yw"), 'type', DB::raw('COUNT(*) as total'))
            ->where('id_offre', $offre->id_offre)
            ->where('created_at', '>=', $debutPeriode)
            ->groupBy('yw', 'type')
            ->get()
            ->groupBy('yw');

        $semaines = [];
        for ($i = 11; $i >= 0; $i--) {
            $debut = now()->startOfWeek()->subWeeks($i);
            $fin   = $debut->copy()->endOfWeek();
            $yw    = $debut->format('oW'); // IYYYIW equivalent: ISO year + ISO week

            $evSemaine   = $evenements->get($yw, collect());
            $semaines[] = [
                'semaine'     => $debut->format('Y') . '-W' . $debut->format('W'),
                'debut'       => $debut->toDateString(),
                'fin'         => $fin->toDateString(),
                'vues'        => (int) ($evSemaine->firstWhere('type', 'vue')?->total ?? 0),
                'simulations' => (int) ($evSemaine->firstWhere('type', 'simulation')?->total ?? 0),
                'clics'       => (int) ($evSemaine->firstWhere('type', 'clic')?->total ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'offre'    => ['id_offre' => $offre->id_offre, 'nom_produit' => $offre->nom_produit],
                'semaines' => $semaines,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/portail/stats/export',
        summary: 'Export CSV des stats sur 12 mois',
        security: [['bearerAuth' => []]],
        tags: ['Portail'],
        responses: [
            new OA\Response(response: 200, description: 'Fichier CSV'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function statsExport(Request $request): StreamedResponse
    {
        $agent    = $request->attributes->get('agent');
        $idImf    = $agent->id_imf;
        $since12m = now()->startOfMonth()->subMonths(11);

        $offreIds = Offre::where('id_imf', $idImf)->pluck('id_offre');

        $rows = DB::table('evenements as e')
            ->join('offres as o', 'o.id_offre', '=', 'e.id_offre')
            ->select(
                DB::raw("TO_CHAR(e.created_at, 'YYYY-MM') as mois"),
                'e.id_offre',
                'o.nom_produit',
                'e.type',
                DB::raw('COUNT(*) as total')
            )
            ->whereIn('e.id_offre', $offreIds)
            ->where('e.created_at', '>=', $since12m)
            ->groupBy('mois', 'e.id_offre', 'o.nom_produit', 'e.type')
            ->orderBy('mois')
            ->orderBy('o.nom_produit')
            ->get();

        // Pivot type columns
        $pivoted = [];
        foreach ($rows as $row) {
            $key = $row->mois . '|' . $row->id_offre;
            if (! isset($pivoted[$key])) {
                $pivoted[$key] = ['mois' => $row->mois, 'offre' => $row->nom_produit, 'vues' => 0, 'simulations' => 0, 'clics' => 0];
            }
            if ($row->type === 'vue')        $pivoted[$key]['vues']        = $row->total;
            if ($row->type === 'simulation') $pivoted[$key]['simulations'] = $row->total;
            if ($row->type === 'clic')       $pivoted[$key]['clics']       = $row->total;
        }

        $filename = 'stats_imf_' . now()->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($pivoted) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM pour ouverture correcte dans Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['mois', 'offre', 'vues', 'simulations', 'clics'], ';');
            foreach ($pivoted as $row) {
                fputcsv($out, [$row['mois'], $row['offre'], $row['vues'], $row['simulations'], $row['clics']], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    #[OA\Get(
        path: '/api/v1/portail/profil',
        summary: "Profil de l'IMF de l'agent connecté",
        security: [['bearerAuth' => []]],
        tags: ['Portail'],
        responses: [
            new OA\Response(response: 200, description: 'Profil IMF'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function profil(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $imf   = Imf::find($agent->id_imf);

        if (! $imf) {
            return response()->json(['success' => false, 'error' => 'IMF introuvable.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $imf->id,
                'nom'              => $imf->nom,
                'description'      => $imf->description,
                'zones_couverture' => $imf->zones_couverture,
                'email_contact'    => $imf->email_contact,
                'telephone'        => $imf->telephone,
                'logo_url'         => $imf->logo_url,
                'statut'           => $imf->statut,
                'completion_profil' => $this->calculerCompletionProfil($imf),
            ],
        ]);
    }

    #[OA\Put(
        path: '/api/v1/portail/profil',
        summary: "Mise à jour du profil de l'IMF",
        security: [['bearerAuth' => []]],
        tags: ['Portail'],
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 422, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function updateProfil(ProfilImfRequest $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $imf   = Imf::find($agent->id_imf);

        if (! $imf) {
            return response()->json(['success' => false, 'error' => 'IMF introuvable.'], 404);
        }

        $imf->update($request->only(['nom', 'description', 'zones_couverture', 'email_contact', 'telephone']));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $imf->id,
                'nom'              => $imf->nom,
                'description'      => $imf->description,
                'zones_couverture' => $imf->zones_couverture,
                'email_contact'    => $imf->email_contact,
                'telephone'        => $imf->telephone,
                'logo_url'         => $imf->logo_url,
                'statut'           => $imf->statut,
                'completion_profil' => $this->calculerCompletionProfil($imf),
            ],
        ]);
    }

    private function calculerCompletionProfil(?Imf $imf): int
    {
        if (! $imf) {
            return 0;
        }

        $champs  = ['nom', 'email_contact', 'telephone', 'logo_url', 'description', 'zones_couverture'];
        $remplis = 0;

        foreach ($champs as $champ) {
            if (! empty($imf->{$champ})) {
                $remplis++;
            }
        }

        return (int) round($remplis / count($champs) * 100);
    }
}
