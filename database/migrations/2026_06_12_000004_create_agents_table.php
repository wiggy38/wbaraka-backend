<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_imf');
            $table->foreign('id_imf')->references('id')->on('imfs')->onDelete('cascade');
            $table->string('nom');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin_imf', 'agent']);
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
