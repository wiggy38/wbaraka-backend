<?php

namespace Database\Factories;

use App\Models\Imf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_imf'   => Imf::factory(),
            'nom'      => $this->faker->name(),
            'email'    => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role'     => 'agent',
            'statut'   => 'actif',
        ];
    }
}
