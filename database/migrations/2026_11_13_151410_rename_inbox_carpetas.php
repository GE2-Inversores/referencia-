<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Los correos que quedaron en «Papelera» se eliminan de verdad: sus
        // gmail_id pasan a la lista de eliminados para que la sincronización
        // no los reimporte desde el buzón.
        $papelera = DB::table('inbox_mensajes')->where('carpeta', 'Papelera')->pluck('gmail_id');
        if ($papelera->isNotEmpty()) {
            $previos = (array) json_decode((string) Setting::get('inbox_eliminados', '[]'), true);
            Setting::set('inbox_eliminados', json_encode(array_values(array_unique(array_merge($previos, $papelera->all())))));
            DB::table('inbox_mensajes')->where('carpeta', 'Papelera')->delete();
        }

        // Las carpetas pasan a ser solo dos: Gestionados (radicadas +
        // reenviadas) y Negados.
        DB::table('inbox_mensajes')->whereIn('carpeta', ['Aceptaciones', 'Reenviados'])->update(['carpeta' => 'Gestionados']);
        DB::table('inbox_mensajes')->where('carpeta', 'Negaciones')->update(['carpeta' => 'Negados']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inbox_mensajes')->where('carpeta', 'Gestionados')->where('gestion', 'radicada')->update(['carpeta' => 'Aceptaciones']);
        DB::table('inbox_mensajes')->where('carpeta', 'Gestionados')->where('gestion', 'reenviada')->update(['carpeta' => 'Reenviados']);
        DB::table('inbox_mensajes')->where('carpeta', 'Negados')->update(['carpeta' => 'Negaciones']);
    }
};
