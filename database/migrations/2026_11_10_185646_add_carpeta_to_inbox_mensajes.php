<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->string('carpeta', 60)->nullable()->index()->after('gestion_detalle');
        });

        DB::table('inbox_mensajes')->where('gestion', 'radicada')->update(['carpeta' => 'Aceptaciones']);
        DB::table('inbox_mensajes')->where('gestion', 'negada')->update(['carpeta' => 'Negaciones']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->dropIndex(['carpeta']);
            $table->dropColumn('carpeta');
        });
    }
};
