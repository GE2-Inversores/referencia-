<?php

namespace Database\Seeders;

use App\Models\Clinica;
use App\Models\DiagnosticoSolicitud;
use App\Models\SolicitudReferencia;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrega clínicas y solicitudes de referencia de ejemplo, con historial
 * distribuido entre enero de 2025 y hoy, para que el dashboard y los
 * reportes tengan suficiente volumen y variedad temporal para filtrar
 * por día/mes/año y comparar contra el periodo anterior.
 *
 * Es acumulativo (no borra datos existentes) y puede ejecutarse de nuevo
 * sin romper nada: los NIT de las clínicas nuevas son fijos y únicos, así
 * que una segunda corrida los omite en vez de duplicarlos.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $ciudades = [
        ['ciudad' => 'Cali', 'departamento' => 'Valle del Cauca'],
        ['ciudad' => 'Bogotá', 'departamento' => 'Cundinamarca'],
        ['ciudad' => 'Medellín', 'departamento' => 'Antioquia'],
        ['ciudad' => 'Barranquilla', 'departamento' => 'Atlántico'],
        ['ciudad' => 'Palmira', 'departamento' => 'Valle del Cauca'],
        ['ciudad' => 'Buga', 'departamento' => 'Valle del Cauca'],
        ['ciudad' => 'Tuluá', 'departamento' => 'Valle del Cauca'],
        ['ciudad' => 'Popayán', 'departamento' => 'Cauca'],
        ['ciudad' => 'Pasto', 'departamento' => 'Nariño'],
        ['ciudad' => 'Pereira', 'departamento' => 'Risaralda'],
    ];

    private array $epsList = [
        'SURA EVENTO', 'Sura', 'COMFENALCO', 'ASMET SALUD EVENTO', 'SALUD TOTAL',
        'Sanitas', 'Nueva EPS', 'Coosalud', 'Compensar', 'Famisanar', 'Aliansalud', 'Mutual Ser',
    ];

    private array $especialidades = [
        'UROLOGIA', 'CARDIOLOGIA', 'MEDICINA GENERAL', 'Dermatologia', 'Neurologia',
        'Ortopedia', 'Pediatria', 'Ginecologia', 'Oftalmologia', 'Otorrinolaringologia',
        'Gastroenterologia', 'Neumologia', 'Endocrinologia', 'Psiquiatria', 'Reumatologia',
        'Oncologia', 'CIRUGIA DE MIEMBRO SUPERIOR', 'GRUPO DE FALLA INTESTINAL',
    ];

    private array $municipios = ['candelaria', 'palmira', 'pradera', 'Barranquilla', 'Cali', 'Buga', 'Popayán', 'Pasto'];

    private array $nombresM = ['jorge', 'carlos', 'andres', 'juan', 'luis', 'miguel', 'santiago', 'daniel', 'oscar', 'ricardo', 'fernando', 'jose', 'alejandro', 'diego', 'sebastian'];

    private array $nombresF = ['maria', 'laura', 'sofia', 'valentina', 'camila', 'natalia', 'paula', 'diana', 'andrea', 'carolina', 'juliana', 'martha', 'sandra', 'lucia', 'gabriela'];

    private array $apellidos = ['gomez', 'rodriguez', 'martinez', 'lopez', 'garcia', 'perez', 'gonzalez', 'sanchez', 'ramirez', 'torres', 'herrera', 'castro', 'ortiz', 'rojas', 'vargas', 'moreno', 'jimenez', 'diaz', 'munoz', 'pardo'];

    private array $diagnosticosPool = [
        ['J189', 'Neumonía, no especificada'],
        ['I10X', 'Hipertensión esencial (primaria)'],
        ['E119', 'Diabetes mellitus tipo 2 sin complicaciones'],
        ['N390', 'Infección de vías urinarias, sitio no especificado'],
        ['K35.9', 'Apendicitis aguda, no especificada'],
        ['M545', 'Lumbago no especificado'],
        ['R073', 'Otro dolor en el pecho'],
        ['O80.9', 'Parto único espontáneo, no especificado'],
        ['S52.5', 'Fractura de la extremidad distal del radio'],
        ['G43.9', 'Migraña, no especificada'],
        ['J45.9', 'Asma, no especificada'],
        ['K29.7', 'Gastritis, no especificada'],
        ['H52.1', 'Miopía'],
        ['F32.9', 'Episodio depresivo, no especificado'],
        ['C509', 'Tumor maligno de la mama, no especificada'],
    ];

    public function run(): void
    {
        $clinicas = $this->crearClinicas();
        $this->crearSolicitudes($clinicas);
    }

    /**
     * @return Collection<int, Clinica>
     */
    private function crearClinicas()
    {
        $nuevas = [
            ['nit' => '800.111.222-1', 'nombre' => 'Clínica San Rafael', 'ciudad' => 0, 'estado' => 'activa'],
            ['nit' => '800.222.333-2', 'nombre' => 'Hospital Universitario del Valle', 'ciudad' => 1, 'estado' => 'activa'],
            ['nit' => '800.333.444-3', 'nombre' => 'Clínica Nuestra Señora de los Remedios', 'ciudad' => 2, 'estado' => 'activa'],
            ['nit' => '800.444.555-4', 'nombre' => 'Clínica del Norte', 'ciudad' => 3, 'estado' => 'activa'],
            ['nit' => '800.555.666-5', 'nombre' => 'Centro Médico Imbanaco Sede Norte', 'ciudad' => 0, 'estado' => 'activa'],
            ['nit' => '800.666.777-6', 'nombre' => 'Clínica Farallones', 'ciudad' => 0, 'estado' => 'activa'],
            ['nit' => '800.777.888-7', 'nombre' => 'ESE Hospital Departamental de Buga', 'ciudad' => 5, 'estado' => 'activa'],
            ['nit' => '800.888.999-8', 'nombre' => 'Clínica de Occidente', 'ciudad' => 6, 'estado' => 'pendiente'],
            ['nit' => '800.999.000-9', 'nombre' => 'Clínica San Francisco', 'ciudad' => 7, 'estado' => 'pendiente'],
            ['nit' => '801.111.222-1', 'nombre' => 'Clínica Rehabilitar IPS', 'ciudad' => 8, 'estado' => 'rechazada'],
            ['nit' => '801.222.333-2', 'nombre' => 'Centro Médico La Sagrada Familia', 'ciudad' => 9, 'estado' => 'activa'],
        ];

        foreach ($nuevas as $i => $c) {
            $ubicacion = $this->ciudades[$c['ciudad']];
            Clinica::firstOrCreate(
                ['nit' => $c['nit']],
                [
                    'nombre' => $c['nombre'],
                    'razon_social' => $c['nombre'].' S.A.S.',
                    'email' => 'contacto'.($i + 1).'@'.str()->slug($c['nombre'], '').'.com',
                    'telefono' => '3'.random_int(100000000, 999999999),
                    'direccion' => 'Calle '.random_int(1, 99).' #'.random_int(1, 99).'-'.random_int(1, 99),
                    'ciudad' => $ubicacion['ciudad'],
                    'departamento' => $ubicacion['departamento'],
                    'representante_legal' => ucfirst($this->nombresM[array_rand($this->nombresM)]).' '.ucfirst($this->apellidos[array_rand($this->apellidos)]),
                    'cedula_representante' => (string) random_int(10000000, 99999999),
                    'is_active' => $c['estado'] === 'activa',
                    'estado' => $c['estado'],
                    'motivo_rechazo' => $c['estado'] === 'rechazada' ? 'Documentación incompleta: falta certificado de habilitación vigente.' : null,
                ]
            );
        }

        return Clinica::where('estado', 'activa')->get();
    }

    private function crearSolicitudes($clinicasActivas): void
    {
        if (SolicitudReferencia::count() >= 500) {
            // Ya se sembraron suficientes solicitudes en una corrida anterior.
            return;
        }

        $desde = Carbon::create(2025, 1, 1);
        $hasta = Carbon::now();
        $totalDias = $desde->diffInDays($hasta);

        $totalSolicitudes = 850;

        for ($i = 0; $i < $totalSolicitudes; $i++) {
            // Sesgar hacia fechas recientes: raíz cuadrada de un valor aleatorio
            // uniforme concentra más registros cerca de "hoy" que al inicio.
            $offset = (int) round((1 - sqrt(1 - random_int(0, 10000) / 10000)) * $totalDias);
            $fecha = $desde->copy()->addDays($totalDias - $offset);

            $esReciente = $fecha->diffInDays($hasta) <= 14;
            $estado = $this->elegirEstado($esReciente);

            $genero = random_int(0, 1) === 0 ? 'M' : 'F';
            $nombre = $genero === 'M'
                ? $this->nombresM[array_rand($this->nombresM)]
                : $this->nombresF[array_rand($this->nombresF)];
            $apellido1 = $this->apellidos[array_rand($this->apellidos)];
            $apellido2 = $this->apellidos[array_rand($this->apellidos)];

            $horaCreacion = sprintf('%02d:%02d:00', random_int(6, 22), random_int(0, 59));
            $clinica = $clinicasActivas->random();
            $especialidad = $this->especialidades[array_rand($this->especialidades)];
            [$cie10, $descDiagnostico] = $this->diagnosticosPool[array_rand($this->diagnosticosPool)];

            $data = [
                'clinica_id' => $clinica->id,
                'fecha' => $fecha->toDateString(),
                'hora' => $horaCreacion,
                'primer_nombre' => $nombre,
                'segundo_nombre' => random_int(0, 1) ? $this->apellidos[array_rand($this->apellidos)] : null,
                'primer_apellido' => $apellido1,
                'segundo_apellido' => $apellido2,
                'genero' => $genero,
                'edad' => random_int(1, 92),
                'tipo_documento' => 'CC',
                'numero_documento' => (string) random_int(1000000000, 1199999999),
                'eps' => $this->epsList[array_rand($this->epsList)],
                'diagnostico' => $descDiagnostico,
                'municipio_capita' => $this->municipios[array_rand($this->municipios)],
                'especialidad_requerida' => $especialidad,
                'servicio_ubicacion_actual' => ['UCI', 'URGENCIAS', 'HOSPITALIZACION'][array_rand(['UCI', 'URGENCIAS', 'HOSPITALIZACION'])],
                'servicio_remision' => ['HOSPITALIZACION', 'URGENCIAS', 'UCI', 'UCIN'][array_rand(['HOSPITALIZACION', 'URGENCIAS', 'UCI', 'UCIN'])],
                'quien_remitente' => null,
                'telefono_contacto' => null,
                'correo_contacto' => null,
                'resumen_historia_clinica' => 'Paciente remitido por '.strtolower($especialidad).' para valoración y manejo especializado.',
                'via_contacto' => random_int(0, 1) ? 'TELEFONICA' : 'EMAIL',
                'gestante' => $genero === 'F' && random_int(0, 9) === 0,
                'condicion_especial' => null,
                'observaciones' => null,
                'estado' => $estado,
                'created_at' => $fecha->copy()->setTimeFromTimeString($horaCreacion),
                'updated_at' => $fecha->copy()->setTimeFromTimeString($horaCreacion),
            ];

            if (in_array($estado, ['completado', 'en_espera'], true)) {
                $data['hora_respuesta'] = $this->horaRespuestaDesde($horaCreacion);
                $data['nombre_quien_responde'] = ucfirst($this->nombresF[array_rand($this->nombresF)]);
                $data['numero_ingreso'] = random_int(100000, 999999);
            }

            if ($estado === 'negado') {
                $data['hora_respuesta'] = $this->horaRespuestaDesde($horaCreacion);
                $data['nombre_quien_responde'] = ucfirst($this->nombresF[array_rand($this->nombresF)]);
                $data['motivo_negacion'] = ['No cumple criterios de remisión', 'Cupo no disponible en la especialidad', 'Paciente ya cuenta con atención en su red', 'Documentación clínica incompleta'][array_rand([0, 1, 2, 3])];
            }

            $solicitud = SolicitudReferencia::create($data);

            if (in_array($estado, ['en_espera', 'completado'], true)) {
                $solicitud->update(['codigo_aceptacion' => 'REF'.str_pad((string) $solicitud->id, 8, '0', STR_PAD_LEFT)]);
            }

            DiagnosticoSolicitud::create([
                'solicitud_referencia_id' => $solicitud->id,
                'codigo_cie10' => $cie10,
                'descripcion' => $descDiagnostico,
            ]);
        }
    }

    /** Genera una hora de respuesta posterior a la hora de creación, dentro del mismo día. */
    private function horaRespuestaDesde(string $horaCreacion): string
    {
        $creacion = Carbon::createFromFormat('H:i:s', $horaCreacion);
        $limite = Carbon::createFromFormat('H:i:s', '23:59:00');
        $minutosDisponibles = max(1, $creacion->diffInMinutes($limite));
        $offset = random_int(5, (int) min(600, $minutosDisponibles));

        return $creacion->copy()->addMinutes($offset)->format('H:i:s');
    }

    private function elegirEstado(bool $esReciente): string
    {
        $roll = random_int(1, 100);

        if ($esReciente) {
            return match (true) {
                $roll <= 25 => 'pendiente',
                $roll <= 65 => 'en_espera',
                $roll <= 90 => 'completado',
                default => 'negado',
            };
        }

        return match (true) {
            $roll <= 25 => 'en_espera',
            $roll <= 80 => 'completado',
            default => 'negado',
        };
    }
}
