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
        // Vista de hilos: thread_id pasa a ser la clave de la conversación
        // (el Message-ID raíz en IMAP o el threadId real en Gmail API) y los
        // Message-ID/References completos quedan aparte para enlazar las
        // respuestas cuando sincronizan.
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->string('message_id')->nullable()->index()->after('thread_id');
            $table->text('in_reply_to')->nullable()->after('message_id');
            $table->text('referencias')->nullable()->after('in_reply_to');
            $table->string('thread_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropColumn(['message_id', 'in_reply_to', 'referencias']);
            $table->string('thread_id', 40)->nullable()->change();
        });
    }
};
