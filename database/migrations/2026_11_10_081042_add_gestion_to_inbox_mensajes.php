<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->string('gestion', 20)->nullable()->after('leido')->index();
            $table->string('gestion_por', 200)->nullable()->after('gestion');
            $table->timestamp('gestion_at')->nullable()->after('gestion_por');
            $table->text('gestion_detalle')->nullable()->after('gestion_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->dropIndex(['gestion']);
            $table->dropColumn(['gestion', 'gestion_por', 'gestion_at', 'gestion_detalle']);
        });
    }
};
