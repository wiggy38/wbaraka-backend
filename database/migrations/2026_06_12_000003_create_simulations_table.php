<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulations', function (Blueprint $table) {
            $table->uuid('id_simulation')->primary();
            $table->uuid('id_utilisateur')->nullable();
            $table->uuid('id_offre')->nullable();
            $table->integer('montant_emprunte');
            $table->integer('duree_mois');
            $table->decimal('taux_utilise', 5, 2);
            $table->integer('cout_total');
            $table->integer('mensualite');
            $table->json('tableau_amortissement');
            $table->timestamp('date_creation')->useCurrent();

            $table->foreign('id_utilisateur')->references('id')->on('users')->nullOnDelete();
            $table->foreign('id_offre')->references('id_offre')->on('offres')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulations');
    }
};
