<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $ahora = now();

        foreach ([
            ['key' => 'gmail_modo', 'value' => 'imap', 'group' => 'mail', 'type' => 'string'],
            ['key' => 'gmail_password', 'value' => null, 'group' => 'mail', 'type' => 'string'],
        ] as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['gmail_modo', 'gmail_password'])->delete();
    }
};
