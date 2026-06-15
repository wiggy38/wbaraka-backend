<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Imf;
use App\Models\Offre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffreControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeJwt(string $agentId): string
    {
        $secret  = env('JWT_PORTAIL_SECRET', 'test-secret-for-phpunit');
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $agentId,
            'exp' => time() + 3600,
        ]));
        $sig = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));

        return "$header.$payload.$sig";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function offrePayload(array $overrides = []): array
    {
        return array_merge([
            'nom_produit'            => 'Crédit PME',
            'taux_interet_mensuel'   => 2.5,
            'montant_min'            => 50000,
            'montant_max'            => 5000000,
            'duree_min_mois'         => 3,
            'duree_max_mois'         => 24,
            'garantie_requise'       => 'caution',
            'delai_traitement_jours' => 7,
            'zones_couverture'       => ['Bamako'],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Test 1 : GET /api/v1/offres → 200 + liste paginée
    // -------------------------------------------------------------------------

    public function test_public_offres_index_returns_200_with_paginated_data(): void
    {
        $imf = Imf::factory()->create();
        Offre::factory()->count(3)->create(['id_imf' => $imf->id]);

        $response = $this->getJson('/api/v1/offres');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);

        $this->assertCount(3, $response->json('data.data'));
    }

    // -------------------------------------------------------------------------
    // Test 2 : POST /api/v1/portail/offres sans token → 401
    // -------------------------------------------------------------------------

    public function test_create_offre_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/portail/offres', $this->offrePayload());

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Test 3 : POST /api/v1/portail/offres avec token valide → 201 + statut brouillon
    // -------------------------------------------------------------------------

    public function test_create_offre_with_valid_token_creates_brouillon(): void
    {
        $imf   = Imf::factory()->create();
        $agent = Agent::factory()->create(['id_imf' => $imf->id]);
        $token = $this->makeJwt($agent->id);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/v1/portail/offres', $this->offrePayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.id_imf', $imf->id);

        $this->assertDatabaseHas('offres', [
            'nom_produit' => 'Crédit PME',
            'id_imf'      => $imf->id,
            'statut'      => 'brouillon',
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 4 : PUT /api/v1/portail/offres/{id} — offre d'une autre IMF → 403
    // -------------------------------------------------------------------------

    public function test_update_offre_belonging_to_other_imf_returns_403(): void
    {
        $imfA  = Imf::factory()->create();
        $imfB  = Imf::factory()->create();

        $agentA = Agent::factory()->create(['id_imf' => $imfA->id]);
        $token  = $this->makeJwt($agentA->id);

        $offreB = Offre::factory()->create(['id_imf' => $imfB->id]);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/v1/portail/offres/{$offreB->id_offre}", $this->offrePayload([
                'nom_produit' => 'Tentative de modification',
                'statut'      => 'brouillon',
            ]));

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
