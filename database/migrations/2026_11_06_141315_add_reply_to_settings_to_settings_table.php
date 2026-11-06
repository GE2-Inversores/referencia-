<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'mail_reply_to_address'],
            ['value' => config('mail.referencia_interna'), 'group' => 'mail', 'type' => 'string'],
        );

        Setting::firstOrCreate(
            ['key' => 'mail_reply_to_name'],
            ['value' => config('mail.referencia_nombre'), 'group' => 'mail', 'type' => 'string'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::whereIn('key', ['mail_reply_to_address', 'mail_reply_to_name'])->delete();
    }
};
