<?php

namespace App\Services;

class AmortissementService
{
    /**
     * Calcule le tableau d'amortissement à annuités constantes.
     *
     * @param  float  $montant       Capital emprunté (FCFA)
     * @param  float  $tauxMensuel   Taux mensuel en décimal (ex : 0.015 pour 1,5 %)
     * @param  int    $dureeMois     Nombre de mensualités
     * @param  float  $fraisDossier  Frais de dossier (FCFA, déduits du montant net)
     * @param  float  $assurance     Prime d'assurance mensuelle fixe (FCFA)
     * @return array{
     *     mensualite: int,
     *     cout_total: float,
     *     montant_net: float,
     *     tableau_amortissement: array<int, array{
     *         mois: int,
     *         capital_restant_debut: float,
     *         interet: float,
     *         capital_rembourse: float,
     *         assurance_mois: float,
     *         mensualite: int,
     *         capital_restant_fin: float,
     *     }>
     * }
     */
    public function calculer(
        float $montant,
        float $tauxMensuel,
        int   $dureeMois,
        float $fraisDossier = 0,
        float $assurance = 0,
    ): array {
        // Mensualité hors assurance (formule annuités constantes)
        if ($tauxMensuel == 0) {
            $mensualiteBase = $montant / $dureeMois;
        } else {
            $mensualiteBase = $montant
                * ($tauxMensuel * (1 + $tauxMensuel) ** $dureeMois)
                / ((1 + $tauxMensuel) ** $dureeMois - 1);
        }

        $mensualite = (int) round($mensualiteBase + $assurance);

        $tableau     = [];
        $capitalRestant = $montant;
        $totalCapitalRembourse = 0;

        for ($mois = 1; $mois <= $dureeMois; $mois++) {
            $capitalDebutMois = $capitalRestant;

            $interet = round($capitalRestant * $tauxMensuel);

            // Dernier mois : ajustement pour que la somme des capitaux = montant exact
            if ($mois === $dureeMois) {
                $capitalRembourse = $montant - $totalCapitalRembourse;
            } else {
                $capitalRembourse = round($mensualiteBase - $interet);
            }

            $capitalRestant -= $capitalRembourse;
            $totalCapitalRembourse += $capitalRembourse;

            $tableau[] = [
                'mois'                   => $mois,
                'capital_restant_debut'  => round($capitalDebutMois),
                'interet'                => $interet,
                'capital_rembourse'      => $capitalRembourse,
                'assurance_mois'         => $assurance,
                'mensualite'             => $mensualite,
                'capital_restant_fin'    => round(max(0, $capitalRestant)),
            ];
        }

        $totalInterets  = array_sum(array_column($tableau, 'interet'));
        $totalAssurance = $assurance * $dureeMois;
        $coutTotal      = $montant + $totalInterets + $fraisDossier + $totalAssurance;
        $montantNet     = $montant - $fraisDossier;

        return [
            'mensualite'            => $mensualite,
            'cout_total'            => round($coutTotal),
            'montant_net'           => round($montantNet),
            'tableau_amortissement' => $tableau,
        ];
    }
}
