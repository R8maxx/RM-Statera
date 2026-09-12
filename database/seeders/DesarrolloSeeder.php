<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\Models\RevisionInventario;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\VincularEvidencia;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos con los que arrancar en local.
 *
 * Todo sintético: ni un dato real de ningún cliente. Necesita el catálogo ya
 * importado (`php artisan catalogo:importar`); sin marcos no hay nada que
 * generar.
 */
class DesarrolloSeeder extends Seeder
{
    public function run(): void
    {
        $organizacion = Organizacion::query()->firstOrCreate(
            ['cif' => 'B00000000'],
            [
                'nombre' => 'Organización de pruebas',
                'sector' => 'Servicios digitales',
                'sujeto_obligado_ens' => false,
                'proveedor_sector_publico' => true,
                'activa' => true,
            ],
        );

        // Sin contexto no se escribe ni una fila de datos propios: la política de
        // RLS deniega por defecto y el scope de Eloquent tampoco deja pasar.
        app(ContextoOrganizacion::class)->establecer($organizacion);

        // Los roles se siembran antes que los usuarios: sin ellos, asignar uno
        // falla, y con `teams = true` cada organización tiene los suyos.
        app(SembrarRoles::class)->paraOrganizacion($organizacion);

        $this->usuario(
            $organizacion,
            'responsable@statera.test',
            'Responsable de seguridad',
            Rol::ResponsableSeguridad,
        );

        // Los otros dos existen para poder comprobar de verdad qué ve cada rol:
        // un permiso que nadie ejerce no está probado.
        $this->usuario($organizacion, 'tecnico@statera.test', 'Técnica de sistemas', Rol::Tecnico);
        $this->usuario($organizacion, 'auditor@statera.test', 'Auditor externo', Rol::Auditor);

        $marco = Marco::query()->where('codigo', 'ENS-RD311-2022')->first();

        if ($marco === null) {
            $this->command->warn('Catálogo sin importar: ejecuta `php artisan catalogo:importar` y repite el seeder.');

            return;
        }

        $sistema = Sistema::query()->firstOrCreate(
            ['codigo' => 'SIS-0001'],
            [
                'marco_id' => $marco->id,
                'nombre' => 'Plataforma de servicios internos',
                'descripcion' => 'Sistema de ejemplo para desarrollo.',
                'alcance_declarado' => 'Servicios internos alojados en la nube corporativa.',
            ],
        );

        /*
         * Cinco dimensiones en bajo: categoría básica, que es el objetivo de la
         * fase actual.
         *
         * Con justificación, y no por adorno: la Declaración de Aplicabilidad
         * del ENS imprime la derivación dimensión a dimensión, y una demo con
         * cinco «Sin justificar» enseña justamente lo que un auditor rechaza.
         */
        $justificaciones = [
            'C' => 'Datos de carácter personal de categoría básica; no hay categorías especiales.',
            'I' => 'Una alteración obligaría a rehacer trámites, sin efectos sobre terceros.',
            'D' => 'El servicio admite una interrupción de un día laborable sin perjuicio apreciable.',
            'A' => 'La identidad se verifica en el alta presencial; el trámite no produce efectos jurídicos.',
            'T' => 'Se registra la actividad para poder reconstruir un trámite a petición del interesado.',
        ];

        foreach (Dimension::cases() as $dimension) {
            ValoracionDimension::query()->updateOrCreate(
                ['sistema_id' => $sistema->id, 'dimension' => $dimension->value],
                [
                    'nivel' => NivelDimension::Bajo->value,
                    'justificacion' => $justificaciones[$dimension->value],
                ],
            );
        }

        app(GeneradorImplantaciones::class)->generar($sistema->fresh());

        $this->command->info(sprintf(
            'Sistema %s: %d implantaciones.',
            $sistema->codigo,
            $sistema->implantaciones()->count(),
        ));

        $this->evidenciaDeEjemplo($sistema);
        $this->inventarioDeEjemplo($sistema);
        $this->planDeAccionDeEjemplo($sistema);
        $this->documentoDeEjemplo($sistema);
    }

    /**
     * El registro de la Declaración de Aplicabilidad, sin generar.
     *
     * Se deja sin versiones a propósito: generar el PDF necesita el contenedor
     * de Gotenberg levantado y el disco de documentos accesible, y un seeder que
     * falle porque falta un servicio de apoyo no sirve para arrancar el entorno.
     * El botón «Generar borrador» de la ficha es el siguiente paso, y es
     * justamente lo que conviene ver funcionar.
     */
    private function documentoDeEjemplo(Sistema $sistema): void
    {
        $documento = Documento::query()->firstOrCreate(
            ['codigo' => 'DDA-ENS-01'],
            [
                'sistema_id' => $sistema->id,
                'titulo' => 'Declaración de Aplicabilidad',
                'tipo' => TipoDocumento::DdaEns->value,
            ],
        );

        $this->command->info(sprintf(
            'Documento %s listo para generar (php artisan documentos:generar %s --sync).',
            $documento->codigo,
            $documento->codigo,
        ));
    }

    /**
     * Una cadena de tres saltos, que es lo mínimo para que la propagación de la
     * valoración se vea sin montarla a mano:
     *
     *     SRV-0001 (servicio, disponibilidad alta)
     *         └── depende de APP-0001 (software, sin valorar)
     *                 └── depende de BBDD-0001 (datos, confidencialidad media)
     *
     * La base de datos hereda «alto» en disponibilidad del servicio que está dos
     * saltos por encima, y conserva su «medio» propio en confidencialidad. Ese
     * es exactamente el activo que una hoja de cálculo deja infravalorado.
     */
    private function inventarioDeEjemplo(Sistema $sistema): void
    {
        $servicio = $this->activo('SRV-0001', 'Sede electrónica interna', TipoActivo::Servicios, [
            'subtipo' => 'Servicio web',
            'valor_d' => NivelDimension::Alto->value,
            'valor_t' => NivelDimension::Bajo->value,
            'ubicacion' => 'Nube corporativa',
            'clasificacion' => Clasificacion::UsoInterno->value,
            'ultima_revision' => Carbon::today()->subMonth()->toDateString(),
        ]);

        $aplicacion = $this->activo('APP-0001', 'Gestor de expedientes', TipoActivo::Software, [
            'subtipo' => 'Aplicación web',
            'ubicacion' => 'Nube corporativa',
            'sistema_operativo' => 'Ubuntu 24.04 LTS',
            'fin_soporte_so' => '2029-05-31',
            'identificador' => 'i-0sintetico00000001',
            // Nadie lo ha comprobado: es una pregunta abierta, no un
            // incumplimiento. El indicador los cuenta aparte.
            'copia_seguridad' => EstadoControl::PorConfirmar->value,
            'ultima_revision' => Carbon::today()->subMonth()->toDateString(),
        ]);

        $baseDeDatos = $this->activo('BBDD-0001', 'Base de datos de expedientes', TipoActivo::Datos, [
            'subtipo' => 'RDS MySQL',
            'valor_c' => NivelDimension::Medio->value,
            'ubicacion' => 'Nube corporativa',
            'identificador' => 'arn:aws:rds:eu-west-1:000000000000:db:sintetica',
            'clasificacion' => Clasificacion::Confidencial->value,
            // Sin cifrar y con la información clasificada: el activo que ilumina
            // dos indicadores a la vez y el que hay que arreglar primero.
            'cifrado' => EstadoControl::No->value,
            'copia_seguridad' => EstadoControl::Si->value,
        ]);

        // Un activo suelto y ya retirado sin constancia del borrado: el caso que
        // la ficha tiene que señalar como pendiente, no dar por cerrado.
        $this->activo('HW-0001', 'Portátil de la técnica de sistemas', TipoActivo::Hardware, [
            'subtipo' => 'Portátil',
            'estado_ciclo_vida' => EstadoCicloVida::Retirado->value,
            'fecha_baja' => Carbon::today()->subMonths(2)->toDateString(),
            'valor_c' => NivelDimension::Bajo->value,
        ]);

        // Y uno en uso con el sistema operativo fuera de soporte, para que el
        // aviso de obsolescencia se vea sin tener que fabricarlo.
        $this->activo('HW-0002', 'Servidor de la sala técnica', TipoActivo::Hardware, [
            'subtipo' => 'Servidor físico',
            'marca_modelo' => 'Genérico rack 1U',
            'sistema_operativo' => 'Ubuntu 20.04 LTS',
            'fin_soporte_so' => '2025-05-31',
            'fin_garantia' => Carbon::today()->subMonths(6)->toDateString(),
            'identificador' => 'SN-SINTETICO-0001',
            'ubicacion' => 'Sala técnica',
            'cifrado' => EstadoControl::No->value,
            'copia_seguridad' => EstadoControl::Si->value,
            'valor_d' => NivelDimension::Medio->value,
        ]);

        $vincular = app(RegistrarDependencia::class);
        $vincular->vincular($servicio, $aplicacion, 'La sede se sirve desde el gestor de expedientes.');
        $vincular->vincular($aplicacion, $baseDeDatos, 'Toda la información del gestor vive aquí.');

        foreach ([$servicio, $aplicacion, $baseDeDatos] as $activo) {
            $activo->sistemas()->syncWithoutDetaching([
                $sistema->id => ['organizacion_id' => $activo->organizacion_id],
            ]);
        }

        RevisionInventario::query()->firstOrCreate(
            ['alcance' => 'Alta inicial del inventario de ejemplo'],
            [
                'fecha' => Carbon::today()->subMonth(),
                'altas' => Activo::query()->count(),
                'bajas' => 0,
                'desviaciones' => 'BBDD-0001 sin cifrado en reposo. HW-0002 con el sistema operativo fuera de soporte y la garantía vencida.',
                'acciones' => 'Plan de cifrado antes del próximo trimestre. Presupuestar la renovación de HW-0002.',
            ],
        );

        $this->command->info(sprintf(
            'Inventario de ejemplo: %d activos, %d dependencias y %d revisión registrada.',
            Activo::query()->count(),
            DB::table('activo_dependencias')->count(),
            RevisionInventario::query()->count(),
        ));
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function activo(string $codigo, string $nombre, TipoActivo $tipo, array $atributos = []): Activo
    {
        return Activo::query()->firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre,
                'tipo' => $tipo->value,
                'estado_ciclo_vida' => EstadoCicloVida::EnProduccion->value,
                'fecha_alta' => Carbon::today()->subYear()->toDateString(),
                ...$atributos,
            ],
        );
    }

    /** Alta idempotente de un usuario con su rol. Contraseña de desarrollo. */
    private function usuario(Organizacion $organizacion, string $email, string $nombre, Rol $rol): void
    {
        $usuario = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $nombre,
                'password' => 'contrasena-de-desarrollo',
                'organizacion_id' => $organizacion->id,
            ],
        );

        $usuario->syncRoles([$rol->value]);
    }

    /**
     * Una evidencia vinculada a dos medidas, para que el vínculo N:M se vea sin
     * tener que montarlo a mano.
     *
     * De enlace y no de fichero: el seeder no debe depender de que MinIO esté
     * levantado, y lo que se quiere enseñar es el vínculo, no la subida.
     */
    private function evidenciaDeEjemplo(Sistema $sistema): void
    {
        $evidencia = Evidencia::query()->firstOrCreate(
            ['titulo' => 'Configuración del segundo factor en el IdP'],
            [
                'tipo' => TipoEvidencia::Captura->value,
                'descripcion' => 'Captura sintética del panel de administración del proveedor de identidad.',
                'url_externa' => 'https://idp.interno.ejemplo/administracion/mfa',
                'fecha_obtencion' => Carbon::today()->subMonth(),
                'periodicidad_renovacion' => PeriodicidadRenovacion::Semestral->value,
            ],
        );

        // Las de control de acceso: es donde una captura del IdP prueba de
        // verdad algo, y suelen ser varias a la vez.
        $implantaciones = $sistema->implantaciones()
            ->whereHas('requisito', fn (Builder $consulta) => $consulta->where('codigo', 'like', 'op.acc.%'))
            ->limit(2)
            ->get();

        $vincular = app(VincularEvidencia::class);

        foreach ($implantaciones as $implantacion) {
            $vincular->vincular($evidencia, $implantacion, null, 'Prueba el mecanismo de autenticación.');
        }

        $this->command->info(sprintf(
            'Evidencia de ejemplo vinculada a %d requisitos.',
            $implantaciones->count(),
        ));
    }

    /**
     * Tres tareas que enseñan lo que hay que ver de un plan de acción: una
     * vencida, una en curso vinculada a dos requisitos de golpe y una sin
     * responsable.
     *
     * Son las tres situaciones que el panel y el aviso diario tienen que saber
     * contar, y montarlas a mano cada vez que se refresca la base cuesta más que
     * escribirlas aquí.
     */
    private function planDeAccionDeEjemplo(Sistema $sistema): void
    {
        $crear = app(CrearTarea::class);

        $accesos = $sistema->implantaciones()
            ->whereHas('requisito', fn (Builder $consulta) => $consulta->where('codigo', 'like', 'op.acc.%'))
            ->limit(2)
            ->get()
            ->all();

        if (Tarea::query()->count() > 0) {
            return;
        }

        $crear([
            'titulo' => 'Revisar la política de contraseñas y publicarla',
            'descripcion' => 'Ajustar longitud mínima y caducidad, y dejarla firmada.',
            'origen' => OrigenTarea::BrechaImplantacion->value,
            'prioridad' => PrioridadTarea::Alta->value,
            'fecha_limite' => Carbon::today()->subDays(6),
        ], null, $accesos);

        $crear([
            'titulo' => 'Contratar la revisión anual del proveedor de correo',
            'origen' => OrigenTarea::Propia->value,
            'prioridad' => PrioridadTarea::Media->value,
            'fecha_limite' => Carbon::today()->addDays(12),
            'coste_estimado' => '1800.00',
        ]);

        $crear([
            'titulo' => 'Documentar el procedimiento de alta y baja de personal',
            'origen' => OrigenTarea::Propia->value,
            'prioridad' => PrioridadTarea::Baja->value,
        ]);

        $this->command->info(sprintf('Plan de acción: %d tareas de ejemplo.', Tarea::query()->count()));
    }
}
