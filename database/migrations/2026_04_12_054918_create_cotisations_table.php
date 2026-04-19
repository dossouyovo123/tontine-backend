<?php

// ──────────────────────────────────────────────────────────────
// database/migrations/create_cotisations_table.php
// ──────────────────────────────────────────────────────────────
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cotisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')
                  ->constrained('membres')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('num_semaine'); // 1–52
            $table->unsignedSmallInteger('annee');       // ex: 2026
            $table->date('date_samedi');
            $table->unsignedInteger('montant')->default(0); // 0 par défaut = impayé

            // 'impaye' par défaut — les semaines sont pré-générées sans paiement
            // 'paye' uniquement après encaissement explicite
            $table->enum('statut', ['paye', 'impaye'])->default('impaye');

            $table->timestamps();

            // Contrainte unique : une seule ligne par (membre, semaine, année)
            // Le statut change, la ligne ne bouge pas (jamais de DELETE)
            $table->unique(
                ['membre_id', 'num_semaine', 'annee'],
                'unique_cotisation_semaine'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotisations');
    }
};

// ──────────────────────────────────────────────────────────────
// VÉRIFIEZ AUSSI votre migration create_membres_table.php
// Elle DOIT avoir ces defaults explicites :
//
//   $table->boolean('is_active')->default(true);
//   $table->boolean('a_abandonne')->default(false);
//
// Sans ces defaults en DB, un Membre::create() sans ces champs
// peut stocker NULL → le filtre WHERE is_active=1 exclut le membre
// et genererSemainesManquantes() ne le traite pas.
//
// Si votre migration membres n'a pas ces defaults, ajoutez une
// nouvelle migration :
//
//   Schema::table('membres', function (Blueprint $table) {
//       $table->boolean('is_active')->default(true)->change();
//       $table->boolean('a_abandonne')->default(false)->change();
//   });
//
// ──────────────────────────────────────────────────────────────