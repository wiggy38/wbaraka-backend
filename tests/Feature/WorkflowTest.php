<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Imf;
use App\Models\Offre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePortailJwt(string $agentId): string
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

    private function makeAdminJwt(string $adminId): string
    {
        $secret  = env('JWT_ADMIN_SECRET', 'test-admin-secret-for-phpunit');
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $adminId,
            'exp' => time() + 3600,
        ]));
        $sig = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));

        return "$header.$payload.$sig";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function createAdmin(string $role = 'admin'): Admin
    {
        return Admin::create([
            'email'    => "admin-{$role}-" . uniqid() . '@test.com',
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    private function offrePayload(array $overrides = []): array
    {
        return array_merge([
            'nom_produit'            => 'Crédit PME Test',
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

    // =========================================================================
    // Workflow 1 : Portail complet — login agent → créer offre → statut brouillon
    // =========================================================================

    /**
     * Un agent se connecte avec ses identifiants, reçoit un token JWT,
     * puis crée une offre qui doit apparaître en statut "brouillon".
     */
    public function test_agent_login_puis_creation_offre_statut_brouillon(): void
    {
        $imf   = Imf::factory()->create();
        $agent = Agent::factory()->create([
            'id_imf'   => $imf->id,
            'password' => Hash::make('secret123'),
            'statut'   => 'actif',
        ]);

        // Étape 1 : login
        $loginResponse = $this->postJson('/api/v1/portail/auth/login', [
            'email'    => $agent->email,
            'password' => 'secret123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'agent']]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        // Étape 2 : créer une offre avec le token obtenu
        $createResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/v1/portail/offres', $this->offrePayload());

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statut', 'brouillon')
            ->assertJsonPath('data.id_imf', $imf->id);

        // Étape 3 : vérifier la persistance en base
        $this->assertDatabaseHas('offres', [
            'nom_produit' => 'Crédit PME Test',
            'id_imf'      => $imf->id,
            'statut'      => 'brouillon',
        ]);
    }

    // =========================================================================
    // Workflow 2 : Modération — admin approuve une offre → statut actif + journal
    // =========================================================================

    /**
     * Un admin se connecte, approuve une offre en_validation,
     * le statut passe à "actif" et une entrée est écrite dans le journal.
     */
    public function test_admin_login_puis_approbation_offre_cree_entree_journal(): void
    {
        $admin = $this->createAdmin('admin');
        $imf   = Imf::factory()->create();
        $offre = Offre::factory()->create([
            'id_imf'  => $imf->id,
            'statut'  => 'en_validation',
        ]);

        // Étape 1 : login admin
        $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => $admin->email,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'admin']]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        // Étape 2 : approuver l'offre
        $approuverResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson("/api/v1/admin/moderation/offres/{$offre->id_offre}/approuver");

        $approuverResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statut', 'actif');

        // Étape 3 : vérifier le statut en base
        $this->assertDatabaseHas('offres', [
            'id_offre' => $offre->id_offre,
            'statut'   => 'actif',
        ]);

        // Étape 4 : vérifier l'entrée journal
        $this->assertDatabaseHas('journal_admin', [
            'id_admin'   => $admin->id,
            'action'     => 'approuver_offre',
            'cible_type' => 'offre',
            'cible_id'   => $offre->id_offre,
        ]);
    }

    // =========================================================================
    // Workflow 3 : Suspension IMF → offres actives passent en inactif
    // =========================================================================

    /**
     * Suspendre une IMF doit passer toutes ses offres "actif" en "inactif".
     * Les offres dans d'autres statuts ne doivent pas être affectées.
     */
    public function test_suspension_imf_desactive_toutes_ses_offres_actives(): void
    {
        $admin = $this->createAdmin('super_admin');
        $token = $this->makeAdminJwt($admin->id);

        $imf = Imf::factory()->create(['statut' => 'actif']);

        // Créer des offres dans différents statuts
        $offreActive1 = Offre::factory()->create(['id_imf' => $imf->id, 'statut' => 'actif']);
        $offreActive2 = Offre::factory()->create(['id_imf' => $imf->id, 'statut' => 'actif']);
        $offreBrouillon = Offre::factory()->create(['id_imf' => $imf->id, 'statut' => 'brouillon']);
        $offreEnValidation = Offre::factory()->create(['id_imf' => $imf->id, 'statut' => 'en_validation']);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/v1/admin/imfs/{$imf->id}/suspendre");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statut', 'suspendu')
            ->assertJsonPath('data.offres_desactivees', 2);

        // IMF suspendue
        $this->assertDatabaseHas('imfs', [
            'id'     => $imf->id,
            'statut' => 'suspendu',
        ]);

        // Les deux offres actives sont passées en inactif
        $this->assertDatabaseHas('offres', ['id_offre' => $offreActive1->id_offre, 'statut' => 'inactif']);
        $this->assertDatabaseHas('offres', ['id_offre' => $offreActive2->id_offre, 'statut' => 'inactif']);

        // Les autres statuts sont inchangés
        $this->assertDatabaseHas('offres', ['id_offre' => $offreBrouillon->id_offre, 'statut' => 'brouillon']);
        $this->assertDatabaseHas('offres', ['id_offre' => $offreEnValidation->id_offre, 'statut' => 'en_validation']);
    }

    /**
     * Tenter de suspendre une IMF déjà suspendue retourne 422.
     */
    public function test_suspension_imf_deja_suspendue_retourne_422(): void
    {
        $admin = $this->createAdmin('admin');
        $token = $this->makeAdminJwt($admin->id);

        $imf = Imf::factory()->create(['statut' => 'suspendu']);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->putJson("/api/v1/admin/imfs/{$imf->id}/suspendre");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // Workflow 4 : Accès refusé — admin non-super_admin sur endpoints super_admin
    // =========================================================================

    /**
     * Un admin avec le rôle "admin" (pas super_admin) reçoit 403
     * quand il tente de supprimer une IMF.
     */
    public function test_admin_non_super_admin_ne_peut_pas_supprimer_imf(): void
    {
        $admin = $this->createAdmin('admin');
        $token = $this->makeAdminJwt($admin->id);
        $imf   = Imf::factory()->create();

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->deleteJson("/api/v1/admin/imfs/{$imf->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        // L'IMF ne doit pas avoir été supprimée
        $this->assertDatabaseHas('imfs', ['id' => $imf->id]);
    }

    /**
     * Un admin avec le rôle "admin" reçoit 403 sur le journal (super_admin uniquement).
     */
    public function test_admin_non_super_admin_ne_peut_pas_acceder_au_journal(): void
    {
        $admin = $this->createAdmin('admin');
        $token = $this->makeAdminJwt($admin->id);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/v1/admin/journal');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Un admin avec le rôle "admin" reçoit 403 sur la liste des sliders (super_admin uniquement).
     */
    public function test_admin_non_super_admin_ne_peut_pas_acceder_aux_sliders(): void
    {
        $admin = $this->createAdmin('admin');
        $token = $this->makeAdminJwt($admin->id);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/v1/admin/slider');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Contrôle inverse : un super_admin accède bien au journal (200).
     */
    public function test_super_admin_peut_acceder_au_journal(): void
    {
        $admin = $this->createAdmin('super_admin');
        $token = $this->makeAdminJwt($admin->id);

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/v1/admin/journal');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
