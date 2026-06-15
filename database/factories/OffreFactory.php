<?php

namespace Database\Factories;

use App\Models\Imf;
use Illuminate\Database\Eloquent\Factories\Factory;

class OffreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_imf'                 => Imf::factory(),
            'nom_produit'            => $this->faker->words(3, true),
            'taux_interet_mensuel'   => $this->faker->randomFloat(2, 1, 5),
            'montant_min'            => 50_000,
            'montant_max'            => 5_000_000,
            'duree_min_mois'         => 3,
            'duree_max_mois'         => 24,
            'frais_dossier'          => null,
            'assurance'              => null,
            'garantie_requise'       => 'caution',
            'delai_traitement_jours' => 7,
            'cible_specifique'       => null,
            'zones_couverture'       => ['Bamako'],
            'statut'                 => 'brouillon',
            'date_mise_a_jour'       => now(),
        ];
    }
}
