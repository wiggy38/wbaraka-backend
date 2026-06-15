<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ImfFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom'              => $this->faker->company(),
            'statut'           => 'actif',
            'email_contact'    => $this->faker->unique()->safeEmail(),
            'telephone'        => '+223' . $this->faker->numerify('########'),
            'zones_couverture' => ['Bamako'],
            'logo_url'         => null,
            'description'      => null,
        ];
    }
}
