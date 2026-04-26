<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tontines', function (Blueprint $table) {
            $table->id();
            $table->string('nom');                // "Tontine 10 500 F"
            $table->enum('categorie', ['petits', 'moyens', 'grands', 'premium']);
            $table->unsignedInteger('montant');   // valeur exacte : 1700, 10500…
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('montant');            // 1 montant = 1 tontine
        });
    }

    public function down(): void { Schema::dropIfExists('tontines'); }
};