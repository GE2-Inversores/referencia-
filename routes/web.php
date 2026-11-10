<?php

use App\Http\Controllers\Api\ApiCredentialController;
use App\Http\Controllers\Api\Clinica\LoginExternoController;
use App\Http\Controllers\Api\Clinica\NotificationController as NotificationExternoController;
use App\Http\Controllers\Api\Clinica\SolicitudReferenciaController as SolicitudReferenciaExternoController;
use App\Http\Controllers\Api\ClinicaController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IdentificationTypeController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\InformeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SolicitudReferenciaController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Named login route required by Laravel's auth redirect mechanism
Route::get('/login', fn () => view('welcome'))->name('login');

Route::prefix('api')->name('api.')->group(function (): void {

    // ── Login externo para clínicas (sin Sanctum) ────────────────────────────
    Route::prefix('externo')->name('externo.')->group(function () {
        Route::post('buscar-clinica', [LoginExternoController::class, 'buscarClinica'])->name('buscar-clinica');
        Route::post('solicitar-acceso', [LoginExternoController::class, 'solicitarAcceso'])->middleware('throttle:otp-request')->name('solicitar-acceso');
        Route::post('verificar-otp', [LoginExternoController::class, 'verificarOtp'])->middleware('throttle:otp-verify')->name('verificar-otp');
        Route::post('verificar-magic-link', [LoginExternoController::class, 'verificarMagicLink'])->name('verificar-magic-link');
        Route::post('registro', [LoginExternoController::class, 'registro'])->name('registro');
        Route::get('clinica', [LoginExternoController::class, 'clinicaActual'])->name('clinica');
        Route::post('logout', [LoginExternoController::class, 'logout'])->name('logout');
        Route::get('solicitudes', [SolicitudReferenciaExternoController::class, 'index'])->name('solicitudes.index');
        Route::get('diagnosticos-cie10', [SolicitudReferenciaExternoController::class, 'buscarDiagnosticosCie10'])->middleware('throttle:60,1')->name('diagnosticos-cie10');
        Route::get('ciudades-gomedisys', [SolicitudReferenciaExternoController::class, 'listarCiudadesGomedisys'])->middleware('throttle:60,1')->name('ciudades-gomedisys');
        Route::get('clinicas', [SolicitudReferenciaExternoController::class, 'listarClinicas'])->middleware('throttle:60,1')->name('clinicas.lista');
        Route::post('clinicas', [SolicitudReferenciaExternoController::class, 'registrarClinica'])->middleware('throttle:10,1')->name('clinicas.registro');
        Route::post('solicitudes', [SolicitudReferenciaExternoController::class, 'store'])->middleware('throttle:20,1')->name('solicitudes.store');
        Route::get('evoluciones', [SolicitudReferenciaExternoController::class, 'evoluciones'])->middleware('throttle:60,1')->name('evoluciones.index');
        Route::post('solicitudes/{id}/evoluciones', [SolicitudReferenciaExternoController::class, 'agregarEvolucion'])->middleware('throttle:30,1')->name('evoluciones.store');
        Route::get('solicitudes/{id}', [SolicitudReferenciaExternoController::class, 'show'])->name('solicitudes.show');
        Route::get('solicitudes/{solicitudId}/adjuntos/{adjuntoId}/descargar', [SolicitudReferenciaExternoController::class, 'descargarAdjunto'])->name('solicitudes.adjuntos.descargar');

        Route::get('notificaciones', [NotificationExternoController::class, 'index'])->name('notificaciones.index');
        Route::get('notificaciones/unread-count', [NotificationExternoController::class, 'unreadCount'])->name('notificaciones.unread-count');
        Route::patch('notificaciones/{notification}/read', [NotificationExternoController::class, 'markAsRead'])->name('notificaciones.read');
        Route::patch('notificaciones/read-all', [NotificationExternoController::class, 'markAllAsRead'])->name('notificaciones.read-all');
        Route::delete('notificaciones/{notification}', [NotificationExternoController::class, 'destroy'])->name('notificaciones.destroy');
        Route::delete('notificaciones', [NotificationExternoController::class, 'clearAll'])->name('notificaciones.clear-all');
    });
    // ────────────────────────────────────────────────────────────────────────

    Route::get('user/password-policy', [CurrentUserController::class, 'passwordPolicy'])->name('user.password-policy');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        // La cola de solicitudes también consulta este endpoint para su panel
        // lateral de métricas — por eso acepta ambos permisos.
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats')->middleware('permission:dashboard.view|solicitudes.view');
        Route::get('search', [DashboardController::class, 'search'])->name('search');

        Route::get('informes', [InformeController::class, 'index'])->name('informes.index')->middleware('permission:dashboard.view');
        Route::get('informes/exportar', [InformeController::class, 'exportar'])->name('informes.exportar')->middleware('permission:dashboard.view');

        Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index')->middleware('permission:inbox.view');
        Route::get('inbox/no-leidos', [InboxController::class, 'noLeidos'])->name('inbox.no-leidos')->middleware('permission:inbox.view');
        Route::post('inbox/sincronizar', [InboxController::class, 'sincronizar'])->name('inbox.sincronizar')->middleware('permission:inbox.view');
        Route::get('inbox/{mensaje}', [InboxController::class, 'show'])->name('inbox.show')->whereNumber('mensaje')->middleware('permission:inbox.view');
        Route::post('inbox/{mensaje}/solicitud', [InboxController::class, 'crearSolicitud'])->name('inbox.solicitud')->whereNumber('mensaje')->middleware('permission:inbox.view|solicitudes.view');
        Route::post('inbox/{mensaje}/negar', [InboxController::class, 'negar'])->name('inbox.negar')->whereNumber('mensaje')->middleware('permission:inbox.view|solicitudes.view');

        Route::get('user', CurrentUserController::class)->name('user');
        Route::put('user/profile', [CurrentUserController::class, 'updateProfile'])->name('user.profile.update');
        Route::put('user/password', [CurrentUserController::class, 'updatePassword'])->name('user.password.update');

        Route::post('users/get-all', [UserController::class, 'getAll'])->name('users.get-all')->middleware('permission:users.view');
        Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('permission:users.update');
        Route::get('users/{user}/audits', [UserController::class, 'audits'])->name('users.audits')->middleware('permission:users.view');
        Route::apiResource('users', UserController::class)->except(['index'])
            ->middlewareFor('store', 'permission:users.create')
            ->middlewareFor('show', 'permission:users.view')
            ->middlewareFor(['update', 'put', 'patch'], 'permission:users.update')
            ->middlewareFor('destroy', 'permission:users.delete');

        Route::post('identification-types/get-all', [IdentificationTypeController::class, 'getAll'])->name('identification-types.get-all')->middleware('permission:identification-types.view');
        Route::apiResource('identification-types', IdentificationTypeController::class)->except(['index'])
            ->middlewareFor('store', 'permission:identification-types.create')
            ->middlewareFor('show', 'permission:identification-types.view')
            ->middlewareFor(['update', 'put', 'patch'], 'permission:identification-types.update')
            ->middlewareFor('destroy', 'permission:identification-types.delete');

        Route::post('roles/get-all', [RoleController::class, 'getAll'])->name('roles.get-all')->middleware('permission:roles.view');
        Route::apiResource('roles', RoleController::class)->except(['index'])
            ->middlewareFor('store', 'permission:roles.create')
            ->middlewareFor('show', 'permission:roles.view')
            ->middlewareFor(['update', 'put', 'patch'], 'permission:roles.update')
            ->middlewareFor('destroy', 'permission:roles.delete');

        Route::post('permissions/get-all', [PermissionController::class, 'getAll'])->name('permissions.get-all')->middleware('permission:permissions.view');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index')->middleware('permission:settings.view');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update')->middleware('permission:settings.update');
        Route::post('settings/probar-correo', [SettingController::class, 'probarCorreo'])->name('settings.probar-correo')->middleware('permission:settings.update');

        Route::get('api-credentials', [ApiCredentialController::class, 'index'])->name('api-credentials.index')->middleware('permission:api-keys.view');
        Route::post('api-credentials', [ApiCredentialController::class, 'store'])->name('api-credentials.store')->middleware('permission:api-keys.create');
        Route::put('api-credentials/{id}', [ApiCredentialController::class, 'update'])->name('api-credentials.update')->middleware('permission:api-keys.update');
        Route::delete('api-credentials/{id}', [ApiCredentialController::class, 'destroy'])->name('api-credentials.destroy')->middleware('permission:api-keys.delete');
        Route::post('api-credentials/{id}/regenerate', [ApiCredentialController::class, 'regenerate'])->name('api-credentials.regenerate')->middleware('permission:api-keys.update');
        Route::patch('api-credentials/{id}/toggle-status', [ApiCredentialController::class, 'toggleStatus'])->name('api-credentials.toggle-status')->middleware('permission:api-keys.update');
        Route::post('api-credentials/{id}/revoke', [ApiCredentialController::class, 'revoke'])->name('api-credentials.revoke')->middleware('permission:api-keys.delete');
        Route::get('api-credentials/{id}/logs', [ApiCredentialController::class, 'logs'])->name('api-credentials.logs')->middleware('permission:api-keys.view');
        Route::get('api-credentials/{id}/stats', [ApiCredentialController::class, 'stats'])->name('api-credentials.stats')->middleware('permission:api-keys.view');

        Route::get('clinicas', [ClinicaController::class, 'index'])->name('clinicas.index')->middleware('permission:clinicas.view');
        Route::post('clinicas', [ClinicaController::class, 'store'])->name('clinicas.store')->middleware('permission:clinicas.view');
        Route::put('clinicas/{clinica}', [ClinicaController::class, 'update'])->name('clinicas.update')->middleware('permission:clinicas.view');
        Route::post('clinicas/carga-masiva', [ClinicaController::class, 'cargaMasiva'])->name('clinicas.carga-masiva')->middleware('permission:clinicas.view');
        Route::post('clinicas/{clinica}/aprobar', [ClinicaController::class, 'aprobar'])->name('clinicas.aprobar')->middleware('permission:clinicas.view');
        Route::post('clinicas/{clinica}/rechazar', [ClinicaController::class, 'rechazar'])->name('clinicas.rechazar')->middleware('permission:clinicas.view');
        Route::post('clinicas/{clinica}/reactivar', [ClinicaController::class, 'reactivar'])->name('clinicas.reactivar')->middleware('permission:clinicas.view');

        Route::get('solicitudes-referencia', [SolicitudReferenciaController::class, 'index'])->name('solicitudes-referencia.index')->middleware('permission:solicitudes.view');
        Route::get('solicitudes-referencia/historico', [SolicitudReferenciaController::class, 'historico'])->name('solicitudes-referencia.historico')->middleware('permission:historico.view');
        // El detalle también lo abre el dashboard (modal) y el historial.
        Route::get('solicitudes-referencia/{solicitud}', [SolicitudReferenciaController::class, 'show'])->name('solicitudes-referencia.show')->middleware('permission:solicitudes.view|historico.view|dashboard.view');
        Route::post('solicitudes-referencia/{solicitud}/aceptar', [SolicitudReferenciaController::class, 'aceptar'])->name('solicitudes-referencia.aceptar')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/negar', [SolicitudReferenciaController::class, 'negar'])->name('solicitudes-referencia.negar')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/pendiente', [SolicitudReferenciaController::class, 'pendiente'])->name('solicitudes-referencia.pendiente')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/cambiar-estado', [SolicitudReferenciaController::class, 'cambiarEstado'])->name('solicitudes-referencia.cambiar-estado')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/notas', [SolicitudReferenciaController::class, 'agregarNota'])->name('solicitudes-referencia.notas')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/completado', [SolicitudReferenciaController::class, 'completado'])->name('solicitudes-referencia.completado')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/solicitar-evolucion', [SolicitudReferenciaController::class, 'solicitarEvolucion'])->name('solicitudes-referencia.solicitar-evolucion')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/no-ingreso', [SolicitudReferenciaController::class, 'noIngreso'])->name('solicitudes-referencia.no-ingreso')->middleware('permission:solicitudes.view|historico.view');
        Route::post('solicitudes-referencia/{solicitud}/consultar-ingreso', [SolicitudReferenciaController::class, 'consultarIngreso'])->name('solicitudes-referencia.consultar-ingreso')->middleware('permission:solicitudes.view|historico.view');
        Route::get('solicitudes-referencia/{solicitud}/adjuntos/{adjunto}/descargar', [SolicitudReferenciaController::class, 'descargarAdjunto'])->name('solicitudes-referencia.adjuntos.descargar')->middleware('permission:solicitudes.view|historico.view|dashboard.view');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::delete('notifications', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    });
});

Route::view('/{any}', 'welcome')
    ->where('any', '^(?!api|sanctum).*$');
