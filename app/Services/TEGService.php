<?php

namespace App\Services;

class TEGService
{
    private const MAX_ITERATIONS = 1000;
    private const PRECISION      = 1e-10; // sur le taux mensuel (~1.2e-9 sur le TEG annuel, largement < 0,01 %)

    /**
     * Calcule le Taux Effectif Global annuel selon la méthode BCEAO.
     *
     * Résout : montant_net = mensualite × [1 − (1+r)^(−n)] / r
     * puis retourne TEG = (1 + r_mensuel)^12 − 1.
     *
     * @param  float  $montant       Capital emprunté (FCFA)
     * @param  float  $mensualite    Mensualité constante toutes charges comprises (FCFA)
     * @param  int    $dureeMois     Nombre de mensualités
     * @param  float  $fraisDossier  Frais de dossier déduits du montant net (FCFA)
     * @return float  TEG annuel effectif en décimal (ex : 0.18 pour 18 %)
     */
    public function calculer(
        float $montant,
        float $mensualite,
        int   $dureeMois,
        float $fraisDossier = 0,
    ): float {
        $montantNet = $montant - $fraisDossier;

        if ($montantNet <= 0) {
            throw new \InvalidArgumentException('Le montant net doit être positif.');
        }

        // Cas dégénéré : zéro intérêt (remboursement exactement au pair)
        if (abs($mensualite * $dureeMois - $montantNet) < 1e-6) {
            return 0.0;
        }

        $tauxMensuel = $this->resoudreParNewtonRaphson($mensualite, $montantNet, $dureeMois);

        return (1 + $tauxMensuel) ** 12 - 1;
    }

    // -------------------------------------------------------------------------
    // Résolution numérique
    // -------------------------------------------------------------------------

    private function resoudreParNewtonRaphson(float $mensualite, float $montantNet, int $n): float
    {
        // Estimation initiale : taux mensuel approximatif par la méthode des intérêts simples
        $r = max(1e-8, ($mensualite * $n - $montantNet) / ($montantNet * $n));

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $f  = $this->f($r, $mensualite, $montantNet, $n);
            $df = $this->df($r, $mensualite, $n);

            if (abs($df) < 1e-15) {
                break; // dérivée nulle : bascule sur dichotomie
            }

            $rNew = max(1e-8, $r - $f / $df);

            if (abs($rNew - $r) < self::PRECISION) {
                return $rNew;
            }

            $r = $rNew;
        }

        return $this->resoudreParDichotomie($mensualite, $montantNet, $n);
    }

    private function resoudreParDichotomie(float $mensualite, float $montantNet, int $n): float
    {
        $rBas  = 1e-8;
        $rHaut = 5.0; // borne haute : 500 % mensuel (très large)

        // f est décroissante : f(rBas) > 0, f(rHaut) < 0
        for ($i = 0; $i < 200; $i++) {
            $rMid = ($rBas + $rHaut) / 2;
            $fMid = $this->f($rMid, $mensualite, $montantNet, $n);

            if (abs($fMid) < 1e-6 || ($rHaut - $rBas) / 2 < self::PRECISION) {
                return $rMid;
            }

            $fMid > 0 ? $rBas = $rMid : $rHaut = $rMid;
        }

        return ($rBas + $rHaut) / 2;
    }

    // -------------------------------------------------------------------------
    // Fonction et dérivée (valeur actuelle des flux moins montant net)
    // -------------------------------------------------------------------------

    /** f(r) = mensualite × [1 − (1+r)^(−n)] / r  −  montantNet */
    private function f(float $r, float $mensualite, float $montantNet, int $n): float
    {
        return $mensualite * (1 - (1 + $r) ** (-$n)) / $r - $montantNet;
    }

    /** f′(r) = mensualite × [ n·(1+r)^(−n−1)·r − (1 − (1+r)^(−n)) ] / r² */
    private function df(float $r, float $mensualite, int $n): float
    {
        $q = (1 + $r) ** (-$n);

        return $mensualite * ($n * (1 + $r) ** (-$n - 1) * $r - (1 - $q)) / ($r ** 2);
    }
}
