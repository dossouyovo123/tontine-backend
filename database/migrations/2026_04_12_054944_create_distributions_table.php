<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration {
    public function up(): void {
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')
                  ->constrained('membres')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('montant');
            $table->date('date_distribution');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('distributions'); }
};