<?php

namespace Tests\Unit;

use App\Services\TEGService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de TEGService.
 *
 * Valeurs de référence calculées analytiquement :
 *
 *   n = 1  →  la formule de la valeur actuelle se réduit à :
 *             mensualite / (1 + r) = montantNet
 *             soit r = mensualite / montantNet − 1  (exact, sans itération)
 *             donc TEG = (1 + r)^12 − 1
 *
 *   n > 1  →  on injecte la mensualité exacte issue de la formule de l'annuité
 *             pour un taux r connu, puis on vérifie que le service retrouve
 *             TEG = (1 + r)^12 − 1 à 1e-5 près.
 */
class TEGServiceTest extends TestCase
{
    private TEGService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TEGService();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Mensualité exacte (float) pour un prêt in fine à annuité constante.
     * mensualite = P × r(1+r)^n / ((1+r)^n − 1)
     */
    private function mensualiteExacte(float $p, float $r, int $n): float
    {
        $q = (1 + $r) ** $n;
        return $p * $r * $q / ($q - 1);
    }

    // -------------------------------------------------------------------------
    // Cas dégénéré : remboursement exactement au pair → TEG = 0
    // -------------------------------------------------------------------------

    /**
     * Référence 1 : zéro intérêt.
     * mensualite × n = montantNet à la précision machine → la formule se court-circuite.
     */
    public function test_cas_degenerere_zero_interet_retourne_teg_zero(): void
    {
        // 10 000 × 12 = 120 000 = montantNet (exact)
        $teg = $this->service->calculer(
            montant:      120_000,
            mensualite:   10_000,
            dureeMois:    12,
            fraisDossier: 0,
        );

        $this->assertSame(0.0, $teg);
    }

    // -------------------------------------------------------------------------
    // Référence 2 : n = 1, sans frais (vérification algébrique exacte)
    // -------------------------------------------------------------------------

    /**
     * Pour n = 1 : PV = mensualite / (1 + r)
     * → r = mensualite / montantNet − 1
     * → TEG = (1 + r)^12 − 1  (calculable sans itération)
     *
     * r_mensuel = 0,015  →  TEG = (1,015)^12 − 1 ≈ 0,195618
     *
     * Valeur Excel / Python :
     *   =RATE(1,,-100000,101500)*1  → 0,015
     *   =(1,015)^12-1               → 0,19561817148...
     */
    public function test_reference2_n1_sans_frais_taux_1p5_pourcent_mensuel(): void
    {
        // mensualite = 101 500  →  r = 1 500 / 100 000 = 0,015
        $teg = $this->service->calculer(
            montant:      100_000,
            mensualite:   101_500,
            dureeMois:    1,
            fraisDossier: 0,
        );

        // TEG attendu : (1,015)^12 − 1 = 0,19561817148...
        $this->assertEqualsWithDelta(0.19561817148, $teg, 1e-7,
            'TEG doit correspondre à un taux mensuel de 1,5 % (n=1, sans frais).'
        );
    }

    // -------------------------------------------------------------------------
    // Référence 3 : n = 1, avec frais de dossier (vérification algébrique exacte)
    // -------------------------------------------------------------------------

    /**
     * Avec frais : montantNet = montant − frais
     * r = mensualite / montantNet − 1
     * TEG = (1 + r)^12 − 1
     *
     * montant=100 000, frais=10 000, mensualite=90 900
     * montantNet = 90 000
     * r = 90 900 / 90 000 − 1 = 0,01
     * TEG = (1,01)^12 − 1 ≈ 0,126825
     *
     * Valeur Excel / Python :
     *   =RATE(1,,-90000,90900)  → 0,01
     *   =(1,01)^12-1            → 0,12682503013...
     */
    public function test_reference3_n1_avec_frais_taux_1_pourcent_mensuel(): void
    {
        $teg = $this->service->calculer(
            montant:      100_000,
            mensualite:   90_900,
            dureeMois:    1,
            fraisDossier: 10_000,
        );

        // TEG attendu : (1,01)^12 − 1 = 0,12682503013...
        $this->assertEqualsWithDelta(0.12682503013, $teg, 1e-7,
            'TEG doit correspondre à un taux mensuel de 1 % (n=1, frais=10 000 FCFA).'
        );
    }

    // -------------------------------------------------------------------------
    // Référence 4 : n = 12, mensualité issue de la formule d'annuité (r = 2 %/mois)
    // -------------------------------------------------------------------------

    /**
     * Taux mensuel cible : r = 0,02
     * (1,02)^12 = 1,268241794562...
     * mensualite_exacte = 100 000 × 0,02 × 1,268241794562 / 0,268241794562
     *                   ≈ 9 456,0171...
     *
     * TEG attendu = (1,02)^12 − 1 = 0,268241794562
     *
     * Valeur Excel / Python :
     *   =PMT(0.02,12,-100000)          → 9456,0171...
     *   =RATE(12,-9456.0171,,100000)   → 0,02 (monthly) → TEG ≈ 0,26824
     *   =(1,02)^12-1                   → 0,26824179...
     */
    public function test_reference4_n12_taux_2_pourcent_mensuel(): void
    {
        $rMensuel        = 0.02;
        $montant         = 100_000.0;
        $n               = 12;
        $mensualiteExact = $this->mensualiteExacte($montant, $rMensuel, $n); // ≈ 9 456,017

        $teg = $this->service->calculer(
            montant:      $montant,
            mensualite:   $mensualiteExact,
            dureeMois:    $n,
            fraisDossier: 0,
        );

        $tegAttendu = (1 + $rMensuel) ** 12 - 1; // ≈ 0,26824179456...

        $this->assertEqualsWithDelta($tegAttendu, $teg, 1e-6,
            "TEG doit correspondre à un taux mensuel de 2 % sur 12 mois (mensualite exacte)."
        );
    }

    // -------------------------------------------------------------------------
    // Référence 5 : n = 6, mensualité issue de la formule d'annuité (r = 1,5 %/mois)
    // -------------------------------------------------------------------------

    /**
     * Taux mensuel cible : r = 0,015
     * (1,015)^6 = 1,093443396...
     * mensualite_exacte = 500 000 × 0,015 × (1,015)^6 / ((1,015)^6 − 1)
     *                   ≈ 87 924,91...
     *
     * TEG attendu = (1,015)^12 − 1 ≈ 0,195618...
     *
     * Valeur Excel / Python :
     *   =PMT(0.015,6,-500000)         → 87 924,90...
     *   =(1,015)^12-1                 → 0,195618...
     */
    public function test_reference5_n6_taux_1p5_pourcent_mensuel_montant_eleve(): void
    {
        $rMensuel        = 0.015;
        $montant         = 500_000.0;
        $n               = 6;
        $mensualiteExact = $this->mensualiteExacte($montant, $rMensuel, $n);

        $teg = $this->service->calculer(
            montant:      $montant,
            mensualite:   $mensualiteExact,
            dureeMois:    $n,
            fraisDossier: 0,
        );

        $tegAttendu = (1 + $rMensuel) ** 12 - 1;

        $this->assertEqualsWithDelta($tegAttendu, $teg, 1e-6,
            "TEG doit correspondre à un taux mensuel de 1,5 % sur 6 mois."
        );
    }

    // -------------------------------------------------------------------------
    // Comportement attendu : TEG > 0 quand mensualite * n > montantNet
    // -------------------------------------------------------------------------

    public function test_teg_est_positif_lorsque_mensualites_depassent_capital(): void
    {
        $teg = $this->service->calculer(
            montant:      300_000,
            mensualite:   30_000,  // 30 000 × 12 = 360 000 > 300 000
            dureeMois:    12,
            fraisDossier: 0,
        );

        $this->assertGreaterThan(0.0, $teg);
        $this->assertLessThan(5.0, $teg); // < 500 % annuel — plausible
    }

    // -------------------------------------------------------------------------
    // Comportement attendu : montant net négatif → InvalidArgumentException
    // -------------------------------------------------------------------------

    public function test_exception_si_montant_net_negatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('montant net');

        $this->service->calculer(
            montant:      100_000,
            mensualite:   10_000,
            dureeMois:    12,
            fraisDossier: 150_000, // frais > montant → montantNet < 0
        );
    }

    // -------------------------------------------------------------------------
    // Comportement attendu : frais nuls et frais positifs donnent des TEG différents
    // -------------------------------------------------------------------------

    /**
     * Les frais réduisent le montantNet mais les mensualités restent identiques,
     * ce qui augmente mécaniquement le TEG.
     */
    public function test_frais_dossier_augmentent_le_teg(): void
    {
        $mensualiteExact = $this->mensualiteExacte(500_000.0, 0.02, 12);

        $tegSansFrais = $this->service->calculer(
            montant:      500_000,
            mensualite:   $mensualiteExact,
            dureeMois:    12,
            fraisDossier: 0,
        );

        $tegAvecFrais = $this->service->calculer(
            montant:      500_000,
            mensualite:   $mensualiteExact,
            dureeMois:    12,
            fraisDossier: 20_000,
        );

        $this->assertGreaterThan($tegSansFrais, $tegAvecFrais,
            'Des frais de dossier doivent augmenter le TEG à mensualité constante.'
        );
    }
}
