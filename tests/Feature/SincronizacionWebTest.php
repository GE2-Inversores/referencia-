<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SincronizacionWebTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    /**
     * Sustituye el servicio de Gmail: devuelve los argumentos con que se llamó a sincronizar().
     *
     * @return array{0: MockInterface, 1: object}
     */
    private function servicio(?string $ultimaSync): array
    {
        $llamadas = new class
        {
            /** @var array<int, array<int, mixed>> */
            public array $args = [];
        };

        $mock = $this->mock(GmailService::class, function (MockInterface $m) use ($ultimaSync, $llamadas): void {
            $m->shouldReceive('configurado')->andReturn(true);
            $m->shouldReceive('ultimaSync')->andReturn($ultimaSync);
            $m->shouldReceive('sincronizar')->andReturnUsing(function (...$args) use ($llamadas): array {
                $llamadas->args[] = $args;

                return ['agregados' => 0, 'examinados' => 0];
            });
        });

        return [$mock, $llamadas];
    }

    public function test_la_bandeja_abierta_no_se_conecta_a_gmail_si_se_sincronizo_hace_poco(): void
    {
        [, $llamadas] = $this->servicio(now()->subSeconds(30)->toIso8601String());

        $this->actingAs($this->staff())
            ->postJson('/api/inbox/sincronizar')
            ->assertOk()
            ->assertJsonPath('data.agregados', 0);

        $this->assertSame([], $llamadas->args);
    }

    public function test_la_sincronizacion_automatica_atrasada_es_pequena_y_acotada_en_tiempo(): void
    {
        [, $llamadas] = $this->servicio(now()->subMinutes(5)->toIso8601String());

        $this->actingAs($this->staff())->postJson('/api/inbox/sincronizar')->assertOk();

        $this->assertCount(1, $llamadas->args);
        $this->assertContains(20, $llamadas->args[0]);
        $this->assertContains(10, $llamadas->args[0]);
    }

    public function test_el_boton_manual_sincroniza_aunque_haya_una_reciente(): void
    {
        [, $llamadas] = $this->servicio(now()->subSeconds(5)->toIso8601String());

        $this->actingAs($this->staff())->postJson('/api/inbox/sincronizar', ['auditar' => true])->assertOk();

        $this->assertCount(1, $llamadas->args);
        $this->assertContains(100, $llamadas->args[0]);
        $this->assertContains(25, $llamadas->args[0]);
    }

    public function test_el_comando_programado_se_detiene_a_los_50_segundos_por_defecto(): void
    {
        [, $llamadas] = $this->servicio(null);

        $this->artisan('inbox:sync')->assertSuccessful();

        $this->assertContains(50, $llamadas->args[0]);
    }

    public function test_el_comando_acepta_otro_tiempo_o_sin_tope(): void
    {
        [, $llamadas] = $this->servicio(null);

        $this->artisan('inbox:sync --segundos=240')->assertSuccessful();
        $this->artisan('inbox:sync --segundos=0')->assertSuccessful();

        $this->assertContains(240, $llamadas->args[0]);
        $this->assertNull(end($llamadas->args[1]));
    }
}
