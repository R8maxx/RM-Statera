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
use App\Domain\Auditoria\PrepararInformeAuditoria;
use App\Domain\Auditoria\RegistrarAuditoria;
use App\Domain\Auditoria\RegistrarHallazgo;
use App\Domain\Auditoria\RevisarPunto;
use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Conformidad\IniciarDeclaracion;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Conformidad\PrepararDocumentoDeclaracion;
use App\Domain\Contexto\AbrirTareaDeCuestion;
use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\AprobarAnalisis;
use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Enums\TipoParteInteresada;
use App\Domain\Contexto\GuardarRequisitosInteresado;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\RegistrarCuestion;
use App\Domain\Contexto\RegistrarParteInteresada;
use App\Domain\Contexto\RetirarDelAnalisis;
use App\Domain\Contexto\VincularImplantacionARequisito;
use App\Domain\Contexto\VincularRiesgoACuestion;
use App\Domain\Continuidad\CambiarEstadoBia;
use App\Domain\Continuidad\CodigoPrueba;
use App\Domain\Continuidad\DerivarDePrueba;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Enums\TipoPrueba;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Continuidad\PlanificarPrueba;
use App\Domain\Continuidad\RegistrarBia;
use App\Domain\Continuidad\RegistrarResultadoPrueba;
use App\Domain\Continuidad\VincularServicioAPlan;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\VincularEvidencia;
use App\Domain\Implantacion\CambiarEstado;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Incidente\CambiarEstadoIncidente;
use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\RegistrarIncidente;
use App\Domain\Mejora\AbrirActuacionDeMejora;
use App\Domain\Mejora\CambiarEstadoMejora;
use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\RegistrarMejora;
use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\RegistrarIndicador;
use App\Domain\Metrica\RegistrarMedicion;
use App\Domain\NoConformidad\AbrirAccionCorrectiva;
use App\Domain\NoConformidad\CambiarEstadoNoConformidad;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use App\Domain\Objetivo\AbrirActuacion;
use App\Domain\Objetivo\CambiarEstadoObjetivo;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\RegistrarObjetivo;
use App\Domain\Objetivo\VincularIndicador;
use App\Domain\Obligacion\AsumirObligacion;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\ObligacionesAplicables;
use App\Domain\Obligacion\RegistrarCumplimiento;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Persona\AsignarPuesto;
use App\Domain\Persona\DesignarRol;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\GuardarPasos;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use App\Domain\Persona\Models\AsignacionPuesto;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\Models\Puesto;
use App\Domain\Persona\RegistrarAsistencia;
use App\Domain\RevisionDireccion\AbrirDecision;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\CambiarEstadoRevision;
use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\RevisionDireccion\RegistrarRevision;
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
use App\Domain\Usuario\Models\CuentaSistema;
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
                // La razón social sí se siembra: es lo que se imprime en la
                // portada de los documentos, y con ella puesta el HTML de
                // `documentos:generar --html` enseña el caso real y no el
                // respaldo.
                'razon_social' => 'Organización de Pruebas, S.L.',
                'sector' => 'Servicios digitales',
                'domicilio' => 'Calle Sintética, 1',
                'codigo_postal' => '28001',
                'municipio' => 'Madrid',
                'provincia' => 'Madrid',
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
         * El auditor externo audita este sistema durante tres meses (§ 4.19).
         * Con un solo sistema sembrado el filtro no esconde nada, pero la ficha
         * de su cuenta enseña el alcance y la fecha, y la cuenta caduca sola.
         */
        $auditor = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->where('email', 'auditor@statera.test')
            ->first();

        if ($auditor !== null) {
            CuentaSistema::query()->firstOrCreate(['user_id' => $auditor->id, 'sistema_id' => $sistema->id]);

            if ($auditor->acceso_hasta === null) {
                $auditor->forceFill(['acceso_hasta' => today()->addMonths(3)])->save();
            }
        }

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
        // Detrás de las no conformidades porque comparte auditoría con ellas: la
        // oportunidad de mejora cuelga del hallazgo de la auditoría en curso.
        $this->mejorasDeEjemplo();
        // Después de riesgos y de implantaciones: el contexto se vincula a los
        // dos, y sembrarlo antes dejaría el DAFO suelto, que es justo lo que este
        // módulo existe para evitar.
        $this->contextoDeEjemplo($organizacion);
        // **Antes que los indicadores**, y no es una preferencia de orden: desde
        // el § 4.8 el IND-03 es calculado y cuenta sobre `personas`. Sembrado
        // después, la primera medición del personal formado sería un cero.
        $this->personasDeEjemplo($sistema);
        // Detrás de las personas y de los activos, que es de donde saca el
        // responsable y lo afectado.
        $this->incidentesDeEjemplo($sistema);
        // El último: mide sobre todo lo anterior, así que sembrarlo antes daría
        // series de ceros que no enseñan nada.
        $this->indicadoresDeEjemplo($sistema);
        // Detrás de los indicadores, que es su criterio de evaluación: sembrarlo
        // antes dejaría los cuatro objetivos sin nada con que juzgarlos, que es
        // justamente el hueco que el módulo existe para cerrar.
        $this->objetivosDeEjemplo();
        // El último de todos: recoge las siete entradas de la 9.3.2, así que
        // sembrarlo antes daría un acta con seis ceros y una fecha.
        $this->revisionDireccionDeEjemplo();
        // Después de la revisión y de las auditorías: los compromisos apuntan a
        // ellas como prueba de haberse cumplido.
        $this->obligacionesDeEjemplo($organizacion, $sistema);
        $this->documentoDeEjemplo($sistema);
        // Detrás de los documentos, porque el plan de continuidad es uno, y del
        // inventario, porque el BIA se hace sobre sus servicios.
        $this->continuidadDeEjemplo();
        // Detrás de las auditorías, porque se apoya en una autoevaluación
        // cerrada, y de los documentos, porque prepara la serie de la DdC.
        $this->conformidadDeEjemplo($sistema);
    }

    /**
     * La conformidad con el ENS (§ 4.17), **en preparación y sin firmar**.
     *
     * Siembra una autoevaluación cerrada —la del trimestre pasado, todas las
     * medidas revisadas y sin no conformidades mayores—, inicia la declaración
     * sobre ella y prepara la serie de la Declaración de Conformidad. Se queda
     * ahí a propósito, como la DdA y la política: firmar exige Gotenberg, y una
     * versión «aprobada» sembrada a mano fabricaría una firma que nadie ha
     * puesto. Lo que falta —generar, aprobar, atar la versión y registrar el
     * distintivo— es el recorrido que se quiere poder hacer en el navegador.
     *
     * La autoevaluación en curso de `auditoriasDeEjemplo()` sigue abierta: es la
     * de la renovación, y cerrarla aquí la dejaría sin nada que enseñar.
     */
    private function conformidadDeEjemplo(Sistema $sistema): void
    {
        if (Conformidad::query()->exists()) {
            return;
        }

        $autor = User::query()->where('organizacion_id', $sistema->organizacion_id)->first();

        $autoevaluacion = app(RegistrarAuditoria::class)([
            'sistema_id' => $sistema->id,
            'codigo' => 'AUD-2026-00',
            'tipo' => TipoAuditoria::Autoevaluacion->value,
            'fecha' => Carbon::today()->subMonths(3),
            'auditor' => 'Responsable de seguridad',
            'alcance' => 'Autoevaluación completa de las medidas exigibles en categoría básica.',
        ]);

        app(PrecargarChecklist::class)($autoevaluacion);
        app(CerrarAuditoria::class)->empezar($autoevaluacion);

        foreach ($autoevaluacion->puntos()->get() as $punto) {
            app(RevisarPunto::class)->marcar($punto, ResultadoPunto::Conforme);
        }

        app(CerrarAuditoria::class)->cerrar($autoevaluacion, $autor, 'Todas las medidas revisadas y conformes.');

        $conformidad = app(IniciarDeclaracion::class)($sistema->fresh(), $autor);
        $documento = app(PrepararDocumentoDeclaracion::class)($sistema, $autor);

        $this->command->info(sprintf(
            'Conformidad del sistema %s en preparación sobre %s. Genera %s, apruébalo y átalo en /conformidad/sistemas/%d.',
            $sistema->codigo,
            $autoevaluacion->codigo,
            $documento->codigo,
            $conformidad->sistema_id,
        ));
    }

    /**
     * El calendario de obligaciones, con las tres situaciones que hay que poder
     * distinguir de un vistazo.
     *
     * Mismo criterio que los cuatro riesgos y las tres no conformidades: un
     * registro de ejemplo donde todo está igual no enseña nada. Aquí se siembran
     * **una al día, una fuera de plazo y una nunca cumplida**, que son los tres
     * estados que la tabla, el panel y el calendario tienen que separar.
     *
     * Se asumen del catálogo y no se inventan: es el camino real, y de paso
     * comprueba que el filtro de `ObligacionesAplicables` deja pasar lo que le
     * toca a esta organización —proveedora del sector público, categoría básica—.
     */
    private function obligacionesDeEjemplo(Organizacion $organizacion, Sistema $sistema): void
    {
        if (Compromiso::query()->exists()) {
            return;
        }

        $asumir = app(AsumirObligacion::class);
        $registrar = app(RegistrarCumplimiento::class);

        $aplicables = app(ObligacionesAplicables::class)->para($organizacion)->keyBy('codigo');

        /*
         * Al día, con su prueba: la revisión por la dirección se celebró hace
         * tres meses y el acta lo demuestra.
         *
         * Es la que cierra el hueco que el acta llevaba impreso —«no se comprueba
         * que la revisión se celebre con la periodicidad comprometida»—, así que
         * es también la que conviene que alguien vea al abrir el módulo.
         *
         * **La auditoría interna no sale aquí y no es un olvido**: es del marco
         * ISO y esta organización sólo tiene un sistema del ENS, así que
         * `ObligacionesAplicables` la descarta. Sembrarla a mano sería saltarse
         * el filtro que el módulo existe para aplicar.
         */
        $revision = $aplicables->get('sgsi.revision-direccion');

        if ($revision !== null) {
            $compromiso = $asumir($revision, Carbon::today()->subYear());
            $acta = RevisionDireccion::query()->orderByDesc('fecha')->first();

            $registrar(
                $compromiso,
                Carbon::today()->subMonths(3),
                $acta === null ? [] : ['revision_direccion_id' => $acta->id],
            );
        }

        // Fuera de plazo: el reloj arrancó hace catorce meses y nadie la cumplió.
        $ines = $aplicables->get('ens.ines');

        if ($ines !== null) {
            $asumir($ines, Carbon::today()->subMonths(14));
        }

        /*
         * Nunca cumplida y todavía en plazo: es la que enseña que «nunca
         * cumplida» no es lo mismo que «fuera de plazo». La conformidad va
         * colgada del sistema, que es lo que la distingue del informe INES.
         */
        $conformidad = $aplicables->get('ens.conformidad');

        if ($conformidad !== null) {
            $asumir($conformidad, Carbon::today()->subMonths(2), $sistema->id);
        }
    }

    /**
     * El cuadro de indicadores: cuatro, y cada uno enseña una cosa distinta.
     *
     * Mismo criterio que los cuatro riesgos y las tres no conformidades: un
     * seeder que siembra cuatro filas iguales no enseña el módulo, enseña la
     * tabla. Aquí hay **uno en objetivo con serie**, para que la gráfica tenga
     * algo que dibujar; **uno fuera de objetivo**, que es el ámbar; **uno
     * manual y nunca medido**, que es la promesa sin cumplir; y **uno con el
     * periodo vencido**, que es el único rojo del módulo y la cláusula 9.1 sin
     * hacer.
     *
     * Las series se siembran **hacia atrás desde el periodo cerrado**, con
     * `RegistrarMedicion`, que es el mismo camino que usa el comando: sembrar
     * filas a pelo se saltaría el congelado del objetivo y la serie mentiría
     * sobre contra qué se juzgó cada trimestre.
     *
     * Datos sintéticos, como todo lo demás.
     */
    /**
     * La plantilla, los nombramientos del 5.3 y la formación. § 4.8.
     *
     * Cada persona enseña una cosa distinta, que es el criterio del resto del
     * seeder:
     *
     * - Una **con cuenta** y designada responsable de seguridad: el puente entre
     *   `personas` y `users`, y el nombramiento que el auditor pide.
     * - Una **sin cuenta**, que es el caso mayoritario, formada y con acuerdo.
     * - Una **sin formación ni acuerdo**, que es lo que pide acción hoy.
     * - Una **dada de baja con la checklist de salida a medias**, que es el único
     *   rojo del módulo y el hermano del equipo retirado sin constancia de
     *   borrado.
     *
     * **El caso incompatible se deja preparado y no se comete**: la responsable
     * de seguridad no se designa además responsable del sistema, porque
     * `DesignarRol` lo rechazaría y el seeder moriría. Está ahí para probarlo a
     * mano desde la interfaz, que es donde el mensaje tiene que leerse bien.
     */
    private function personasDeEjemplo(Sistema $sistema): void
    {
        if (Persona::query()->exists()) {
            return;
        }

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnica = User::query()->where('email', 'tecnico@statera.test')->first();
        $designar = app(DesignarRol::class);

        $ana = Persona::query()->create([
            'codigo' => 'PER-001',
            'nombre_pila' => 'Ana',
            'apellido1' => 'Ruiz',
            'apellido2' => 'Beltrán',
            'nif' => '00000001A',
            'telefono' => '+34 600 000 001',
            'email' => 'responsable@statera.test',
            'user_id' => $responsable?->id,
            'fecha_alta' => Carbon::today()->subYears(4),
        ]);

        $bruno = Persona::query()->create([
            'codigo' => 'PER-002',
            'nombre_pila' => 'Bruno',
            'apellido1' => 'Sáez',
            'apellido2' => 'Molina',
            'nif' => '00000002B',
            'telefono' => '+34 600 000 002',
            'telefono_fijo' => '+34 960 000 002',
            'email' => 'tecnico@statera.test',
            'user_id' => $tecnica?->id,
            'fecha_alta' => Carbon::today()->subYears(2),
        ]);

        // Sin cuenta: la mayoría de una plantilla no entra nunca en Statera.
        $carla = Persona::query()->create([
            'codigo' => 'PER-003',
            'nombre_pila' => 'Carla',
            'apellido1' => 'Ibáñez',
            'nif' => '00000003C',
            'fecha_alta' => Carbon::today()->subMonths(14),
        ]);

        // La que pide acción: ni formación ni acuerdo.
        $diego = Persona::query()->create([
            'codigo' => 'PER-004',
            'nombre_pila' => 'Diego',
            'apellido1' => 'Ferrer',
            'apellido2' => 'Lago',
            'fecha_alta' => Carbon::today()->subMonths(3),
        ]);

        // El rojo: se fue y la checklist de salida está a medias.
        $elena = Persona::query()->create([
            'codigo' => 'PER-005',
            'nombre_pila' => 'Elena',
            'apellido1' => 'Prat',
            'nif' => '00000005E',
            'fecha_alta' => Carbon::today()->subYears(3),
            'fecha_baja' => Carbon::today()->subMonth(),
        ]);

        // --- Los puestos y quién los ocupa -----------------------------------
        //
        // El organigrama de ejemplo tiene DOS niveles y no uno: con todo colgando
        // de la raíz, la pantalla no enseña lo único que hace falta ver, que es
        // el sangrado. Dirección arriba, y de ella las cuatro áreas.

        $direccion = Puesto::query()->create([
            'codigo' => 'PUE-001',
            'titulo' => 'Dirección',
            'mision' => 'Fijar los objetivos de la organización y responder de ellos.',
            'competencias' => 'Responsabilidad ejecutiva sobre la organización.',
        ]);

        $puestos = [];

        foreach ([
            'PUE-002' => 'Responsable de seguridad de la información',
            'PUE-003' => 'Administrador de sistemas',
            'PUE-004' => 'Atención al cliente',
            'PUE-005' => 'Comercial',
            'PUE-006' => 'Desarrolladora',
        ] as $codigo => $titulo) {
            $puestos[$titulo] = Puesto::query()->create([
                'codigo' => $codigo,
                'titulo' => $titulo,
                'reporta_a_id' => $direccion->id,
            ]);
        }

        // Uno se queda SIN caracterizar a propósito: es la cifra que la tabla
        // enseña y el filtro que hay que poder pulsar.
        $puestos['Responsable de seguridad de la información']->update([
            'mision' => 'Determinar qué protección necesita la información y verificar que se aplica.',
            'funciones' => "Mantener el SGSI.\nProponer las medidas y comprobar su eficacia.",
            'competencias' => 'Formación en seguridad de la información y tres años de experiencia.',
        ]);

        $asignar = app(AsignarPuesto::class);

        $asignar($ana, $puestos['Responsable de seguridad de la información'], Carbon::today()->subYears(4));
        $asignar($bruno, $puestos['Administrador de sistemas'], Carbon::today()->subYears(2));
        $asignar($carla, $puestos['Atención al cliente'], Carbon::today()->subMonths(14));
        $asignar($diego, $puestos['Comercial'], Carbon::today()->subMonths(3));

        /*
         * Elena se fue, así que su asignación está CERRADA y no borrada: es lo
         * que hace que la ficha del puesto pueda decir quién lo ocupó.
         *
         * Se escribe directa y **no por `AsignarPuesto`**, que la rechazaría por
         * estar de baja — y hace bien: asignar un puesto NUEVO a quien ya no
         * está es el error que esa guarda existe para impedir. Lo que se siembra
         * aquí no es una asignación nueva, es el hecho de que ocupó ese puesto
         * mientras estuvo.
         */
        AsignacionPuesto::query()->create([
            'persona_id' => $elena->id,
            'puesto_id' => $puestos['Desarrolladora']->id,
            'desde' => Carbon::today()->subYears(3),
            'hasta' => Carbon::today()->subMonth(),
        ]);

        // --- Los nombramientos del 5.3 --------------------------------------

        $designar($ana, $sistema, RolEns::ResponsableSeguridad, Carbon::today()->subYears(2), $responsable, 'Acta del comité de seguridad.');
        $designar($bruno, $sistema, RolEns::ResponsableSistema, Carbon::today()->subYears(2), $responsable);
        $designar($ana, $sistema, RolEns::ResponsableInformacion, Carbon::today()->subYear(), $responsable);

        // --- La formación: mp.per.3 y mp.per.4 -------------------------------

        $concienciacion = AccionFormativa::query()->create([
            'codigo' => sprintf('FOR-%d-01', Carbon::today()->year),
            'titulo' => 'Concienciación anual en seguridad de la información',
            'tipo' => TipoAccionFormativa::Concienciacion->value,
            'fecha' => Carbon::today()->subMonths(2),
            'duracion_horas' => 1.5,
            'contenido' => 'Correo fraudulento, contraseñas, puesto despejado y a quién avisar ante un incidente.',
        ]);

        // Diego se queda fuera a propósito: es el «sin formación reciente» que
        // el panel y la tabla tienen que saber enseñar.
        app(RegistrarAsistencia::class)($concienciacion, [
            $ana->id => true,
            $bruno->id => true,
            $carla->id => true,
            // Convocado y no fue, que es un hecho distinto de no haber sido
            // convocado — y el que un auditor pregunta.
            $diego->id => false,
        ]);

        $formacion = AccionFormativa::query()->create([
            'codigo' => sprintf('FOR-%d-02', Carbon::today()->year),
            'titulo' => 'Gestión de registros de actividad y detección de intrusión',
            'tipo' => TipoAccionFormativa::Formacion->value,
            'fecha' => Carbon::today()->subMonths(5),
            'duracion_horas' => 8,
            'contenido' => 'op.exp.8 y op.mon.1: qué se registra, dónde se guarda y cómo se revisa.',
        ]);

        app(RegistrarAsistencia::class)($formacion, [$bruno->id => true]);

        // --- Los deberes por escrito: mp.per.2 -------------------------------

        foreach ([$ana, $bruno, $carla, $elena] as $persona) {
            AcuerdoConfidencialidad::query()->create([
                'organizacion_id' => $persona->organizacion_id,
                'persona_id' => $persona->id,
                'fecha_firma' => $persona->fecha_alta,
                'nota' => 'Firmado en el alta, con el contrato.',
            ]);
        }

        // --- Las dos checklists ----------------------------------------------

        $guardar = app(GuardarPasos::class);

        $guardar($diego, TipoPasoPersona::Alta, [
            ['titulo' => 'Firmar el acuerdo de confidencialidad', 'hecho' => false],
            ['titulo' => 'Entregar el equipo y registrarlo en el inventario', 'hecho' => true],
            ['titulo' => 'Alta de cuentas y segundo factor', 'hecho' => true],
            ['titulo' => 'Convocar a la sesión de concienciación', 'hecho' => false],
        ]);

        // El rojo del módulo: se fue y quedan pasos sin marcar.
        $guardar($elena, TipoPasoPersona::Baja, [
            ['titulo' => 'Recuperar el portátil y el token', 'hecho' => true],
            ['titulo' => 'Revocar los accesos a los sistemas', 'hecho' => false],
            ['titulo' => 'Recordar por escrito que el deber de confidencialidad sigue vigente', 'hecho' => false],
        ]);

        $this->command->info(sprintf(
            'Personas: %d, con %d nombramientos vigentes.',
            Persona::query()->count(),
            DesignacionRol::query()->vigentes()->count(),
        ));
    }

    /**
     * Tres incidentes, y cada uno enseña una cosa distinta. § 4.10.
     *
     * - Uno **cerrado con su lección aprendida**, que es el ciclo completo de
     *   `op.exp.7` y lo único que el auditor busca de verdad.
     * - Uno **abierto y notificable a la AEPD dentro de plazo**: trabajo urgente
     *   con el reloj corriendo, que **no** va en rojo.
     * - Uno **fuera de plazo con la AEPD**, que es el único rojo del módulo y lo
     *   que hay que ver el primer día — igual que el residual sin respaldo en
     *   riesgos y la salida sin cerrar en personas.
     *
     * El tercero abre además una no conformidad, que es el caso que conecta los
     * dos módulos; el primero deja una oportunidad de mejora, que es el otro
     * camino y el que más se usa.
     */
    private function incidentesDeEjemplo(Sistema $sistema): void
    {
        if (Incidente::query()->exists()) {
            return;
        }

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnica = User::query()->where('email', 'tecnico@statera.test')->first();

        $registrar = app(RegistrarIncidente::class);
        $cambiar = app(CambiarEstadoIncidente::class);
        $anio = Carbon::today()->year;

        // --- El ciclo completo, con su lección --------------------------------

        $cerrado = $registrar([
            'codigo' => sprintf('INC-%d-01', $anio),
            'titulo' => 'Correo fraudulento suplantando a la dirección',
            'descripcion' => 'Se recibieron doce correos pidiendo una transferencia urgente desde un dominio parecido al corporativo. Nadie respondió y se reportaron al buzón de seguridad.',
            'sistema_id' => $sistema->id,
            'clasificacion' => ClasificacionIncidente::Fraude->value,
            'peligrosidad' => PeligrosidadIncidente::Media->value,
            'fecha_deteccion' => Carbon::now()->subDays(40),
            'fecha_inicio' => Carbon::now()->subDays(40)->subHours(3),
            'afecta_autenticidad' => true,
            'impacto' => 'Ningún pago llegó a cursarse. Doce personas recibieron el correo.',
            'acciones_contencion' => 'Bloqueo del dominio remitente y aviso a toda la plantilla.',
            'responsable_id' => $tecnica?->id,
        ], $responsable);

        $cambiar($cerrado, EstadoIncidente::EnTratamiento, $tecnica);
        $cambiar($cerrado, EstadoIncidente::Resuelto, $tecnica);

        $cerrado->update([
            'leccion_aprendida' => 'El filtro no marcaba los dominios parecidos al propio. Se añadió la regla y se incluyó el caso en la sesión de concienciación.',
        ]);

        $cambiar($cerrado->refresh(), EstadoIncidente::Cerrado, $responsable);

        // --- El reloj corriendo, y todavía en plazo ---------------------------

        $enPlazo = $registrar([
            'codigo' => sprintf('INC-%d-02', $anio),
            'titulo' => 'Envío de un listado con datos personales a un destinatario equivocado',
            'descripcion' => 'Un listado con nombre y correo de cuarenta personas se envió por error a una dirección externa.',
            'sistema_id' => $sistema->id,
            'clasificacion' => ClasificacionIncidente::CompromisoInformacion->value,
            'peligrosidad' => PeligrosidadIncidente::Alta->value,
            'fecha_deteccion' => Carbon::now()->subHours(6),
            'afecta_confidencialidad' => true,
            'impacto' => 'Cuarenta personas afectadas, sin categorías especiales de datos.',
            'acciones_contencion' => 'Se solicitó por escrito la destrucción del correo al destinatario.',
            'responsable_id' => $responsable?->id,
            // Datos personales de por medio: el reloj de las 72 h está en marcha.
            'notificable_aepd' => true,
            'notificable_ccn_cert' => true,
        ], $responsable);

        $cambiar($enPlazo, EstadoIncidente::EnTratamiento, $responsable);

        // --- El rojo: el plazo de la AEPD vencido sin notificar ---------------

        $vencido = $registrar([
            'codigo' => sprintf('INC-%d-03', $anio),
            'titulo' => 'Acceso no autorizado a una cuenta de correo corporativa',
            'descripcion' => 'Se detectaron accesos desde una dirección IP no habitual a la cuenta de una persona de la plantilla, con reenvío automático configurado a un buzón externo.',
            'sistema_id' => $sistema->id,
            'clasificacion' => ClasificacionIncidente::Intrusion->value,
            'peligrosidad' => PeligrosidadIncidente::MuyAlta->value,
            'fecha_deteccion' => Carbon::now()->subDays(5),
            'fecha_inicio' => Carbon::now()->subDays(9),
            'afecta_confidencialidad' => true,
            'afecta_autenticidad' => true,
            'afecta_trazabilidad' => true,
            'impacto' => 'Correo de una persona con acceso a datos de clientes, durante cuatro días.',
            'acciones_contencion' => 'Se revocó la sesión, se cambió la contraseña y se retiró la regla de reenvío.',
            'responsable_id' => $responsable?->id,
            'notificable_aepd' => true,
            'notificable_ccn_cert' => true,
        ], $responsable);

        $cambiar($vencido, EstadoIncidente::EnTratamiento, $responsable);

        $this->command->info(sprintf(
            'Incidentes: %d registrados, %d abiertos y %d fuera de plazo con la AEPD.',
            Incidente::query()->count(),
            Incidente::query()->abiertos()->count(),
            Incidente::query()->fueraDePlazoAepd()->count(),
        ));
    }

    private function indicadoresDeEjemplo(Sistema $sistema): void
    {
        $registrar = app(RegistrarIndicador::class);
        $medir = app(RegistrarMedicion::class);

        // --- El que va bien, con cuatro trimestres detrás -------------------
        $cobertura = $registrar([
            'codigo' => 'IND-01',
            'nombre' => 'Medidas del Anexo II implantadas',
            'descripcion' => 'El avance de la adecuación al ENS sobre lo que se le exige al sistema.',
            'origen' => OrigenMedicion::Calculado->value,
            'calculo' => CalculoIndicador::CumplimientoImplantado->value,
            'marco_id' => $sistema->marco_id,
            'unidad' => UnidadIndicador::Porcentaje->value,
            'sentido' => SentidoIndicador::MayorMejor->value,
            'periodicidad' => Periodicidad::Trimestral->value,
            'objetivo' => 60,
        ]);

        // Cuatro trimestres hacia atrás con una progresión creíble: una serie de
        // un punto no es una serie, y la gráfica es la mitad del módulo.
        foreach ([[4, 18.0], [3, 27.0], [2, 46.0], [1, 63.0]] as [$atras, $valor]) {
            [$inicio, $fin] = Periodicidad::Trimestral->periodoDe(Carbon::today()->subMonths($atras * 3));

            $medir->manual($cobertura, $inicio, $fin, [
                'valor' => $valor,
                'numerador' => (int) round($valor * 52 / 100),
                'denominador' => 52,
            ]);
        }

        // --- El que se queda corto, que es el ámbar ------------------------
        $evidencias = $registrar([
            'codigo' => 'IND-02',
            'nombre' => 'Evidencias caducadas',
            'descripcion' => 'Pruebas que ya no prueban: el requisito sigue diciendo «implantado» y la prueba ha vencido.',
            'origen' => OrigenMedicion::Calculado->value,
            'calculo' => CalculoIndicador::EvidenciasCaducadas->value,
            'unidad' => UnidadIndicador::Recuento->value,
            'sentido' => SentidoIndicador::MenorMejor->value,
            'periodicidad' => Periodicidad::Trimestral->value,
            'objetivo' => 0,
        ]);

        [$inicio, $fin] = Periodicidad::Trimestral->periodoAnteriorA(Carbon::today());
        $medir->calculada($evidencias, $inicio, $fin);

        /*
         * --- El que pasó de manual a calculado ----------------------------
         *
         * Existía como **manual** para enseñar lo que la 9.1 pide y esta base de
         * datos no sabía contestar, y su comentario decía literalmente «sin el
         * módulo de personas (§ 4.8)». Con el § 4.8 dentro eso es falso, así que
         * pasa a calculado: el porcentaje sale de las asistencias de los últimos
         * doce meses sobre la plantilla activa.
         */
        $registrar([
            'codigo' => 'IND-03',
            'nombre' => 'Personal con formación en seguridad al día',
            'descripcion' => 'Lo exige mp.per.4 del ENS y la cláusula 7.2 de ISO. Activas con al menos una asistencia en los últimos doce meses.',
            'origen' => OrigenMedicion::Calculado->value,
            'calculo' => CalculoIndicador::PersonalFormado->value,
            'unidad' => UnidadIndicador::Porcentaje->value,
            'sentido' => SentidoIndicador::MayorMejor->value,
            'periodicidad' => Periodicidad::Anual->value,
            'objetivo' => 95,
        ]);

        /*
         * --- El manual y nunca medido -------------------------------------
         *
         * El hueco que deja el anterior, y es honesto: la satisfacción de las
         * partes interesadas es **la entrada 9.3.2 e)** que el acta de la
         * revisión por la dirección declara que se aporta fuera de Statera. Un
         * módulo de métricas que sólo admitiera lo que ya sabe contar dejaría
         * fuera justo lo que cuesta medir.
         */
        $registrar([
            'codigo' => 'IND-05',
            'nombre' => 'Satisfacción de las partes interesadas',
            'descripcion' => 'La retroalimentación que pide la 9.3.2 e). Statera registra qué exige cada parte, no qué ha dicho: la cifra no sale de aquí.',
            'origen' => OrigenMedicion::Manual->value,
            'formula_o_fuente' => 'Encuesta anual a clientes y a la dirección: respuestas satisfactorias sobre respuestas recibidas.',
            'unidad' => UnidadIndicador::Porcentaje->value,
            'sentido' => SentidoIndicador::MayorMejor->value,
            'periodicidad' => Periodicidad::Anual->value,
            'objetivo' => 80,
        ]);

        /*
         * --- El que se saltó su periodo, que es el rojo --------------------
         *
         * Mensual y sin ninguna medición: el periodo cerrado pasó sin cifra, y
         * eso es la cláusula 9.1 sin hacer. Es el aviso que hay que ver en el
         * panel el primer día, igual que el residual sin respaldo en riesgos.
         */
        $registrar([
            'codigo' => 'IND-04',
            'nombre' => 'Tareas del plan de acción fuera de plazo',
            'descripcion' => 'Trabajo comprometido que no se hizo a tiempo.',
            'origen' => OrigenMedicion::Calculado->value,
            'calculo' => CalculoIndicador::TareasVencidas->value,
            'unidad' => UnidadIndicador::Recuento->value,
            'sentido' => SentidoIndicador::MenorMejor->value,
            'periodicidad' => Periodicidad::Mensual->value,
            'objetivo' => 2,
        ]);

        $this->command->info('Indicadores: 5 declarados, 1 con serie de 4 trimestres, 1 manual sin medir y 1 con el periodo vencido.');
    }

    /**
     * Dos revisiones por la dirección: la del año pasado, firmada, y la de este
     * año, en curso.
     *
     * **Las dos hacen falta para que se vea el módulo entero**, igual que con el
     * contexto. Con sólo la firmada, la ficha enseña un acta congelada y nada
     * más; con la de este año encima se ve lo único que de verdad distingue a
     * este módulo: que las entradas se miran **en vivo** mientras se prepara la
     * reunión y **congeladas** en cuanto se firma el acta.
     *
     * Y la del año pasado deja decisiones colgando, que es lo que hace que la de
     * este año tenga una entrada a) que enseñar: sin ella, «el estado de las
     * acciones de revisiones previas» saldría vacío y esa costura —la que hace
     * que la serie de actas signifique algo— no se vería.
     *
     * Datos sintéticos, como todo lo demás.
     */
    private function revisionDireccionDeEjemplo(): void
    {
        if (RevisionDireccion::query()->count() > 0) {
            return;
        }

        $registrar = app(RegistrarRevision::class);
        $empezar = app(CambiarEstadoRevision::class);
        $aprobar = app(AprobarRevision::class);
        $decidir = app(AbrirDecision::class);

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnica = User::query()->where('email', 'tecnico@statera.test')->first();

        if (! $responsable instanceof User) {
            return;
        }

        $anio = Carbon::today()->year;

        // --- La del año pasado, recorrida entera ---------------------------
        $anterior = $registrar([
            'codigo' => sprintf('RD-%d-01', $anio - 1),
            'fecha' => Carbon::today()->subMonths(11),
            'periodo_desde' => Carbon::today()->subMonths(23),
            'periodo_hasta' => Carbon::today()->subMonths(12),
            'asistentes' => 'Dirección general, responsable de seguridad y jefatura de sistemas.',
            'conclusiones' => 'El sistema de gestión está implantado y opera. Se acuerda reforzar la '
                .'formación y cerrar la adecuación al ENS antes del cierre del ejercicio.',
        ]);

        $empezar($anterior, EstadoRevision::EnCurso);

        $decidir($anterior, [
            'titulo' => 'Contratar la formación anual en seguridad',
            'descripcion' => 'Acordado en la revisión por la dirección.',
            'prioridad' => PrioridadTarea::Alta->value,
            'responsable_id' => $responsable->id,
            'fecha_limite' => Carbon::today()->subMonths(4),
        ], $responsable);

        $decidir($anterior, [
            'titulo' => 'Revisar el presupuesto de seguridad para el ejercicio siguiente',
            'prioridad' => PrioridadTarea::Media->value,
            'responsable_id' => $responsable->id,
            'fecha_limite' => Carbon::today()->subMonths(6),
        ], $responsable);

        $aprobar($anterior->refresh(), $responsable);

        // --- La de este año, en curso: las entradas se ven en vivo ---------
        $enCurso = $registrar([
            'codigo' => sprintf('RD-%d-01', $anio),
            'fecha' => Carbon::today(),
            'periodo_desde' => Carbon::today()->subMonths(11),
            'periodo_hasta' => Carbon::today(),
            'asistentes' => 'Dirección general y responsable de seguridad.',
        ]);

        $empezar($enCurso, EstadoRevision::EnCurso);

        $decidir($enCurso, [
            'titulo' => 'Aprobar el plan de adecuación revisado',
            'prioridad' => PrioridadTarea::Alta->value,
            'responsable_id' => $tecnica?->id,
            'fecha_limite' => Carbon::today()->addMonths(2),
        ], $responsable);

        $this->command->info(sprintf(
            'Revisiones por la dirección: %s aprobada con 2 decisiones y %s en curso.',
            $anterior->codigo,
            $enCurso->codigo,
        ));
    }

    /**
     * Los objetivos de seguridad: cuatro, y cada uno enseña una cosa distinta.
     *
     * Mismo criterio que los cuatro riesgos y los cuatro indicadores: un seeder
     * que siembra cuatro filas iguales no enseña el módulo, enseña la tabla.
     * Aquí hay **uno aprobado y en marcha**, con su indicador y su actuación, que
     * es el caso completo; **uno vencido**, que es el único rojo del módulo y la
     * 6.2 sin terminar; **uno propuesto y sin indicador**, que es la 6.2 e) sin
     * hacer y lo primero que un auditor pregunta; y **uno no alcanzado con su
     * motivo escrito**, que es literalmente lo que la revisión por la dirección
     * va a leer del año que termina.
     *
     * Todos pasan por `RegistrarObjetivo` y `CambiarEstadoObjetivo`, no por la
     * factory: es el mismo camino que usa la interfaz, así que la firma, la fecha
     * de cierre y el histórico quedan como quedarían de verdad. Sembrar filas a
     * pelo daría objetivos aprobados sin transición de aprobación, que es justo
     * lo que el auditor mira.
     *
     * Datos sintéticos, como todo lo demás.
     */
    private function objetivosDeEjemplo(): void
    {
        $registrar = app(RegistrarObjetivo::class);
        $cambiar = app(CambiarEstadoObjetivo::class);
        $vincular = app(VincularIndicador::class);

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnica = User::query()->where('email', 'tecnico@statera.test')->first();

        $anio = Carbon::today()->year;

        /** @var ?Indicador $cobertura */
        $cobertura = Indicador::query()->where('codigo', 'IND-01')->first();
        /** @var ?Indicador $tareasVencidas */
        $tareasVencidas = Indicador::query()->where('codigo', 'IND-04')->first();

        // --- El caso completo: aprobado, con indicador y con actuación -------
        $adecuacion = $registrar([
            'codigo' => sprintf('OBJ-%d-01', $anio),
            'titulo' => 'Llegar al 80 % de las medidas del Anexo II implantadas',
            'descripcion' => 'El objetivo de adecuación del año, sobre lo que el ENS le exige al sistema en categoría básica.',
            'recursos' => 'Media jornada semanal del equipo de sistemas y el presupuesto de la herramienta de inventario.',
            'responsable_id' => $responsable?->id,
            'fecha_objetivo' => Carbon::today()->addMonths(4),
        ], $responsable);

        if ($cobertura instanceof Indicador) {
            $vincular->vincular($adecuacion, $cobertura, $responsable);
        }

        $cambiar($adecuacion, EstadoObjetivo::Aprobado, $responsable, 'Aprobado en el comité de seguridad.');

        app(AbrirActuacion::class)($adecuacion, [
            'titulo' => 'Cerrar las medidas de op.exp pendientes',
            'descripcion' => 'Las de explotación son las que más pesan en el porcentaje.',
            'prioridad' => PrioridadTarea::Alta->value,
            'responsable_id' => $tecnica?->id,
            'fecha_limite' => Carbon::today()->addMonths(2),
            'coste_estimado' => 3500,
        ], $responsable);

        // --- El rojo: aprobado, con el plazo pasado y sin cerrar ------------
        $incidentes = $registrar([
            'codigo' => sprintf('OBJ-%d-02', $anio),
            'titulo' => 'Bajar a cero las tareas del plan fuera de plazo',
            'descripcion' => 'Trabajo comprometido que no se hizo a tiempo: es lo que el auditor usa para medir si el plan es real.',
            'recursos' => 'Sin coste: es cuestión de revisar el plan cada semana.',
            'responsable_id' => $tecnica?->id,
            'fecha_objetivo' => Carbon::today()->subDays(20),
        ], $responsable);

        if ($tareasVencidas instanceof Indicador) {
            $vincular->vincular($incidentes, $tareasVencidas, $responsable);
        }

        $cambiar($incidentes, EstadoObjetivo::Aprobado, $responsable, 'Aprobado en el comité de seguridad.');

        // --- La 6.2 e) sin hacer: propuesto y sin ningún indicador ----------
        $registrar([
            'codigo' => sprintf('OBJ-%d-03', $anio),
            'titulo' => 'Reducir el tiempo de aplicación de parches críticos',
            'descripcion' => 'Propuesto y todavía sin cifra con la que juzgarlo: la 6.2 exige que el objetivo sea medible.',
            'responsable_id' => $tecnica?->id,
        ], $responsable);

        // --- El que no se alcanzó, con su motivo escrito --------------------
        $formacion = $registrar([
            'codigo' => sprintf('OBJ-%d-04', $anio),
            'titulo' => 'Formar en seguridad al 95 % de la plantilla',
            'descripcion' => 'Lo exigen mp.per.4 del ENS y la cláusula 7.2 de ISO.',
            'recursos' => 'Contrato de formación anual.',
            'responsable_id' => $responsable?->id,
            'fecha_objetivo' => Carbon::today()->subMonths(2),
        ], $responsable);

        $cambiar($formacion, EstadoObjetivo::Aprobado, $responsable, 'Aprobado en el comité de seguridad.');
        $cambiar(
            $formacion,
            EstadoObjetivo::NoAlcanzado,
            $responsable,
            'Se quedó en el 71 %: la formación se contrató en noviembre y no dio tiempo a dos turnos. '
            .'Se replantea con el contrato firmado en enero.',
        );

        $this->command->info(sprintf(
            'Objetivos de seguridad: %d declarados, 1 fuera de plazo, 1 sin indicador y 1 no alcanzado con su motivo.',
            Objetivo::query()->count(),
        ));
    }

    /**
     * El contexto de la organización: un análisis aprobado y una revisión abierta.
     *
     * **Los dos hacen falta para que se vea el módulo entero.** Con sólo el
     * aprobado, la pantalla de revisiones enseña una fila y nada más; con la
     * revisión abierta encima —un alta y una baja— se ve lo único que la cláusula
     * 9.3 pide de verdad: qué ha cambiado.
     *
     * El DAFO lleva **dos cuestiones por cuadrante**, para que la matriz no salga
     * coja, y una de ellas marcada como climática, que es lo que permite enseñar a
     * qué se refiere la declaración de la enmienda 1:2024 en vez de sólo afirmarla.
     *
     * Y tres vínculos, uno de cada clase: una amenaza que abrió un riesgo, una
     * debilidad con su tarea, y un requisito legal atado a la medida que lo cubre
     * —que es el que hace que la Declaración de Aplicabilidad pueda imprimirlo—.
     *
     * Datos sintéticos, como todo lo demás: ni una cuestión real de nadie.
     */
    private function contextoDeEjemplo(Organizacion $organizacion): void
    {
        if (AnalisisContexto::query()->count() > 0) {
            return;
        }

        $registrarCuestion = app(RegistrarCuestion::class);
        $registrarParte = app(RegistrarParteInteresada::class);
        $guardarRequisitos = app(GuardarRequisitosInteresado::class);
        $enCurso = app(AnalisisEnCurso::class);

        $responsable = User::query()
            ->where('organizacion_id', $organizacion->id)
            ->where('email', 'responsable@statera.test')
            ->first();

        // --- El DAFO ---------------------------------------------------------

        $cuestiones = [
            ['CTX-01', TipoCuestion::Fortaleza, MateriaCuestion::Organizativo, 'Dirección implicada en seguridad', 'El comité de dirección revisa el estado del SGSI cada trimestre y aprueba el presupuesto de seguridad.'],
            ['CTX-02', TipoCuestion::Fortaleza, MateriaCuestion::Tecnologico, 'Infraestructura homogénea y reciente', 'Todo el parque de servidores está en una única nube, con plantillas comunes y menos de tres años de antigüedad.'],
            ['CTX-03', TipoCuestion::Debilidad, MateriaCuestion::Organizativo, 'Equipo de sistemas pequeño', 'Dos personas para toda la operación: las vacaciones y las bajas dejan tareas de seguridad sin cubrir.'],
            ['CTX-04', TipoCuestion::Debilidad, MateriaCuestion::Tecnologico, 'Inventario de software sin mantener', 'No hay constancia de qué software está instalado en los puestos, así que no se sabe qué hay sin soporte.'],
            ['CTX-05', TipoCuestion::Oportunidad, MateriaCuestion::Competitivo, 'El ENS abre concursos públicos', 'Acreditar la conformidad con el ENS permite optar a licitaciones a las que hoy no se puede concurrir.'],
            ['CTX-06', TipoCuestion::Oportunidad, MateriaCuestion::LegalRegulatorio, 'Convergencia con NIS2', 'Buena parte de lo que exige el ENS adelanta trabajo para NIS2, que llegará por otra vía.'],
            ['CTX-07', TipoCuestion::Amenaza, MateriaCuestion::LegalRegulatorio, 'Endurecimiento del marco regulatorio', 'Los pliegos del sector público empiezan a exigir categoría media donde antes bastaba la básica.'],
            ['CTX-08', TipoCuestion::Amenaza, MateriaCuestion::Ambiental, 'Olas de calor y continuidad del servicio', 'Los episodios de calor extremo afectan a la climatización del centro de proceso de datos del proveedor y a la disponibilidad del servicio.', true],
        ];

        foreach ($cuestiones as $fila) {
            $registrarCuestion([
                'codigo' => $fila[0],
                'tipo' => $fila[1]->value,
                'materia' => $fila[2]->value,
                'titulo' => $fila[3],
                'descripcion' => $fila[4],
                'es_climatica' => $fila[5] ?? false,
            ], $responsable);
        }

        // --- Las partes interesadas ------------------------------------------

        $partes = [
            ['PI-01', 'Administraciones públicas cliente', TipoParteInteresada::Cliente, Ambito::Externo, 'Los organismos que contratan la plataforma y que trasladan el ENS por contrato.'],
            ['PI-02', 'Centro Criptológico Nacional', TipoParteInteresada::Regulador, Ambito::Externo, 'Supervisa el Esquema Nacional de Seguridad y publica las guías CCN-STIC.'],
            ['PI-03', 'Personal propio', TipoParteInteresada::Empleado, Ambito::Interno, 'Quienes operan y desarrollan la plataforma.'],
            ['PI-04', 'Proveedor de nube', TipoParteInteresada::Proveedor, Ambito::Externo, 'Presta la infraestructura sobre la que corre todo el servicio.'],
        ];

        foreach ($partes as $fila) {
            $registrarParte([
                'codigo' => $fila[0],
                'nombre' => $fila[1],
                'tipo' => $fila[2]->value,
                'ambito' => $fila[3]->value,
                'descripcion' => $fila[4],
            ], $responsable);
        }

        /*
         * Seis requisitos, y las tres naturalezas representadas: sin un ejemplo de
         * cada una, el indicador de «obligaciones sin cubrir» no se distingue del
         * recuento total y la diferencia entre exigir y esperar no se ve.
         */
        $requisitos = [
            'PI-01' => [
                ['Cumplir el ENS en la categoría que fije cada pliego.', NaturalezaRequisito::Legal, 'RD 311/2022, art. 2', 'Sistema categorizado como básico y plan de adecuación en marcha.'],
                ['Avisar de cualquier incidente de seguridad en menos de 24 horas.', NaturalezaRequisito::Contractual, 'Contrato marco, cláusula 12', null],
            ],
            'PI-02' => [
                ['Declarar la conformidad y publicar el distintivo correspondiente.', NaturalezaRequisito::Legal, 'RD 311/2022, art. 38', null],
            ],
            'PI-03' => [
                ['Que las medidas de seguridad no impidan trabajar.', NaturalezaRequisito::Expectativa, null, 'Se revisa en la encuesta interna anual.'],
                ['Formación en seguridad de la información al incorporarse.', NaturalezaRequisito::Contractual, 'Convenio interno', null],
            ],
            'PI-04' => [
                ['Mantener la disponibilidad acordada incluso en episodios de calor extremo.', NaturalezaRequisito::Contractual, 'ANS, anexo I', null, true],
            ],
        ];

        foreach ($requisitos as $codigo => $lineas) {
            $parte = ParteInteresada::query()->where('codigo', $codigo)->first();

            if (! $parte instanceof ParteInteresada) {
                continue;
            }

            $guardarRequisitos($parte, array_map(static fn (array $linea): array => [
                'descripcion' => $linea[0],
                'naturaleza' => $linea[1]->value,
                'referencia' => $linea[2],
                'como_se_atiende' => $linea[3],
                'es_climatico' => $linea[4] ?? false,
            ], $lineas));
        }

        $this->vinculosDeContexto($responsable);

        // --- Se firma --------------------------------------------------------

        $borrador = $enCurso->borradorObligatorio($responsable);
        $borrador->update([
            'fecha_analisis' => Carbon::today()->subMonths(4),
            'clima_pertinente' => true,
            'clima_justificacion' => 'Los episodios de calor extremo afectan a la refrigeración del centro de proceso '
                .'de datos del proveedor, y con ella a la disponibilidad del servicio. Se recoge como cuestión CTX-08 '
                .'y se traslada al acuerdo de nivel de servicio con el proveedor de nube.',
            'nota' => 'Taller de dos horas con dirección, sistemas y desarrollo. Se revisaron los pliegos de las tres '
                .'últimas licitaciones y el informe de incidentes del año.',
        ]);

        if ($responsable instanceof User) {
            app(AprobarAnalisis::class)($borrador->refresh(), $responsable);
        }

        // --- Y se abre la revisión siguiente ---------------------------------

        /*
         * Un alta y una baja sobre el análisis ya firmado. Es lo único que hace
         * visible la pantalla de comparación, que es la que contesta a la entrada
         * «cambios de contexto» de la cláusula 9.3.
         */
        $registrarCuestion([
            'codigo' => 'CTX-09',
            'tipo' => TipoCuestion::Oportunidad->value,
            'materia' => MateriaCuestion::Economico->value,
            'titulo' => 'Ayudas públicas a la ciberseguridad',
            'descripcion' => 'La convocatoria de este año cubre parte del coste de las herramientas de monitorización.',
        ], $responsable);

        $obsoleta = CuestionContexto::query()->where('codigo', 'CTX-02')->first();

        if ($obsoleta instanceof CuestionContexto) {
            app(RetirarDelAnalisis::class)->cuestion(
                $obsoleta,
                'El proveedor ha migrado parte del parque a otra región con hardware distinto, así que la '
                .'homogeneidad ya no se sostiene.',
                $responsable,
            );
        }

        $this->command->info(sprintf(
            'Contexto: %d cuestiones, %d partes interesadas, %d análisis.',
            CuestionContexto::query()->count(),
            ParteInteresada::query()->count(),
            AnalisisContexto::query()->count(),
        ));
    }

    /**
     * Los tres vínculos del módulo, uno de cada clase.
     *
     * Van antes de aprobar a propósito: la instantánea los congela, y un análisis
     * firmado que no enseñara ninguno diría que el DAFO no llegó a ninguna parte.
     */
    private function vinculosDeContexto(?User $responsable): void
    {
        $amenaza = CuestionContexto::query()->where('codigo', 'CTX-07')->first();
        $riesgo = Riesgo::query()->orderBy('codigo')->first();

        if ($amenaza instanceof CuestionContexto && $riesgo instanceof Riesgo) {
            app(VincularRiesgoACuestion::class)->vincular($amenaza, $riesgo, $responsable);
        }

        $debilidad = CuestionContexto::query()->where('codigo', 'CTX-04')->first();

        if ($debilidad instanceof CuestionContexto) {
            app(AbrirTareaDeCuestion::class)($debilidad, [
                'titulo' => 'Levantar el inventario de software de los puestos',
                'descripcion' => 'Extraer la lista de software instalado y contrastarla con las versiones con soporte.',
                'prioridad' => PrioridadTarea::Alta->value,
                'fecha_limite' => Carbon::today()->addMonths(2),
            ], $responsable);
        }

        /*
         * El requisito legal atado a una medida. Es el vínculo que paga el módulo:
         * a partir de aquí la Declaración de Aplicabilidad puede justificar la
         * inclusión del control con «exigido por las administraciones cliente».
         */
        $requisito = ParteInteresada::query()
            ->where('codigo', 'PI-01')
            ->first()
            ?->requisitos()
            ->where('naturaleza', NaturalezaRequisito::Legal->value)
            ->first();

        $implantacion = Implantacion::query()->where('aplica', true)->orderBy('id')->first();

        if ($requisito !== null && $implantacion instanceof Implantacion) {
            app(VincularImplantacionARequisito::class)->vincular($requisito, $implantacion, $responsable);
        }
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
            'equipo' => 'Responsable de seguridad, como acompañante',
            'alcance' => 'Muestreo de las medidas de control de acceso y de explotación.',
            // La 9.2.2: contra qué y cómo. Salen impresos en el informe.
            'criterios' => 'Anexo II del Real Decreto 311/2022 y la política de seguridad de la organización.',
            'metodo' => 'Entrevistas con los responsables y revisión documental sobre una muestra de las medidas.',
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

        // Su informe (§ 4.18), preparado y sin generar: `documentos:generar
        // INF-AUD-2025-01 --html` es el bucle rápido para verlo.
        app(PrepararInformeAuditoria::class)($cerrada->fresh() ?? $cerrada);

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

        /*
         * Y una oportunidad de mejora, que desde la cláusula 10.1 tiene registro
         * propio. Es el caso que enseña la bifurcación: este hallazgo **no** se
         * trata como no conformidad porque no incumple nada, y el formulario de
         * no conformidades redirige al de mejoras si alguien lo intenta.
         */
        $registrar->registrar(
            $enCurso,
            TipoHallazgo::OportunidadMejora,
            'El inventario de software de los puestos se mantiene a mano; se podría extraer del gestor de parches.',
            $enCurso->puntos()->firstOrFail(),
        );

        $this->command->info(sprintf(
            'Auditorías: %s cerrada con %d hallazgos y %s en curso.',
            $cerrada->codigo,
            $cerrada->hallazgos()->count(),
            $enCurso->codigo,
        ));
    }

    /**
     * Cuatro oportunidades de mejora, y cada una enseña una cosa distinta.
     *
     * Mismo criterio que los cuatro riesgos, los cuatro indicadores y los cuatro
     * objetivos. Aquí hay **una de un hallazgo de auditoría**, que es la que
     * enseña la bifurcación del capítulo 10 —ese hallazgo no se trata como no
     * conformidad porque no incumple nada—; **una en curso con su actuación**, que
     * es el caso completo; **una descartada con su motivo**, que es el estado más
     * usado de este registro y el que explica por qué el motivo es obligatorio; y
     * **una sin empezar y pasada de fecha**, que es la cifra honesta de un buzón
     * de ideas al que nadie vuelve — y que se señala en gris, no en rojo.
     *
     * Todas pasan por `RegistrarMejora` y `CambiarEstadoMejora`, que es el mismo
     * camino que usa la interfaz: sembrar filas a pelo daría mejoras cerradas sin
     * transición, que es justo lo que el auditor mira.
     *
     * Datos sintéticos, como todo lo demás.
     */
    private function mejorasDeEjemplo(): void
    {
        if (Mejora::query()->count() > 0) {
            return;
        }

        $registrar = app(RegistrarMejora::class);
        $cambiar = app(CambiarEstadoMejora::class);

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnica = User::query()->where('email', 'tecnico@statera.test')->first();

        $anio = Carbon::today()->year;

        // --- La del hallazgo, que enseña la bifurcación del capítulo 10 ------
        $hallazgo = Hallazgo::query()
            ->where('tipo', TipoHallazgo::OportunidadMejora->value)
            ->whereDoesntHave('mejora')
            ->orderBy('id')
            ->first();

        if ($hallazgo instanceof Hallazgo) {
            $registrar([
                'codigo' => sprintf('OM-%d-01', $anio),
                'origen' => OrigenMejora::Auditoria->value,
                'hallazgo_id' => $hallazgo->id,
                'titulo' => 'Extraer el inventario de software del gestor de parches',
                'descripcion' => 'Hoy se mantiene a mano y se desactualiza entre revisiones.',
                'beneficio_esperado' => 'El inventario deja de depender de que alguien se acuerde, y op.exp.1 pasa a tener prueba automática.',
                'responsable_id' => $tecnica?->id,
                'fecha_deteccion' => Carbon::today()->subDays(10),
            ], $responsable);
        }

        // --- El caso completo: en curso y con su actuación -------------------
        $enCurso = $registrar([
            'codigo' => sprintf('OM-%d-02', $anio),
            'origen' => OrigenMejora::Indicador->value,
            'titulo' => 'Avisar de las evidencias que caducan con treinta días de antelación',
            'descripcion' => 'El indicador de evidencias caducadas se queda corto todos los trimestres porque se renuevan tarde.',
            'beneficio_esperado' => 'Menos pruebas caducadas sin que nadie tenga que revisarlas a mano.',
            'responsable_id' => $tecnica?->id,
            'fecha_deteccion' => Carbon::today()->subDays(30),
            'fecha_prevista' => Carbon::today()->addMonths(2),
        ], $responsable);

        $cambiar($enCurso, EstadoMejora::EnCurso, $responsable);

        app(AbrirActuacionDeMejora::class)($enCurso, [
            'titulo' => 'Añadir el aviso de caducidad al resumen diario',
            'prioridad' => PrioridadTarea::Media->value,
            'responsable_id' => $tecnica?->id,
            'fecha_limite' => Carbon::today()->addMonth(),
            'coste_estimado' => 0,
        ], $responsable);

        // --- La descartada, con su motivo escrito ---------------------------
        $descartada = $registrar([
            'codigo' => sprintf('OM-%d-03', $anio),
            'origen' => OrigenMejora::Propia->value,
            'titulo' => 'Certificar el sistema en ISO 27017',
            'descripcion' => 'Se planteó al revisar los servicios en la nube.',
            'fecha_deteccion' => Carbon::today()->subMonths(2),
        ], $responsable);

        $cambiar(
            $descartada,
            EstadoMejora::Descartada,
            $responsable,
            'Fuera del alcance de este año: primero hay que cerrar la adecuación al ENS. Se revisa en la próxima revisión por la dirección.',
        );

        // --- La que nadie ha empezado y se pasó de fecha --------------------
        $registrar([
            'codigo' => sprintf('OM-%d-04', $anio),
            'origen' => OrigenMejora::RevisionDireccion->value,
            'titulo' => 'Unificar las dos plantillas de acta que conviven',
            'fecha_deteccion' => Carbon::today()->subMonths(4),
            'fecha_prevista' => Carbon::today()->subDays(20),
        ], $responsable);

        $this->command->info(sprintf(
            'Oportunidades de mejora: %d registradas, 1 de un hallazgo, 1 descartada con motivo y 1 sin empezar.',
            Mejora::query()->count(),
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

        /*
         * Y el análisis del contexto, el cuarto calculado y el primero **sin
         * sistema**: las cuestiones internas y externas y las partes interesadas
         * son de la organización entera. Sirve además de prueba viva de que el
         * `CHECK` reescrito deja pasar un calculado de ámbito organizativo.
         *
         * Lleva acuse de lectura y periodicidad anual: el contexto es lo que la
         * dirección tiene que volver a mirar cada año, y la revisión vencida la
         * avisa `Fuente::Documento` sin que haga falta una cuarta fuente.
         */
        $contexto = Documento::query()->firstOrCreate(
            ['codigo' => 'CTX-SGSI-01'],
            [
                'sistema_id' => null,
                'titulo' => 'Análisis del contexto de la organización',
                'tipo' => TipoDocumento::AnalisisContexto->value,
                'periodicidad_revision_meses' => 12,
            ],
        );

        $this->command->info(sprintf(
            'Análisis del contexto %s listo para generar (php artisan documentos:generar %s --html).',
            $contexto->codigo,
            $contexto->codigo,
        ));

        /*
         * Y el acta de la revisión por la dirección, el **quinto** calculado y el
         * segundo sin sistema. Imprime siempre la última revisión aprobada, así
         * que con el seeder recién pasado sale la del año anterior — que es la
         * que tiene decisiones y entradas de verdad.
         *
         * Periodicidad anual, como el contexto: lo que vence no es la revisión
         * sino la revisión de su acta, y eso lo recoge `Fuente::Documento` sin
         * que haga falta una cuarta fuente.
         */
        $acta = Documento::query()->firstOrCreate(
            ['codigo' => 'ACT-REV-01'],
            [
                'sistema_id' => null,
                'titulo' => 'Acta de revisión por la dirección',
                'tipo' => TipoDocumento::ActaRevision->value,
                'periodicidad_revision_meses' => 12,
            ],
        );

        $this->command->info(sprintf(
            'Acta de revisión %s lista para generar (php artisan documentos:generar %s --html).',
            $acta->codigo,
            $acta->codigo,
        ));
    }

    /**
     * La continuidad, con las cuatro situaciones que el módulo tiene que separar.
     *
     * - **Dos servicios con BIA**: la sede electrónica, aprobado y coherente, y
     *   una mesa de ayuda nueva, en borrador y con un RTO de 72 h en un servicio
     *   que el propio BIA deja de tolerar al primer día. Es el ámbar que avisa
     *   sin bloquear, y el que hay que ver sin fabricarlo a mano.
     * - **Un plan que cubre los dos**, redactado y sin aprobar, como la política:
     *   sembrar una versión aprobada fabricaría una firma que nadie ha puesto.
     * - **Una prueba realizada con resultado parcial** —la sede tardó más de lo
     *   que promete su RTO— y una tarea derivada de ella, que es la costura que
     *   convierte el hueco en trabajo.
     * - **Y una planificada dentro de tres semanas**, para que el calendario y el
     *   panel tengan algo por delante.
     *
     * Todo por las acciones del dominio y no con `create()`: el histórico de
     * estados del BIA y de la prueba sólo sale si se pasa por ellas, y un seeder
     * que se lo salte enseñaría fichas sin trazabilidad.
     */
    private function continuidadDeEjemplo(): void
    {
        if (BiaServicio::query()->exists() || PruebaContinuidad::query()->exists()) {
            return;
        }

        $responsable = User::query()->where('email', 'responsable@statera.test')->first();
        $tecnica = User::query()->where('email', 'tecnico@statera.test')->first();

        $sede = $this->activo('SRV-0001', 'Sede electrónica interna', TipoActivo::Servicios);
        $mesa = $this->activo('SRV-0002', 'Mesa de ayuda a usuarios', TipoActivo::Servicios, [
            'subtipo' => 'Servicio de soporte',
            'valor_d' => NivelDimension::Medio->value,
            'ubicacion' => 'Nube corporativa',
            'clasificacion' => Clasificacion::UsoInterno->value,
        ]);

        $registrarBia = app(RegistrarBia::class);

        // --- Aprobado y coherente: intolerable a la semana, RTO de un día ------

        $biaSede = $registrarBia([
            'activo_id' => $sede->id,
            'impacto_4h' => NivelImpacto::Bajo->value,
            'impacto_1d' => NivelImpacto::Medio->value,
            'impacto_3d' => NivelImpacto::Alto->value,
            'impacto_1s' => NivelImpacto::MuyAlto->value,
            'impacto_1m' => NivelImpacto::MuyAlto->value,
            'rto_horas' => 24,
            'rpo_horas' => 4,
            'justificacion' => 'Los trámites admiten un día de retraso; a partir de la semana se incumplen plazos administrativos con terceros.',
            'responsable_id' => $tecnica?->id,
        ], $responsable);

        app(CambiarEstadoBia::class)($biaSede, EstadoBia::Aprobado, $responsable);

        // --- Borrador e incoherente: intolerable al día, RTO de tres días ------

        $registrarBia([
            'activo_id' => $mesa->id,
            'impacto_4h' => NivelImpacto::Medio->value,
            'impacto_1d' => NivelImpacto::MuyAlto->value,
            'impacto_3d' => NivelImpacto::MuyAlto->value,
            'impacto_1s' => NivelImpacto::MuyAlto->value,
            'impacto_1m' => NivelImpacto::MuyAlto->value,
            'rto_horas' => 72,
            'rpo_horas' => 24,
            'justificacion' => 'Sin mesa de ayuda nadie restablece contraseñas ni atiende incidencias. El RTO está copiado del contrato del proveedor y falta contrastarlo.',
            'responsable_id' => $responsable?->id,
        ], $responsable);

        // --- El plan, que cubre los dos servicios ------------------------------

        $plan = Documento::query()->firstOrCreate(
            ['codigo' => 'PLN-CONT-01'],
            [
                'sistema_id' => null,
                'titulo' => 'Plan de continuidad de los servicios internos',
                'tipo' => TipoDocumento::PlanContinuidad->value,
                'periodicidad_revision_meses' => 12,
            ],
        );

        // Lo mismo que hace `DocumentoController::store()` al crear uno: sin los
        // huecos materializados, el editor abriría vacío.
        app(MaterializarSecciones::class)($plan);

        $vincular = app(VincularServicioAPlan::class);
        $vincular->vincular($plan, $sede);
        $vincular->vincular($plan, $mesa);

        // --- Una prueba realizada, parcial, con su tarea -----------------------

        $planificar = app(PlanificarPrueba::class);
        $codigos = app(CodigoPrueba::class);

        $realizada = $planificar([
            'codigo' => $codigos->siguiente(),
            'titulo' => 'Restauración de la sede desde la copia nocturna',
            'documento_id' => $plan->id,
            'tipo' => TipoPrueba::Tecnica->value,
            'fecha_prevista' => Carbon::today()->subDays(20)->toDateString(),
            'responsable_id' => $tecnica?->id,
        ], [$sede->id, $mesa->id], $responsable);

        $realizada = app(RegistrarResultadoPrueba::class)($realizada, [
            'fecha_realizacion' => Carbon::today()->subDays(18)->toDateString(),
            'resultado' => ResultadoPrueba::Parcial,
            'conclusiones' => 'La copia se restauró completa, pero la sede tardó 30 horas en volver frente a las 24 del RTO: la reinstalación del gestor de expedientes no estaba en el guion. La mesa de ayuda volvió en 20 horas.',
            'servicios' => [
                $sede->id => ['rto_alcanzado_horas' => 30, 'rpo_alcanzado_horas' => 4],
                $mesa->id => ['rto_alcanzado_horas' => 20, 'rpo_alcanzado_horas' => 12],
            ],
        ], $tecnica);

        if ($responsable instanceof User) {
            app(DerivarDePrueba::class)->tarea($realizada, [
                'titulo' => 'Añadir la reinstalación del gestor de expedientes al guion de recuperación',
                'descripcion' => 'La prueba de restauración superó el RTO de la sede en seis horas por este paso.',
                'prioridad' => PrioridadTarea::Alta->value,
                'fecha_limite' => Carbon::today()->addDays(30),
                'responsable_id' => $tecnica?->id,
            ], $responsable);
        }

        // --- Y la siguiente, dentro de tres semanas ----------------------------

        $planificar([
            'codigo' => $codigos->siguiente(),
            'titulo' => 'Ejercicio de sobremesa: caída de la mesa de ayuda',
            'documento_id' => $plan->id,
            'tipo' => TipoPrueba::Sobremesa->value,
            'fecha_prevista' => Carbon::today()->addWeeks(3)->toDateString(),
            'responsable_id' => $responsable?->id,
        ], [$mesa->id], $responsable);

        $this->command->info(sprintf(
            'Continuidad: %d BIA (%d con el RTO incoherente), plan %s y %d pruebas.',
            BiaServicio::query()->count(),
            BiaServicio::query()->rtoIncoherente()->count(),
            $plan->codigo,
            PruebaContinuidad::query()->count(),
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

        // Las cuentas sembradas llevan contraseña conocida, así que nacen
        // activas: sin `activada_en` serían invitaciones pendientes y
        // `CuentaVigente` las echaría en la primera petición.
        if ($usuario->activada_en === null) {
            $usuario->forceFill(['activada_en' => now()])->save();
        }

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
