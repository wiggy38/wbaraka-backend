<?php

/*
|--------------------------------------------------------------------------
| Configuration financière — Baraka Microcrédit
|--------------------------------------------------------------------------
|
| Source unique de vérité pour tous les calculs financiers de la plateforme.
| Conforme aux instructions de la BCEAO (Banque Centrale des États de
| l'Afrique de l'Ouest) applicables aux SFD/IMF de l'UEMOA.
|
| Référence réglementaire :
|   - Instruction BCEAO n°016-12/2010 relative aux conditions d'exercice
|     et de contrôle des activités des SFD dans l'UEMOA
|   - Circulaire BCEAO n°003-2011 sur les taux d'intérêt débiteurs
|   - Loi PARMEC révisée (2007) — cadre juridique des IMF en UEMOA
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Monnaie et arrondi
    |--------------------------------------------------------------------------
    |
    | Le FCFA (Franc CFA de l'Afrique de l'Ouest, XOF) n'a pas de subdivision
    | décimale en usage courant. Tous les montants affichés et stockés doivent
    | être des entiers (arrondi à l'unité supérieure, PHP_ROUND_HALF_UP).
    |
    */

    // Code ISO 4217 de la monnaie
    'devise' => 'XOF',

    // Symbole d'affichage
    'devise_symbole' => 'FCFA',

    // Nombre de décimales pour l'affichage (0 = entier strict)
    'decimales' => 0,

    // Mode d'arrondi PHP appliqué à chaque montant calculé
    // PHP_ROUND_HALF_UP = arrondi commercial standard (0,5 → 1)
    'arrondi_mode' => PHP_ROUND_HALF_UP,

    /*
    |--------------------------------------------------------------------------
    | Méthode de calcul du TEG (Taux Effectif Global)
    |--------------------------------------------------------------------------
    |
    | La BCEAO impose la méthode actuarielle pour le calcul du TEG.
    | Le TEG intègre : taux d'intérêt nominal + frais de dossier + assurance
    | + toute autre charge obligatoire liée à l'octroi du crédit.
    |
    | Formule actuarielle (base 365 jours) :
    |   (1 + taux_mensuel)^12 - 1 = TEG annuel
    |
    | Le TEG est exprimé en pourcentage annuel avec 2 décimales (affichage).
    |
    */

    'teg' => [

        // Méthode officielle BCEAO : 'actuarielle' (obligatoire pour IMF/SFD)
        // Alternative non retenue : 'proportionnelle' (taux × 12) — sous-estime le coût réel
        'methode' => 'actuarielle',

        // Base jours pour la conversion taux mensuel → taux annuel
        // BCEAO utilise l'année civile exacte (365 jours, 366 en bissextile)
        'jours_par_an' => 365,

        // Nombre de mois dans l'année — période de base du taux contractuel
        'mois_par_an' => 12,

        // Nombre de décimales pour l'affichage du TEG (ex. : 26,82%)
        'decimales_affichage' => 2,

        // Éléments inclus dans le calcul du TEG conformément à la réglementation BCEAO
        // true = la charge est intégrée dans le TEG calculé par la plateforme
        'inclure_frais_dossier' => true,
        'inclure_assurance'     => true,
        'inclure_frais_garantie' => false, // Exclu : charge variable et tierce (notaire, etc.)

    ],

    /*
    |--------------------------------------------------------------------------
    | Taux d'usure BCEAO (plafond légal du TEG)
    |--------------------------------------------------------------------------
    |
    | La BCEAO fixe un taux d'usure au-delà duquel tout prêt est illégal.
    | Pour les IMF/SFD de l'UEMOA, le taux plafond est de 27 % l'an (TEG).
    | Source : Instruction BCEAO n°016-12/2010 art. 44.
    |
    | ⚠ Ce plafond est révisé périodiquement par la BCEAO — à mettre à jour
    |   dès publication d'une nouvelle instruction ou circulaire.
    |
    */

    // Taux d'usure maximum légal en pourcentage annuel (TEG)
    'taux_usure_max_pct' => 27.00,

    // Date de dernière vérification du taux d'usure auprès de la BCEAO
    'taux_usure_verifie_le' => '2024-01-01',

    /*
    |--------------------------------------------------------------------------
    | Taux de pénalité de retard
    |--------------------------------------------------------------------------
    |
    | En cas de non-paiement à l'échéance, une pénalité est appliquée sur
    | les montants en souffrance. Le taux de pénalité ne peut pas dépasser
    | le taux d'usure BCEAO (art. 44 Instruction 016-12/2010).
    |
    | Calcul : pénalité_jour = capital_restant_dû × (taux_penalite_annuel / 365)
    |
    */

    'penalite' => [

        // Taux de pénalité annuel par défaut, en pourcentage (ex. : 3.00 = 3 % l'an)
        // Appliqué sur le capital échu impayé — s'ajoute aux intérêts contractuels
        'taux_annuel_defaut_pct' => 3.00,

        // Délai de grâce avant déclenchement des pénalités, en jours calendaires
        // Passé ce délai, les pénalités courent à compter de la date d'échéance
        'delai_grace_jours' => 3,

        // Seuil minimum de pénalité à facturer (en FCFA entier)
        // En dessous de ce montant, la pénalité n'est pas prélevée (gestion commerciale)
        'montant_minimum_fcfa' => 500,

    ],

    /*
    |--------------------------------------------------------------------------
    | Remboursement anticipé
    |--------------------------------------------------------------------------
    |
    | Conformément à la réglementation BCEAO et aux pratiques des SFD,
    | les IMF partenaires peuvent appliquer une indemnité de remboursement
    | anticipé (IRA) plafonnée.
    |
    */

    'remboursement_anticipe' => [

        // Indemnité de remboursement anticipé maximale, en % du capital restant dû
        // Plafond indicatif — à confirmer par contrat avec chaque IMF partenaire
        'ira_max_pct' => 1.00,

        // Remboursement anticipé autorisé sans pénalité en fin de crédit (dernière échéance)
        'exonere_derniere_echeance' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Tableau d'amortissement
    |--------------------------------------------------------------------------
    |
    | Paramètres régissant la génération du tableau d'amortissement par
    | échéances constantes (méthode "à annuités constantes" — la plus courante
    | pour les crédits à la consommation et les microcrédits en UEMOA).
    |
    */

    'amortissement' => [

        // Méthode de calcul des mensualités
        // 'annuites_constantes' : mensualité fixe, part intérêts décroissante
        // 'capital_constant'    : capital remboursé égal chaque mois, mensualité décroissante
        'methode_defaut' => 'annuites_constantes',

        // Nombre maximum de lignes dans un tableau d'amortissement exportable
        // (protection contre des durées aberrantes saisies par API)
        'duree_max_mois' => 120, // 10 ans

        // Durée minimale d'un crédit en mois
        'duree_min_mois' => 1,

    ],

    /*
    |--------------------------------------------------------------------------
    | Limites des montants de crédit
    |--------------------------------------------------------------------------
    |
    | Plafonds plateforme — indépendants des limites propres à chaque IMF.
    | Servent à la validation des simulations et à la protection contre les
    | saisies erronées.
    |
    */

    'montant' => [

        // Montant minimum simulable sur la plateforme (en FCFA)
        'simulation_min_fcfa' => 10_000,

        // Montant maximum simulable sur la plateforme (en FCFA)
        // (50 000 000 FCFA ≈ plafond haut des crédits PME des IMF agréées UEMOA)
        'simulation_max_fcfa' => 50_000_000,

    ],

];
