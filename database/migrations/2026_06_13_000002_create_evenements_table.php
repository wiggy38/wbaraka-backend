<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_offre');
            $table->foreign('id_offre')->references('id_offre')->on('offres')->onDelete('cascade');
            $table->enum('type', ['vue', 'simulation', 'clic']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};
