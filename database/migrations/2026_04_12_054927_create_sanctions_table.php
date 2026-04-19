<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration {
    public function up(): void {
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')
                  ->constrained('membres')
                  ->cascadeOnDelete();
            $table->enum('motif', [
                'retard_reunion',
                'absence_non_justifiee',
                'retard_cotisation',
                'non_respect_statuts',
                'autre',
            ]);
            $table->unsignedInteger('montant'); // 300 ou 500 — fixé par motif
            $table->date('date_sanction');
            $table->enum('statut', ['en_attente', 'paye'])
                  ->default('en_attente');
            $table->date('date_paiement')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sanctions'); }
};