<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('benefices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')
                  ->constrained('membres')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('total_cotise');    // somme cotisations payées sur l'année
            $table->unsignedInteger('montant_benefice');// = montant tontine du membre
            $table->date('date_prelevement');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Un seul bénéfice par membre par année
            $table->unique(['membre_id', 'annee'], 'unique_benefice_annee');
        });
    }

    public function down(): void { Schema::dropIfExists('benefices'); }
};