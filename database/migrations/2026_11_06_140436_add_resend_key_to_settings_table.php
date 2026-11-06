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
            ['key' => 'resend_key'],
            ['value' => null, 'group' => 'mail', 'type' => 'string'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::where('key', 'resend_key')->delete();
    }
};
