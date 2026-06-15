<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests Feature pour POST /api/v1/simulations/preview.
 *
 * L'endpoint est public (pas d'authentification requise) et ne persiste rien.
 * On vérifie :
 *   1. Structure complète de la réponse JSON
 *   2. Temps de réponse < 500 ms
 *   3. Cohérence des valeurs calculées (TEG > 0, tableaux, types)
 *   4. Rejet des payloads invalides (422)
 */
class SimulationPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/simulations/preview';

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'montant_emprunte' => 500_000,
            'duree_mois'       => 12,
            'taux_utilise'     => 2.5,   // 2,5 % / mois (en %)
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Test 1 : réponse 200 et structure JSON complète
    // -------------------------------------------------------------------------

    public function test_preview_retourne_200_et_structure_json_correcte(): void
    {
        $response = $this->postJson(self::URL, $this->payload());

        $response->assertStatus(200);

        // Enveloppe
        $response->assertJsonStructure([
            'success',
            'data' => [
                'montant_emprunte',
                'duree_mois',
                'taux_utilise',
                'montant_net',
                'mensualite',
                'cout_total',
                'teg',
                'tableau_amortissement',
            ],
        ]);

        $data = $response->json('data');

        // success = true
        $this->assertTrue($response->json('success'));

        // Types de base
        $this->assertIsInt($data['montant_emprunte']);
        $this->assertIsInt($data['duree_mois']);
        $this->assertIsNumeric($data['taux_utilise']);
        $this->assertIsNumeric($data['montant_net']);
        $this->assertIsInt($data['mensualite']);
        $this->assertIsInt($data['cout_total']);
        $this->assertIsFloat($data['teg']);
        $this->assertIsArray($data['tableau_amortissement']);
    }

    // -------------------------------------------------------------------------
    // Test 2 : temps de réponse < 500 ms
    // -------------------------------------------------------------------------

    public function test_preview_repond_en_moins_de_500ms(): void
    {
        $debut = microtime(true);

        $this->postJson(self::URL, $this->payload())->assertStatus(200);

        $dureeMs = (microtime(true) - $debut) * 1000;

        $this->assertLessThan(
            500,
            $dureeMs,
            sprintf('Le endpoint preview a mis %.1f ms (seuil : 500 ms).', $dureeMs)
        );
    }

    // -------------------------------------------------------------------------
    // Test 3 : cohérence des valeurs retournées
    // -------------------------------------------------------------------------

    public function test_preview_valeurs_coherentes(): void
    {
        $response = $this->postJson(self::URL, $this->payload([
            'montant_emprunte' => 500_000,
            'duree_mois'       => 12,
            'taux_utilise'     => 2.5,
            'frais_dossier'    => 15_000,
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        // montant_net = montant − frais
        $this->assertEquals(500_000 - 15_000, $data['montant_net']);

        // mensualite > 0
        $this->assertGreaterThan(0, $data['mensualite']);

        // cout_total > montant_emprunte (il y a des intérêts et des frais)
        $this->assertGreaterThan($data['montant_emprunte'], $data['cout_total']);

        // TEG > 0 (il y a un taux d'intérêt non nul)
        $this->assertGreaterThan(0.0, $data['teg']);

        // TEG arrondi à 6 décimales
        $this->assertEquals(
            round($data['teg'], 6),
            $data['teg'],
            'teg doit être arrondi à 6 décimales.'
        );

        // Tableau : exactement 12 lignes pour duree_mois = 12
        $this->assertCount(12, $data['tableau_amortissement']);
    }

    // -------------------------------------------------------------------------
    // Test 4 : structure de chaque ligne du tableau d'amortissement
    // -------------------------------------------------------------------------

    public function test_preview_tableau_amortissement_structure_par_ligne(): void
    {
        $response = $this->postJson(self::URL, $this->payload([
            'duree_mois' => 6,
        ]));

        $response->assertStatus(200);

        $tableau = $response->json('data.tableau_amortissement');

        $this->assertCount(6, $tableau);

        foreach ($tableau as $i => $ligne) {
            $this->assertArrayHasKey('mois', $ligne, "Ligne $i : clé 'mois' manquante.");
            $this->assertArrayHasKey('capital_restant_debut', $ligne);
            $this->assertArrayHasKey('interet', $ligne);
            $this->assertArrayHasKey('capital_rembourse', $ligne);
            $this->assertArrayHasKey('assurance_mois', $ligne);
            $this->assertArrayHasKey('mensualite', $ligne);
            $this->assertArrayHasKey('capital_restant_fin', $ligne);

            // Numérotation séquentielle
            $this->assertEquals($i + 1, $ligne['mois']);

            // Capital restant fin non négatif
            $this->assertGreaterThanOrEqual(0, $ligne['capital_restant_fin']);
        }

        // Dernier mois : capital restant = 0
        $this->assertEquals(0, end($tableau)['capital_restant_fin']);
    }

    // -------------------------------------------------------------------------
    // Test 5 : avec assurance mensuelle — mensualite inclut l'assurance
    // -------------------------------------------------------------------------

    public function test_preview_avec_assurance_mensualite_plus_elevee(): void
    {
        $sanAssurance  = $this->postJson(self::URL, $this->payload())->json('data.mensualite');
        $avecAssurance = $this->postJson(self::URL, $this->payload(['assurance' => 2_000]))->json('data.mensualite');

        $this->assertGreaterThan(
            $sanAssurance,
            $avecAssurance,
            "La mensualité doit augmenter lorsqu'une assurance est incluse."
        );
    }

    // -------------------------------------------------------------------------
    // Test 6 : rejet 422 — champs obligatoires manquants
    // -------------------------------------------------------------------------

    public function test_preview_rejette_422_si_champs_obligatoires_absents(): void
    {
        $this->postJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // -------------------------------------------------------------------------
    // Test 7 : rejet 422 — montant en dehors des bornes
    // -------------------------------------------------------------------------

    public function test_preview_rejette_422_si_montant_hors_bornes(): void
    {
        // Trop faible (min = 25 000)
        $this->postJson(self::URL, $this->payload(['montant_emprunte' => 5_000]))
            ->assertStatus(422)
            ->assertJsonPath('errors.montant_emprunte', fn ($v) => is_array($v) && count($v) > 0);

        // Trop élevé (max = 10 000 000)
        $this->postJson(self::URL, $this->payload(['montant_emprunte' => 20_000_000]))
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Test 8 : rejet 422 — durée non autorisée
    // -------------------------------------------------------------------------

    public function test_preview_rejette_422_si_duree_non_autorisee(): void
    {
        // Durée non listée dans in:1,3,6,12,18,24
        $this->postJson(self::URL, $this->payload(['duree_mois' => 9]))
            ->assertStatus(422)
            ->assertJsonPath('errors.duree_mois', fn ($v) => is_array($v) && count($v) > 0);
    }

    // -------------------------------------------------------------------------
    // Test 9 : réponse sans persistance — aucune ligne créée en base
    // -------------------------------------------------------------------------

    public function test_preview_ne_persiste_pas_de_simulation(): void
    {
        $this->postJson(self::URL, $this->payload())->assertStatus(200);

        $this->assertDatabaseCount('simulations', 0);
    }
}
