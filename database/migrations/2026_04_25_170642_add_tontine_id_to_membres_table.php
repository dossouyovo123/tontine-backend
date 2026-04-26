<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->foreignId('tontine_id')
                  ->nullable()                   // nullable = compatibilité membres existants
                  ->after('date_inscription')
                  ->constrained('tontines')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->dropForeign(['tontine_id']);
            $table->dropColumn('tontine_id');
        });
    }
};