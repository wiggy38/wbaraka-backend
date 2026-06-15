<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_admin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_admin')->constrained('admins')->cascadeOnDelete();
            $table->string('action');
            $table->string('cible_type');
            $table->uuid('cible_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_admin');
    }
};
