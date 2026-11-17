<?php

namespace App\Providers;

use App\Http\Controllers\Api\DashboardController;
use App\Models\Clinica;
use App\Models\SolicitudReferencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->invalidarCacheDelDashboard();

        $hotFile = public_path('hot');

        if (! File::exists($hotFile)) {
            return;
        }

        $hotUrl = trim(File::get($hotFile));
        $host = parse_url($hotUrl, PHP_URL_HOST);

        if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return;
        }

        try {
            $isViteAvailable = Http::connectTimeout(1)
                ->timeout(1)
                ->get(rtrim($hotUrl, '/').'/@vite/client')
                ->successful();
        } catch (\Throwable) {
            $isViteAvailable = false;
        }

        if (! $isViteAvailable) {
            File::delete($hotFile);
        }
    }

    /**
     * Las estadísticas del dashboard se guardan unos segundos: cualquier cambio en las
     * solicitudes, clínicas o usuarios las descarta para que no se muestren números viejos.
     */
    private function invalidarCacheDelDashboard(): void
    {
        $invalidar = fn () => DB::afterCommit(DashboardController::invalidarCache(...));

        foreach ([SolicitudReferencia::class, Clinica::class] as $modelo) {
            $modelo::saved($invalidar);
            $modelo::deleted($invalidar);
        }

        // Los usuarios solo aportan el total y los activos: iniciar sesión (que también los guarda) no debe vaciar la caché.
        User::created($invalidar);
        User::deleted($invalidar);
        User::updated(fn (User $usuario) => $usuario->wasChanged('is_active') ? $invalidar() : null);
    }
}
