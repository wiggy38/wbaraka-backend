<?php

namespace Tests\Unit;

use App\Services\AmortissementService;
use PHPUnit\Framework\TestCase;

class AmortissementServiceTest extends TestCase
{
    private AmortissementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmortissementService();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Vérifie les trois invariants communs à tous les cas :
     *  1. Nombre de lignes du tableau == dureeMois
     *  2. Somme des capital_rembourse == montant emprunté (ajustement dernier mois)
     *  3. cout_total cohérent avec la somme des mensualités + frais_dossier
     *     (tolérance : 1 FCFA par mois dû aux arrondis entiers)
     */
    private function assertInvariants(
        array $result,
        float $montant,
        int   $dureeMois,
        float $fraisDossier = 0,
    ): void {
        $tableau = $result['tableau_amortissement'];

        // 1. Longueur du tableau
        $this->assertCount(
            $dureeMois,
            $tableau,
            "Le tableau doit contenir exactement {$dureeMois} lignes."
        );

        // 2. Somme des capitaux remboursés == montant exact
        $sumCapital = array_sum(array_column($tableau, 'capital_rembourse'));
        $this->assertEquals(
            $montant,
            $sumCapital,
            "La somme des capital_rembourse ({$sumCapital}) doit égaler le montant emprunté ({$montant})."
        );

        // 3. Cohérence cout_total ↔ somme des mensualités + frais
        //    cout_total = montant + Σintérêts + frais + Σassurance
        //    Σmensualités ≈ montant + Σintérêts + Σassurance  (≠ exactement car arrondis)
        //    => |cout_total - (Σmensualités + frais)| ≤ duree_mois  (≤ 1 FCFA / mois)
        $sumMensualites = $result['mensualite'] * $dureeMois;
        $ecart = abs($result['cout_total'] - ($sumMensualites + $fraisDossier));
        $this->assertLessThanOrEqual(
            $dureeMois,
            $ecart,
            "Le cout_total ({$result['cout_total']}) doit être cohérent avec "
            . "Σmensualités ({$sumMensualites}) + frais ({$fraisDossier}). Écart : {$ecart} FCFA."
        );
    }

    // -------------------------------------------------------------------------
    // Cas 1 : Taux zéro — remboursement linéaire pur
    // -------------------------------------------------------------------------

    public function test_cas1_taux_zero_remboursement_lineaire(): void
    {
        $montant    = 120_000;
        $dureeMois  = 12;

        $result = $this->service->calculer($montant, tauxMensuel: 0.0, dureeMois: $dureeMois);

        // Mensualité exacte = montant / durée (pas d'intérêts)
        $this->assertEquals(10_000, $result['mensualite']);

        // cout_total = montant (pas d'intérêts, pas de frais, pas d'assurance)
        $this->assertEquals($montant, $result['cout_total']);

        // montant_net = montant (pas de frais)
        $this->assertEquals($montant, $result['montant_net']);

        // Tous les intérêts doivent être nuls
        foreach ($result['tableau_amortissement'] as $ligne) {
            $this->assertEquals(0, $ligne['interet'], "Intérêt mois {$ligne['mois']} doit être 0 si taux = 0.");
        }

        $this->assertInvariants($result, $montant, $dureeMois);
    }

    // -------------------------------------------------------------------------
    // Cas 2 : Taux standard, durée courte, sans frais ni assurance
    // -------------------------------------------------------------------------

    public function test_cas2_taux_standard_sans_frais_ni_assurance(): void
    {
        $montant    = 100_000;
        $tauxMensuel = 0.015; // 1,5 % / mois
        $dureeMois  = 6;

        $result = $this->service->calculer($montant, $tauxMensuel, $dureeMois);

        // La mensualité doit être positive et supérieure au capital mensuel théorique
        $this->assertGreaterThan((int) ($montant / $dureeMois), $result['mensualite']);

        // Le capital restant fin du dernier mois doit être 0
        $derniereLigne = end($result['tableau_amortissement']);
        $this->assertEquals(0, $derniereLigne['capital_restant_fin']);

        // Aucune assurance dans le tableau
        foreach ($result['tableau_amortissement'] as $ligne) {
            $this->assertEquals(0, $ligne['assurance_mois']);
        }

        $this->assertInvariants($result, $montant, $dureeMois);
    }

    // -------------------------------------------------------------------------
    // Cas 3 : Avec frais de dossier uniquement
    // -------------------------------------------------------------------------

    public function test_cas3_avec_frais_dossier_uniquement(): void
    {
        $montant       = 500_000;
        $tauxMensuel   = 0.02;  // 2 % / mois
        $dureeMois     = 12;
        $fraisDossier  = 15_000;

        $result = $this->service->calculer($montant, $tauxMensuel, $dureeMois, fraisDossier: $fraisDossier);

        // montant_net = montant - frais_dossier
        $this->assertEquals($montant - $fraisDossier, $result['montant_net']);

        // cout_total doit inclure les frais
        $this->assertGreaterThan($montant, $result['cout_total']);

        // Les frais n'apparaissent pas dans les mensualités (pas d'assurance)
        foreach ($result['tableau_amortissement'] as $ligne) {
            $this->assertEquals(0, $ligne['assurance_mois']);
        }

        $this->assertInvariants($result, $montant, $dureeMois, $fraisDossier);
    }

    // -------------------------------------------------------------------------
    // Cas 4 : Avec assurance mensuelle uniquement (longue durée)
    // -------------------------------------------------------------------------

    public function test_cas4_avec_assurance_uniquement_longue_duree(): void
    {
        $montant     = 200_000;
        $tauxMensuel = 0.015; // 1,5 % / mois
        $dureeMois   = 24;
        $assurance   = 1_000;  // 1 000 FCFA / mois

        $result = $this->service->calculer($montant, $tauxMensuel, $dureeMois, assurance: $assurance);

        // Chaque ligne du tableau doit afficher l'assurance mensuelle
        foreach ($result['tableau_amortissement'] as $ligne) {
            $this->assertEquals($assurance, $ligne['assurance_mois'],
                "assurance_mois mois {$ligne['mois']} doit valoir {$assurance}."
            );
        }

        // cout_total doit inclure assurance totale = assurance * dureeMois
        $assuranceTotale = $assurance * $dureeMois;
        $this->assertGreaterThanOrEqual($montant + $assuranceTotale, $result['cout_total']);

        // La mensualité doit être > mensualite sans assurance
        $resultSansAssurance = $this->service->calculer($montant, $tauxMensuel, $dureeMois);
        $this->assertGreaterThan($resultSansAssurance['mensualite'], $result['mensualite']);

        $this->assertInvariants($result, $montant, $dureeMois);
    }

    // -------------------------------------------------------------------------
    // Cas 5 : Frais + assurance combinés, montant élevé
    // -------------------------------------------------------------------------

    public function test_cas5_frais_et_assurance_combines_montant_eleve(): void
    {
        $montant      = 1_000_000;
        $tauxMensuel  = 0.025; // 2,5 % / mois
        $dureeMois    = 18;
        $fraisDossier = 25_000;
        $assurance    = 2_000;

        $result = $this->service->calculer($montant, $tauxMensuel, $dureeMois, $fraisDossier, $assurance);

        // montant_net
        $this->assertEquals($montant - $fraisDossier, $result['montant_net']);

        // cout_total > montant + frais + assurance totale (+ intérêts)
        $this->assertGreaterThan($montant + $fraisDossier + $assurance * $dureeMois, $result['cout_total']);

        // Structure de chaque ligne
        foreach ($result['tableau_amortissement'] as $i => $ligne) {
            $this->assertEquals($i + 1, $ligne['mois']);
            $this->assertGreaterThanOrEqual(0, $ligne['capital_restant_fin']);
            $this->assertGreaterThan(0, $ligne['capital_rembourse']);
            $this->assertGreaterThan(0, $ligne['interet']);
            $this->assertEquals($assurance, $ligne['assurance_mois']);
        }

        $this->assertInvariants($result, $montant, $dureeMois, $fraisDossier);
    }

    // -------------------------------------------------------------------------
    // Cas 6 : Durée minimale d'un mois
    // -------------------------------------------------------------------------

    public function test_cas6_duree_un_mois(): void
    {
        $montant     = 50_000;
        $tauxMensuel = 0.02;
        $dureeMois   = 1;

        $result = $this->service->calculer($montant, $tauxMensuel, $dureeMois);

        // Tableau de 1 seule ligne
        $this->assertCount(1, $result['tableau_amortissement']);

        $ligne = $result['tableau_amortissement'][0];

        // Capital remboursé = montant entier (dernier mois = premier mois)
        $this->assertEquals($montant, $ligne['capital_rembourse']);

        // Capital restant fin = 0
        $this->assertEquals(0, $ligne['capital_restant_fin']);

        // Intérêt = montant * taux
        $this->assertEquals(round($montant * $tauxMensuel), $ligne['interet']);

        $this->assertInvariants($result, $montant, $dureeMois);
    }

    // -------------------------------------------------------------------------
    // Cas 7 : Capital restant décroissant strictement tout au long du tableau
    // -------------------------------------------------------------------------

    public function test_cas7_capital_restant_strictement_decroissant(): void
    {
        $result = $this->service->calculer(
            montant:     300_000,
            tauxMensuel: 0.018,
            dureeMois:   12,
            assurance:   500,
        );

        $tableau = $result['tableau_amortissement'];

        for ($i = 1; $i < count($tableau); $i++) {
            $this->assertLessThan(
                $tableau[$i - 1]['capital_restant_fin'],
                $tableau[$i]['capital_restant_fin'],
                "capital_restant_fin au mois {$tableau[$i]['mois']} doit être "
                . "inférieur au mois précédent."
            );
        }
    }
}
