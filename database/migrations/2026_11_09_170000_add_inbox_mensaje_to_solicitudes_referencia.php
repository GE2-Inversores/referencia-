<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->unsignedBigInteger('inbox_mensaje_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->dropIndex(['inbox_mensaje_id']);
            $table->dropColumn('inbox_mensaje_id');
        });
    }
};
