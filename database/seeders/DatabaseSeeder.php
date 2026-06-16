<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Imf;
use App\Models\Offre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Admin::create([
            'email'    => 'superadmin@baraka.ml',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);

        Admin::create([
            'email'    => 'admin@baraka.ml',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $imfs = Imf::factory(5)->create();

        foreach ($imfs as $imf) {
            Agent::factory(rand(1, 2))->create(['id_imf' => $imf->id]);

            Offre::factory(rand(2, 4))->create([
                'id_imf' => $imf->id,
                'statut' => 'actif',
            ]);
        }
    }
}
