<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration {
    public function up(): void {
        Schema::create('membres', function (Blueprint $table) {
            $table->id();
            $table->integer('num_registre')->unique();
            $table->string('nom');
            $table->string('telephone');
            $table->string('adresse')->nullable();
            $table->string('profession');
            $table->boolean('is_active')->default(true);
            $table->boolean('a_abandonne')->default(false);
            $table->date('date_inscription');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('membres'); }
};