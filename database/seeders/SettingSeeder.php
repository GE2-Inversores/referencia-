<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app_name', 'value' => 'Template CACSB', 'group' => 'general', 'type' => 'string'],
            ['key' => 'app_timezone', 'value' => 'America/Bogota', 'group' => 'general', 'type' => 'string'],
            ['key' => 'app_locale', 'value' => 'es', 'group' => 'general', 'type' => 'string'],

            // Mail
            ['key' => 'mail_mailer', 'value' => 'mailpit', 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_host', 'value' => '127.0.0.1', 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_port', 'value' => '1025', 'group' => 'mail', 'type' => 'integer'],
            ['key' => 'mail_encryption', 'value' => '', 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_username', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_password', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_from_address', 'value' => 'noreply@example.com', 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_from_name', 'value' => 'Clínica Santa Bárbara', 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_reply_to_address', 'value' => env('MAIL_REFERENCIA_INTERNA', 'referencia@cacsantabarbara.co'), 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mail_reply_to_name', 'value' => env('MAIL_REFERENCIA_NOMBRE', 'Referencia CACSB'), 'group' => 'mail', 'type' => 'string'],
            // AWS SES
            ['key' => 'ses_key', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'ses_secret', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'ses_region', 'value' => 'us-east-1', 'group' => 'mail', 'type' => 'string'],
            // Mailgun
            ['key' => 'mailgun_domain', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mailgun_secret', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'mailgun_endpoint', 'value' => 'api.mailgun.net', 'group' => 'mail', 'type' => 'string'],
            // Postmark
            ['key' => 'postmark_token', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            // Resend
            ['key' => 'resend_key', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'gmail_buzon', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'gmail_credenciales', 'value' => null, 'group' => 'mail', 'type' => 'string'],

            // API
            ['key' => 'api_rate_limit', 'value' => '60', 'group' => 'api', 'type' => 'integer'],
            ['key' => 'api_token_expiration', 'value' => '60', 'group' => 'api', 'type' => 'integer'],

            // Security
            ['key' => 'session_lifetime', 'value' => '120', 'group' => 'security', 'type' => 'integer'],
            ['key' => 'password_min_length', 'value' => '8', 'group' => 'security', 'type' => 'integer'],
            ['key' => 'password_require_special', 'value' => '1', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'password_require_numbers', 'value' => '1', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'password_require_uppercase', 'value' => '1', 'group' => 'security', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'type' => $setting['type'],
                ],
            );
        }
    }
}
