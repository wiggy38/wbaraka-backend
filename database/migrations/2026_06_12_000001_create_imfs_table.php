<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imfs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->json('zones_couverture');
            $table->enum('statut', ['actif', 'suspendu']);
            $table->string('email_contact');
            $table->string('telephone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imfs');
    }
};
