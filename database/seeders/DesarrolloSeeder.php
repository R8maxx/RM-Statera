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
use App\Domain\Auditoria\CerrarAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Auditoria\RegistrarAuditoria;
use App\Domain\Auditoria\RegistrarHallazgo;
use App\Domain\Auditoria\RevisarPunto;
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
use App\Domain\Implantacion\CambiarEstado;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\NoConformidad\AbrirAccionCorrectiva;
use App\Domain\NoConformidad\CambiarEstadoNoConformidad;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Riesgo\AceptarRiesgo;
use App\Domain\Riesgo\CrearRiesgo;
use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\Models\Amenaza;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\ValorarRiesgo;
use App\Domain\Riesgo\VincularRiesgo;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\GuardarSubtareas;
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
        $this->analisisDeRiesgosDeEjemplo($sistema);
        $this->auditoriasDeEjemplo($sistema);
        $this->noConformidadesDeEjemplo($sistema);
        $this->documentoDeEjemplo($sistema);
    }

    /**
     * Dos auditorías, y cada una enseña lo que la otra no puede.
     *
     * **La cerrada es la que más vale**, y por eso va primero: es el único estado
     * que el trigger blinda —su checklist y sus hallazgos dejan de poder tocarse—
     * y el único en el que los puntos llevan congelado lo que la medida decía ese
     * día. Montarla a mano para probar el blindaje cuesta cinco pasos.
     *
     * La segunda se queda **en curso y a medias**: es el estado normal de trabajo,
     * y con ella se ve la checklist recorriéndose y la acción masiva funcionando.
     */
    private function auditoriasDeEjemplo(Sistema $sistema): void
    {
        if (Auditoria::query()->count() > 0) {
            return;
        }

        $registrarAuditoria = app(RegistrarAuditoria::class);
        $precargar = app(PrecargarChecklist::class);
        $revisar = app(RevisarPunto::class);
        $registrar = app(RegistrarHallazgo::class);
        $cerrar = app(CerrarAuditoria::class);

        // --- La del año pasado, cerrada con dos hallazgos -------------------

        $cerrada = $registrarAuditoria([
            'sistema_id' => $sistema->id,
            'codigo' => 'AUD-2025-01',
            'tipo' => TipoAuditoria::Interna->value,
            'fecha' => Carbon::today()->subMonths(8),
            'auditor' => 'Consultora externa de ejemplo',
            'alcance' => 'Muestreo de las medidas de control de acceso y de explotación.',
        ]);

        $precargar($cerrada);
        $cerrar->empezar($cerrada);

        $puntos = $cerrada->puntos()->with('implantacion.requisito')->get();

        foreach ($puntos as $indice => $punto) {
            // Dos no conformes, el resto conformes: una auditoría que no
            // encuentra nada no enseña nada.
            $revisar->marcar(
                $punto,
                $indice < 2 ? ResultadoPunto::NoConforme : ResultadoPunto::Conforme,
                $indice < 2 ? 'No se encontró constancia documental durante la revisión.' : null,
            );
        }

        foreach ($puntos->take(2) as $indice => $punto) {
            $registrar->registrar(
                $cerrada,
                $indice === 0 ? TipoHallazgo::NcMenor : TipoHallazgo::Observacion,
                $indice === 0
                    ? 'La medida está implantada pero no hay registro de su revisión periódica.'
                    : 'El procedimiento existe y no está referenciado desde la política.',
                $punto,
            );
        }

        // Y uno que no cuelga de ninguna medida, que es el caso que § 2.2 no
        // contemplaba: un hallazgo sobre el sistema de gestión.
        $registrar->registrar(
            $cerrada,
            TipoHallazgo::Observacion,
            'El programa anual de auditoría no está formalizado.',
        );

        $cerrar->cerrar(
            $cerrada,
            User::query()->where('organizacion_id', $sistema->organizacion_id)->first(),
            'Dos desviaciones menores sobre el muestreo revisado. Sin no conformidades mayores.',
        );

        // --- La de este año, a medias --------------------------------------

        $enCurso = $registrarAuditoria([
            'sistema_id' => $sistema->id,
            'codigo' => 'AUD-2026-01',
            'tipo' => TipoAuditoria::Autoevaluacion->value,
            'fecha' => Carbon::today()->subDays(10),
            'alcance' => 'Autoevaluación del ENS para la renovación de la conformidad.',
        ]);

        $precargar($enCurso);
        $cerrar->empezar($enCurso);

        foreach ($enCurso->puntos()->limit(6)->get() as $punto) {
            $revisar->marcar($punto, ResultadoPunto::Conforme);
        }

        $this->command->info(sprintf(
            'Auditorías: %s cerrada con %d hallazgos y %s en curso.',
            $cerrada->codigo,
            $cerrada->hallazgos()->count(),
            $enCurso->codigo,
        ));
    }

    /**
     * Tres no conformidades, y cada una enseña un tramo distinto de la 10.2.
     *
     * **La verificada es la que más vale**, como la auditoría cerrada: es el único
     * estado que recorre el ciclo entero —tratamiento, cierre y comprobación de
     * eficacia, con sus dos fechas y dos firmantes distintos— y montarlo a mano
     * cuesta cuatro transiciones. Va además colgada del hallazgo de la auditoría
     * cerrada, así que enseña la costura entre las dos mitades del módulo **y** el
     * doble vínculo: su acción correctiva cuenta como trabajo sobre la medida.
     *
     * La segunda se queda **pendiente de verificar**, que es el indicador que más
     * importa del registro: cerrada se lee como resuelta y no lo está.
     *
     * Y la tercera, **abierta, vencida y sin acción correctiva**, que es la que
     * enciende las dos alertas a la vez.
     */
    private function noConformidadesDeEjemplo(Sistema $sistema): void
    {
        if (NoConformidad::query()->count() > 0) {
            return;
        }

        $registrar = app(RegistrarNoConformidad::class);
        $cambiar = app(CambiarEstadoNoConformidad::class);
        $abrirAccion = app(AbrirAccionCorrectiva::class);

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnico = User::query()->where('email', 'tecnico@statera.test')->first();

        // --- La del año pasado, recorrida entera ---------------------------

        $hallazgo = Hallazgo::query()->sinTratar()->orderBy('id')->first();

        if ($hallazgo !== null) {
            $cerrada = $registrar([
                'codigo' => 'NC-2025-01',
                'origen' => OrigenNoConformidad::Auditoria->value,
                'hallazgo_id' => $hallazgo->id,
                'descripcion' => 'No hay constancia de la revisión periódica de la medida auditada.',
                'correccion_inmediata' => 'Se hizo la revisión pendiente y se dejó el acta firmada.',
                'analisis_causa_raiz' => 'El procedimiento fija la periodicidad y no dice quién la convoca, '
                    .'así que nadie la convocaba.',
                'responsable_id' => $tecnico?->id,
                'fecha_deteccion' => Carbon::today()->subMonths(8),
                'fecha_prevista' => Carbon::today()->subMonths(6),
            ], $responsable);

            $abrirAccion($cerrada, [
                'titulo' => 'Asignar la convocatoria de la revisión periódica en el procedimiento',
                'prioridad' => PrioridadTarea::Alta->value,
                'responsable_id' => $tecnico?->id,
                'fecha_limite' => Carbon::today()->subMonths(6),
                'coste_estimado' => 0,
            ], $responsable);

            $cambiar($cerrada, EstadoNoConformidad::EnTratamiento, $tecnico);
            $cambiar($cerrada, EstadoNoConformidad::Cerrada, $tecnico);

            /*
             * Y la firma la pone otro: quien ejecuta el tratamiento no verifica su
             * eficacia, que es la razón entera de que `no_conformidades.verificar`
             * sea un permiso aparte.
             */
            $cambiar(
                $cerrada,
                EstadoNoConformidad::Verificada,
                $responsable,
                'Muestreo de las dos últimas revisiones: las dos convocadas y con acta.',
            );
        }

        // --- La de este año, tratada y sin verificar ------------------------

        $pendiente = $registrar([
            'codigo' => 'NC-2026-01',
            'origen' => OrigenNoConformidad::Propia->value,
            'descripcion' => 'Dos cuentas de administración seguían activas tras la baja de sus titulares.',
            'correccion_inmediata' => 'Las dos cuentas se deshabilitaron el mismo día.',
            'analisis_causa_raiz' => 'La baja de personal no dispara ninguna revisión de accesos.',
            'responsable_id' => $tecnico?->id,
            'fecha_deteccion' => Carbon::today()->subMonths(2),
            'fecha_prevista' => Carbon::today()->addMonth(),
        ], $responsable);

        $abrirAccion($pendiente, [
            'titulo' => 'Enlazar la baja de personal con la revisión de accesos',
            'prioridad' => PrioridadTarea::Critica->value,
            'responsable_id' => $tecnico?->id,
            'fecha_limite' => Carbon::today()->addWeeks(2),
            'coste_estimado' => 900,
        ], $responsable);

        $cambiar($pendiente, EstadoNoConformidad::EnTratamiento, $tecnico);
        $cambiar($pendiente, EstadoNoConformidad::Cerrada, $tecnico);

        // --- La que enciende las alertas ------------------------------------

        $registrar([
            'codigo' => 'NC-2026-02',
            'origen' => OrigenNoConformidad::Propia->value,
            'descripcion' => 'Las copias de seguridad no se restauran nunca para comprobar que sirven.',
            'responsable_id' => null,
            'fecha_deteccion' => Carbon::today()->subMonths(3),
            'fecha_prevista' => Carbon::today()->subWeeks(3),
        ], $responsable);

        $this->command->info(sprintf(
            'No conformidades: %d registradas, de ellas %d sin cerrar y %d sin verificar.',
            NoConformidad::query()->count(),
            NoConformidad::query()->abiertas()->count(),
            NoConformidad::query()->pendientesDeVerificar()->count(),
        ));
    }

    /**
     * Cuatro riesgos, y cada uno enseña una cosa que no se ve con la tabla vacía.
     *
     * No se toca la metodología: la organización se queda con la de fábrica y sin
     * aprobar, que es el estado real de partida de cualquier cliente y el que los
     * avisos de la ficha tienen que declarar. Guardarla aquí escondería justamente
     * el aviso que hay que ver.
     *
     * Los cuatro:
     *
     * - **R-001** por encima del umbral y con el residual **sin respaldo**: se
     *   declara que baja de 12 a 4 y ninguna salvaguarda está implantada. Es el
     *   hallazgo que el módulo existe para enseñar, y montarlo a mano cada vez que
     *   se refresca la base cuesta más que escribirlo aquí.
     * - **R-002** aceptado y con la reevaluación vencida. Un riesgo firmado sigue
     *   teniendo que volver a mirarse, y su valoración ya es inmutable: es donde se
     *   comprueba que el trigger deja jubilarla al revaluar.
     * - **R-003** sin valorar, que no es lo mismo que valorado en cero.
     * - **R-004** con amenaza **libre** —lo que MAGERIT no recoge— y decisión de
     *   transferir, para que no parezca que todo se mitiga.
     */
    private function analisisDeRiesgosDeEjemplo(Sistema $sistema): void
    {
        if (Riesgo::query()->count() > 0) {
            return;
        }

        if (Amenaza::query()->vigentes()->count() === 0) {
            $this->command->warn('Sin catálogo de amenazas: ejecuta `php artisan catalogo:importar` y repite el seeder.');

            return;
        }

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $crear = app(CrearRiesgo::class);
        $valorar = app(ValorarRiesgo::class);
        $vinculos = app(VincularRiesgo::class);

        $activo = fn (string $codigo): ?Activo => Activo::query()->where('codigo', $codigo)->first();
        $amenaza = fn (string $codigo): ?Amenaza => Amenaza::query()->where('codigo', $codigo)->first();

        /*
         * R-001 — el que hay que ver primero.
         *
         * Pesa sobre la base de datos, que está sin cifrar y clasificada como
         * confidencial. El impacto NO sale de su valoración escrita: sale de la
         * efectiva, que hereda «alto» en disponibilidad del servicio que está dos
         * saltos por encima. Ese es el activo que una hoja de cálculo infravalora.
         */
        $base = $activo('BBDD-0001');

        if ($base !== null && $amenaza('A.11') !== null) {
            $riesgo = $crear([
                'titulo' => 'Acceso no autorizado a la base de datos de expedientes',
                'amenaza_id' => $amenaza('A.11')->id,
                'vulnerabilidad' => 'La base no cifra en reposo y las credenciales de administración se comparten entre dos personas.',
                'propietario_id' => $responsable?->id,
                'fecha_revision' => Carbon::today()->addMonths(6)->toDateString(),
            ], [$base], $responsable);

            // Dos controles de acceso apuntados y ninguno implantado: exactamente
            // lo que hace saltar el aviso de «residual sin respaldo».
            foreach ($this->implantaciones($sistema, 'op.acc.%', 2) as $implantacion) {
                $vinculos->salvaguarda(
                    $riesgo,
                    $implantacion,
                    'Previsto para el plan de adecuación; todavía sin implantar.',
                    $responsable,
                );
            }

            $valorar($riesgo->fresh(), [
                'probabilidad' => 3,
                'impacto' => 4,
                'probabilidad_residual' => 2,
                'impacto_residual' => 2,
                'justificacion_residual' => 'Con el cifrado en reposo y las cuentas nominativas, la exposición bajaría a un nivel asumible.',
                'decision' => DecisionRiesgo::Mitigar->value,
            ], $responsable);
        }

        /*
         * R-002 — aceptado, y con la reevaluación pasada de fecha.
         *
         * Aquí sí hay una salvaguarda implantada, así que el residual se sostiene.
         * Aceptar lo vuelve inmutable: es el riesgo con el que comprobar que el
         * trigger deja jubilar la valoración al revaluar y no deja reescribirla.
         */
        $servicio = $activo('SRV-0001');

        if ($servicio !== null && $amenaza('I.6') !== null) {
            $riesgo = $crear([
                'titulo' => 'Parada de la sede electrónica por corte de suministro',
                'amenaza_id' => $amenaza('I.6')->id,
                'vulnerabilidad' => 'La sala técnica no tiene generador; el SAI aguanta veinte minutos.',
                'propietario_id' => $responsable?->id,
                'fecha_revision' => Carbon::today()->subDays(20)->toDateString(),
                // El servidor de la sala técnica sólo si existe: el corte le afecta
                // igual, y así el riesgo pesa sobre los dos.
            ], array_filter([$servicio, $activo('HW-0002')]), $responsable);

            $respaldo = $this->implantaciones($sistema, 'mp.if.%', 1);

            foreach ($respaldo as $implantacion) {
                app(CambiarEstado::class)(
                    $implantacion,
                    EstadoImplantacion::Implantado,
                    $responsable,
                    'SAI instalado y probado en la revisión de instalaciones.',
                );

                $vinculos->salvaguarda($riesgo, $implantacion->fresh(), 'El SAI cubre el apagado ordenado.', $responsable);
            }

            $valorar($riesgo->fresh(), [
                'probabilidad' => 2,
                'impacto' => 5,
                'probabilidad_residual' => 1,
                'impacto_residual' => 5,
                'justificacion_residual' => 'El SAI no evita la parada larga, pero sí la pérdida de datos por apagado brusco.',
                'decision' => DecisionRiesgo::Mitigar->value,
                'nota' => 'Pendiente de presupuestar un generador.',
            ], $responsable);

            if ($responsable !== null) {
                app(AceptarRiesgo::class)(
                    $riesgo->fresh()->load('valoracionVigente'),
                    $responsable,
                    'Aceptado en el comité de seguridad; se revisa al presupuestar el generador.',
                );
            }
        }

        /*
         * R-003 — registrado y sin medir.
         *
         * Sobre el portátil retirado sin constancia de borrado, que es el activo
         * que la ficha del inventario ya señala. Sin valorar no cuenta en ninguna
         * cifra de exposición: no está por encima ni por debajo de ningún umbral,
         * sencillamente no se sabe.
         */
        $portatil = $activo('HW-0001');

        if ($portatil !== null && $amenaza('A.25') !== null) {
            $crear([
                'titulo' => 'Sustracción del portátil retirado, con los datos dentro',
                'amenaza_id' => $amenaza('A.25')->id,
                'vulnerabilidad' => 'Retirado hace dos meses y sin registro del borrado seguro que exige mp.si.5.',
            ], [$portatil], $responsable);
        }

        /*
         * R-004 — amenaza que no está en MAGERIT, y decisión que no es mitigar.
         *
         * «El proveedor cierra» no figura en el catálogo y no tiene por qué: se
         * escribe en el riesgo y no se añade al catálogo global, que es de todos.
         */
        $aplicacion = $activo('APP-0001');

        if ($aplicacion !== null) {
            $riesgo = $crear([
                'titulo' => 'Cierre del proveedor del gestor de expedientes',
                'amenaza_libre' => 'El proveedor del software cesa su actividad',
                'vulnerabilidad' => 'No hay depósito del código fuente ni contrato de continuidad.',
                'propietario_id' => $responsable?->id,
                'fecha_revision' => Carbon::today()->addMonths(3)->toDateString(),
            ], [$aplicacion], $responsable);

            $valorar($riesgo->fresh(), [
                'probabilidad' => 2,
                'impacto' => 4,
                'probabilidad_residual' => 2,
                'impacto_residual' => 3,
                'justificacion_residual' => 'Con el depósito de código en escrow, la migración sería viable en semanas.',
                'decision' => DecisionRiesgo::Transferir->value,
            ], $responsable);
        }

        $this->command->info(sprintf(
            'Análisis de riesgos: %d riesgos, %d sin valorar, %d con el residual sin respaldo.',
            Riesgo::query()->count(),
            Riesgo::query()->sinValorar()->count(),
            Riesgo::query()->residualSinRespaldo()->count(),
        ));
    }

    /**
     * Las primeras implantaciones del sistema cuyo requisito empieza por un
     * prefijo. Sirve para colgar salvaguardas de medidas reales del Anexo II sin
     * escribir ids que cambian en cada `migrate:fresh`.
     *
     * @return list<Implantacion>
     */
    private function implantaciones(Sistema $sistema, string $prefijo, int $cuantas): array
    {
        return $sistema->implantaciones()
            ->where('aplica', true)
            ->whereHas('requisito', fn (Builder $consulta) => $consulta->where('codigo', 'like', $prefijo))
            ->limit($cuantas)
            ->get()
            ->all();
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

        /*
         * Y una política, que es la otra familia de documentos: no se calcula, la
         * escribe la organización. Es la que da sentido al acuse de lectura —de
         * una Declaración de Aplicabilidad no acusa recibo nadie— y la que enseña
         * el flujo de aprobación de punta a punta.
         *
         * Se queda **sin versión y sin aprobar**, igual que la DdA: generar exige
         * Gotenberg, y sembrar una versión «aprobada» a mano fabricaría una firma
         * que nadie ha puesto. El estado de partida de cualquier organización es
         * justamente éste — un documento por escribir y por firmar.
         */
        $politica = Documento::query()->firstOrCreate(
            ['codigo' => 'POL-SEG-01'],
            [
                'sistema_id' => null,
                'titulo' => 'Política de Seguridad de la Información',
                'tipo' => TipoDocumento::Politica->value,
                'periodicidad_revision_meses' => 12,
                'exige_acuse' => true,
            ],
        );

        $this->command->info(sprintf(
            'Política %s creada: redáctala en /documentos/%d/cuerpo y mándala a revisión.',
            $politica->codigo,
            $politica->id,
        ));

        /*
         * Y el plan de adecuación, que es el tercer calculado y el que cierra la
         * fase 2. Sale del mismo sistema que la DdA a propósito: los dos se leen
         * uno al lado del otro —qué se exige y cómo está, frente a qué falta— y
         * así se ve que las cifras de los dos cuadran.
         */
        $plan = Documento::query()->firstOrCreate(
            ['codigo' => 'PLA-ENS-01'],
            [
                'sistema_id' => $sistema->id,
                'titulo' => 'Plan de adecuación al ENS',
                'tipo' => TipoDocumento::PlanAdecuacionEns->value,
            ],
        );

        $this->command->info(sprintf(
            'Plan %s listo para generar (php artisan documentos:generar %s --html).',
            $plan->codigo,
            $plan->codigo,
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
     * La primera lleva además coste estimado, porque vinculada a dos medidas es
     * la que hace visible que el plan de adecuación suma su coste una sola vez.
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
            /*
             * Con coste, y vinculada a DOS medidas a la vez: es la que enseña la
             * deduplicación del plan de adecuación. La columna le imputa 950 € a
             * cada una de las dos y el total suma 950, no 1.900.
             */
            'coste_estimado' => '950.00',
        ], null, $accesos);

        // Una con lista de comprobación, para que se vea el «2/4» sin montarlo
        // a mano cada vez que se refresca la base.
        app(GuardarSubtareas::class)(
            Tarea::query()->where('titulo', 'like', 'Revisar la política%')->firstOrFail(),
            [
                ['titulo' => 'Revisar la longitud mínima y la caducidad', 'hecha' => true],
                ['titulo' => 'Pasarla por el comité', 'hecha' => true],
                ['titulo' => 'Publicarla en la intranet', 'hecha' => false],
                ['titulo' => 'Registrar el acuse de lectura', 'hecha' => false],
            ],
        );

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
