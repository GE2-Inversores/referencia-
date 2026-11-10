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
            ['key' => 'gmail_buzon', 'value' => null, 'group' => 'mail', 'type' => 'string'],
            ['key' => 'gmail_credenciales', 'value' => null, 'group' => 'mail', 'type' => 'string'],
        ] as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['created_at' => $ahora, 'updated_at' => $ahora],
            );
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'inbox.view', 'guard_name' => 'web'],
            [
                'display_name' => 'Ver bandeja de correo',
                'description' => 'Permite acceder a la bandeja de entrada con los correos del buzón de Gmail',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        );

        $permisoId = DB::table('permissions')->where('name', 'inbox.view')->value('id');

        // Los roles que ya pueden ver el panel principal heredan la bandeja.
        $rolIds = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'dashboard.view')
            ->pluck('role_has_permissions.role_id');

        foreach ($rolIds as $rolId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['role_id' => $rolId, 'permission_id' => $permisoId],
                [],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permisoId = DB::table('permissions')->where('name', 'inbox.view')->value('id');
        if ($permisoId) {
            DB::table('role_has_permissions')->where('permission_id', $permisoId)->delete();
        }
        DB::table('permissions')->where('name', 'inbox.view')->delete();
        DB::table('settings')->whereIn('key', ['gmail_buzon', 'gmail_credenciales'])->delete();
    }
};
