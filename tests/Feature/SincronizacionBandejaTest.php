<?php

namespace Tests\Feature;

use App\Models\InboxMensaje;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SincronizacionBandejaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    private function correo(array $extra = []): InboxMensaje
    {
        return InboxMensaje::create(array_merge([
            'gmail_id' => 'g'.uniqid(),
            'remitente' => 'EPS <eps@example.com>',
            'remitente_email' => 'eps@example.com',
            'asunto' => 'Remisión',
            'recibido_en' => now(),
            'leido' => false,
        ], $extra));
    }

    private function configurarBuzon(): void
    {
        Setting::set('gmail_buzon', 'referencia@example.com');
        Setting::set('gmail_password', 'abcdabcdabcdabcd');
    }

    public function test_la_limpieza_borra_pendientes_de_ayer_pero_conserva_gestionados_y_los_de_hoy(): void
    {
        $pendienteAyer = $this->correo();
        $pendienteAyer->forceFill(['created_at' => now()->subDay()])->save();

        $gestionadoAyer = $this->correo(['gestion' => 'negada', 'carpeta' => 'Negaciones']);
        $gestionadoAyer->forceFill(['created_at' => now()->subDays(3)])->save();

        // La fecha del encabezado es vieja, pero entró hoy: no debe borrarse.
        $entroHoy = $this->correo(['recibido_en' => now()->subDays(5)]);

        $this->artisan('inbox:prune')->assertSuccessful();

        $this->assertModelMissing($pendienteAyer);
        $this->assertModelExists($gestionadoAyer);
        $this->assertModelExists($entroHoy);
    }

    public function test_el_vigilante_avisa_una_sola_vez_cuando_la_bandeja_se_atrasa_y_cuando_se_recupera(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $this->configurarBuzon();

        Cache::put('inbox_ultima_sync', now()->subMinutes(40)->toIso8601String());

        $this->artisan('inbox:vigilar')->assertSuccessful();
        $this->artisan('inbox:vigilar')->assertSuccessful();

        $alertas = Notification::where('user_id', $staff->id)->where('title', 'like', '%dejó de sincronizar%')->get();
        $this->assertCount(1, $alertas);
        $this->assertSame('error', $alertas->first()->type);
        $this->assertSame('/bandeja', $alertas->first()->link);

        Cache::put('inbox_ultima_sync', now()->toIso8601String());
        $this->artisan('inbox:vigilar')->assertSuccessful();
        $this->artisan('inbox:vigilar')->assertSuccessful();

        $this->assertSame(1, Notification::where('user_id', $staff->id)->where('title', 'like', '%volvió a sincronizar%')->count());
        $this->assertFalse(Cache::has('inbox_alerta_activa'));
    }

    public function test_el_vigilante_no_hace_nada_si_el_buzon_no_esta_configurado(): void
    {
        $this->staff();
        Cache::put('inbox_ultima_sync', now()->subDays(2)->toIso8601String());

        $this->artisan('inbox:vigilar')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_el_vigilante_no_alerta_si_se_sincronizo_hace_poco(): void
    {
        $this->staff();
        $this->configurarBuzon();
        Cache::put('inbox_ultima_sync', now()->subMinutes(5)->toIso8601String());

        $this->artisan('inbox:vigilar')->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }
}
