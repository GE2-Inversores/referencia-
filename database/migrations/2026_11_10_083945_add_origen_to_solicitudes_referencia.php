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
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->string('origen', 20)->default('aplicativo')->index()->after('inbox_mensaje_id');
        });

        DB::table('solicitudes_referencia')->whereNotNull('inbox_mensaje_id')->update(['origen' => 'correo']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->dropIndex(['origen']);
            $table->dropColumn('origen');
        });
    }
};
