<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration {
    public function up(): void {
        Schema::create('complements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // CMP001 …
            $table->foreignId('membre_id')
                  ->constrained('membres')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('semaines_cotisees_snapshot');
            $table->unsignedBigInteger('montant_cotise_total');
            $table->unsignedBigInteger('montant_moto_estime');
            $table->string('description_moto')->nullable();
            $table->enum('statut', [
                'en_attente',
                'approuve',
                'refuse',
                'moto_attribuee',
            ])->default('en_attente');
            $table->date('date_demande');
            $table->date('date_attribution_moto')->nullable();
            $table->text('notes_admin')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('complements'); }
};