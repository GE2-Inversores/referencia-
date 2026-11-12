<?php

namespace Tests\Feature;

use App\Models\Clinica;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditarClinicaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('user_name', 'superadmin')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(?string $nit): array
    {
        return [
            'nit' => $nit,
            'nombre' => 'ESE Hospital Norte',
            'email' => 'urgencias@example.com',
            'telefono' => '3173004495',
            'ciudad' => 'Puerto Tejada',
            'departamento' => 'Cauca',
            'direccion' => 'Puerto Tejada',
        ];
    }

    public function test_editar_una_clinica_no_exige_el_nit(): void
    {
        $staff = $this->staff();
        $clinica = Clinica::create(['nit' => null, 'nombre' => 'ESE Hospital Norte', 'email' => 'urgencias@example.com']);

        $this->actingAs($staff)->putJson("/api/clinicas/{$clinica->id}", $this->datos(null))->assertOk();
        $this->actingAs($staff)->putJson("/api/clinicas/{$clinica->id}", $this->datos(''))->assertOk();

        $this->assertNull($clinica->fresh()->nit);
    }

    public function test_si_se_escribe_el_nit_se_guarda_y_no_puede_repetirse(): void
    {
        $staff = $this->staff();
        $otra = Clinica::create(['nit' => '900111222', 'nombre' => 'Otra clínica', 'email' => 'otra@example.com']);
        $clinica = Clinica::create(['nit' => null, 'nombre' => 'ESE Hospital Norte', 'email' => 'urgencias@example.com']);

        $this->actingAs($staff)->putJson("/api/clinicas/{$clinica->id}", $this->datos('900123456'))->assertOk();
        $this->assertSame('900123456', $clinica->fresh()->nit);

        $this->actingAs($staff)->putJson("/api/clinicas/{$clinica->id}", $this->datos($otra->nit))
            ->assertStatus(422)
            ->assertJsonPath('data.errors.nit.0', 'Ya existe una clínica registrada con este NIT.');
    }

    public function test_dos_clinicas_sin_nit_pueden_convivir(): void
    {
        $staff = $this->staff();
        $a = Clinica::create(['nit' => null, 'nombre' => 'Clínica A', 'email' => 'a@example.com']);
        $b = Clinica::create(['nit' => null, 'nombre' => 'Clínica B', 'email' => 'b@example.com']);

        $this->actingAs($staff)->putJson("/api/clinicas/{$a->id}", ['nombre' => 'Clínica A'] + $this->datos(null))->assertOk();
        $this->actingAs($staff)->putJson("/api/clinicas/{$b->id}", ['nombre' => 'Clínica B'] + $this->datos(null))->assertOk();
    }
}
