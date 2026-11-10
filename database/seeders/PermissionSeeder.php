<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['users.view', 'Ver usuarios', 'Permite listar y consultar usuarios'],
            ['users.create', 'Crear usuarios', 'Permite crear usuarios'],
            ['users.update', 'Actualizar usuarios', 'Permite actualizar usuarios'],
            ['users.delete', 'Eliminar usuarios', 'Permite eliminar usuarios'],
            ['roles.view', 'Ver roles', 'Permite listar y consultar roles'],
            ['roles.create', 'Crear roles', 'Permite crear roles'],
            ['roles.update', 'Actualizar roles', 'Permite actualizar roles'],
            ['roles.delete', 'Eliminar roles', 'Permite eliminar roles'],
            ['permissions.view', 'Ver permisos', 'Permite listar permisos'],
            ['identification-types.view', 'Ver tipos de identificacion', 'Permite listar tipos de identificacion'],
            ['identification-types.create', 'Crear tipos de identificacion', 'Permite crear tipos de identificacion'],
            ['identification-types.update', 'Actualizar tipos de identificacion', 'Permite actualizar tipos de identificacion'],
            ['identification-types.delete', 'Eliminar tipos de identificacion', 'Permite eliminar tipos de identificacion'],
            ['settings.view', 'Ver configuracion', 'Permite ver la configuracion del sistema'],
            ['settings.update', 'Actualizar configuracion', 'Permite actualizar la configuracion del sistema'],
            ['api-keys.view', 'Ver API keys', 'Permite listar y consultar credenciales API'],
            ['api-keys.create', 'Crear API keys', 'Permite crear nuevas credenciales API'],
            ['api-keys.update', 'Actualizar API keys', 'Permite actualizar y regenerar credenciales API'],
            ['api-keys.delete', 'Eliminar API keys', 'Permite eliminar credenciales API'],
            ['clinicas.view', 'Ver Clínicas', 'Permite listar y gestionar clínicas externas registradas'],
            ['solicitudes.view', 'Ver solicitudes de referencia', 'Permite acceder a la cola de solicitudes de referencia y gestionarlas'],
            ['historico.view', 'Ver historial de solicitudes', 'Permite consultar el historial de solicitudes finalizadas'],
            ['solicitudes.analytics', 'Ver analíticas de solicitudes', 'Permite ver los paneles de métricas de la cola de solicitudes (salud de la cola, especialidades)'],
            ['dashboard.view', 'Ver panel principal', 'Permite acceder al dashboard con todas las métricas y gráficos'],
            ['inbox.view', 'Ver bandeja de correo', 'Permite acceder a la bandeja de entrada con los correos del buzón de Gmail'],
        ])->each(fn (array $permission) => Permission::query()->updateOrCreate(
            [
                'name' => $permission[0],
                'guard_name' => 'web',
            ],
            [
                'display_name' => $permission[1],
                'description' => $permission[2],
            ],
        ));
    }
}
