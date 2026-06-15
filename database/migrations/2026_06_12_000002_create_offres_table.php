<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offres', function (Blueprint $table) {
            $table->uuid('id_offre')->primary();
            $table->uuid('id_imf');
            $table->foreign('id_imf')->references('id')->on('imfs')->onDelete('cascade');
            $table->string('nom_produit');
            $table->decimal('taux_interet_mensuel', 5, 2);
            $table->integer('montant_min');
            $table->integer('montant_max');
            $table->integer('duree_min_mois');
            $table->integer('duree_max_mois');
            $table->decimal('frais_dossier', 10, 2)->nullable();
            $table->decimal('assurance', 5, 2)->nullable();
            $table->enum('garantie_requise', ['aucune', 'caution', 'neant', 'bien']);
            $table->integer('delai_traitement_jours');
            $table->json('cible_specifique')->nullable();
            $table->json('zones_couverture');
            $table->enum('statut', ['brouillon', 'en_validation', 'actif', 'inactif']);
            $table->timestamp('date_mise_a_jour')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};
