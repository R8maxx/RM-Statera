<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Persona\Models\Persona;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Todo lo que vence en un tramo de fechas, venga de donde venga.
 *
 * Vive en `Aviso/` y no en `Tarea/` **a propósito**: es el calendario de
 * obligaciones de § 4.16, que unifica todo lo periódico. Colgarlo de tareas
 * habría obligado a mudarlo, o a tener dos calendarios.
 *
 * **`entre()` recorre `Fuente::cases()` y no una lista de bloques `if`.** Eran
 * tres al principio y no han dejado de crecer; con bloques escritos a mano,
 * añadir una fuente sería acordarse de tocar aquí, y no acordarse no rompe
 * nada: la fuente sencillamente no sale. Lo que cada fuente necesita —de qué tabla, de qué fecha y con qué
 * estado— lo declara su propio método, y todos devuelven lo mismo.
 *
 * **Es también el único sitio donde se decide qué es un vencimiento.** El
 * resumen diario que sale por correo se apoya en esto mismo: si cada uno
 * consultara por su cuenta, el correo y el calendario acabarían discrepando y el
 * que se mira menos es el que se queda mal.
 */
final readonly class CalendarioVencimientos
{
    /**
     * Lo que vence entre dos fechas, ambas incluidas.
     *
     * Ordenado por fecha: un calendario agrupa por día y una lista de agenda se
     * lee de arriba abajo, y las dos quieren lo mismo.
     *
     * @param  FiltrosVencimiento|null  $filtros  lo que acote la pantalla, si acota
     * @return list<Vencimiento>
     */
    public function entre(Carbon $desde, Carbon $hasta, ?FiltrosVencimiento $filtros = null): array
    {
        $filtros ??= FiltrosVencimiento::ninguno();

        $vencimientos = [];

        foreach (Fuente::cases() as $fuente) {
            if (! $filtros->quiere($fuente)) {
                continue;
            }

            $vencimientos = [...$vencimientos, ...$this->deFuente($fuente, $desde, $hasta, $filtros)];
        }

        usort($vencimientos, static fn (Vencimiento $a, Vencimiento $b): int => [$a->dia, $a->titulo] <=> [$b->dia, $b->titulo]);

        return $vencimientos;
    }

    /**
     * La consulta de una fuente, acotada al tramo y a los filtros.
     *
     * El `match` es exhaustivo a propósito: un caso nuevo de `Fuente` que no se
     * declare aquí no compila en silencio, revienta con `UnhandledMatchError`.
     * Es lo contrario de lo que pasaba con los bloques `if`, donde la fuente
     * nueva simplemente no aparecía.
     *
     * @return list<Vencimiento>
     */
    private function deFuente(Fuente $fuente, Carbon $desde, Carbon $hasta, FiltrosVencimiento $filtros): array
    {
        return match ($fuente) {
            Fuente::Tarea => $this->deTareas($filtros->acotar(
                Tarea::query()
                    ->abiertas()
                    ->whereNotNull('fecha_limite')
                    ->whereDate('fecha_limite', '>=', $desde)
                    ->whereDate('fecha_limite', '<=', $hasta),
                'fecha_limite',
            )),

            Fuente::Evidencia => $this->deEvidencias($filtros->acotar(
                Evidencia::query()
                    ->whereNotNull('fecha_caducidad')
                    ->whereDate('fecha_caducidad', '>=', $desde)
                    ->whereDate('fecha_caducidad', '<=', $hasta),
                'fecha_caducidad',
            )),

            Fuente::Documento => $this->deDocumentos($filtros->acotar(
                Documento::query()->whereHas(
                    'versionAprobada',
                    fn (Builder $version): Builder => $version
                        ->whereNotNull('fecha_proxima_revision')
                        ->whereDate('fecha_proxima_revision', '>=', $desde)
                        ->whereDate('fecha_proxima_revision', '<=', $hasta),
                ),
                'fecha_proxima_revision',
                'versionAprobada',
            )),

            Fuente::Formacion => $this->deFormacion($filtros->acotarCalculado(
                Persona::query()->formacionVenceEntre($desde, $hasta),
                Persona::expresionRenovacionFormativa(),
            )),

            Fuente::Indicador => $this->deIndicadores($desde, $hasta, $filtros),

            Fuente::Implantacion => $this->deImplantaciones($filtros->acotar(
                Implantacion::query()->objetivoEntre($desde, $hasta),
                'implantaciones.fecha_objetivo',
            )),

            Fuente::Obligacion => $this->deObligaciones($filtros->acotarCalculado(
                Compromiso::query()->proximaEntre($desde, $hasta),
                Compromiso::expresionProxima(),
            )),

            Fuente::PruebaContinuidad => $this->dePruebas($filtros->acotar(
                PruebaContinuidad::query()->previstaEntre($desde, $hasta),
                'fecha_prevista',
            )),

            Fuente::Bia => $this->deBias($filtros->acotar(
                BiaServicio::query()->revisionEntre($desde, $hasta),
                'fecha_revision',
            )),

            Fuente::Proveedor => $this->deProveedores($filtros->acotar(
                Proveedor::query()->reevaluacionEntre($desde, $hasta),
                'proxima_evaluacion',
            )),

            Fuente::Vulnerabilidad => $this->deVulnerabilidades($filtros->acotar(
                Vulnerabilidad::query()->plazoEntre($desde, $hasta),
                'fecha_limite',
            )),
        };
    }

    /**
     * Las tareas abiertas que vencen, en el orden y con el tono del dominio.
     *
     * @param  Builder<Tarea>  $consulta
     * @return list<Vencimiento>
     */
    public function deTareas(Builder $consulta): array
    {
        return $this->filas($consulta, 'fecha_limite', Fuente::Tarea);
    }

    /**
     * @param  Builder<Evidencia>  $consulta
     * @return list<Vencimiento>
     */
    public function deEvidencias(Builder $consulta): array
    {
        return $this->filas($consulta, 'fecha_caducidad', Fuente::Evidencia);
    }

    /**
     * Los documentos cuya revisión toca, o ya tocaba.
     *
     * **No puede pasar por `filas()`**, y no es un capricho: ese helper lee la
     * fecha de la propia fila, y aquí la fecha vive en la versión aprobada
     * mientras que el título y el responsable viven en el documento. Lo que vence
     * no es el documento, es la revisión de lo que se firmó.
     *
     * @param  Builder<Documento>  $consulta
     * @return list<Vencimiento>
     */
    public function deDocumentos(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with(['responsable:id,name', 'versionAprobada'])
            ->get()
            ->map(function (Documento $documento) use ($hoy): ?Vencimiento {
                $fecha = $documento->versionAprobada?->fecha_proxima_revision;

                if ($fecha === null) {
                    return null;
                }

                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $documento->id,
                    fuente: Fuente::Documento,
                    // Con el código delante: el auditor cita «la SoA v4», y en una
                    // lista de quince vencimientos «Declaración de Aplicabilidad»
                    // aparece tres veces sin decir cuál es cuál.
                    titulo: "{$documento->codigo} — {$documento->titulo}",
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $documento->responsable?->name,
                    tono: $this->tono($dias),
                    /*
                     * Un documento aprobado cuya revisión se pasó **no deja de
                     * estar aprobado**: lo que está vencido es la revisión. Por
                     * eso el estado dice «Revisión vencida» y no «Obsoleto», que
                     * sería decir que el documento ya no vale — y sí vale, hasta
                     * que alguien apruebe el siguiente.
                     */
                    estadoTono: $dias < 0 ? 'caducada' : 'implantado',
                    estadoEtiqueta: $dias < 0 ? 'Revisión vencida' : 'Vigente',
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A quién le toca renovar la formación, y cuándo.
     *
     * **La fila es la persona y no la sesión.** Lo que vence no es la
     * convocatoria de marzo: es que a alguien le toca volver a formarse doce
     * meses después de la última a la que asistió. La fecha la calcula
     * `Persona::expresionRenovacionFormativa()`, que es la misma que usan los
     * scopes, y por eso viene como columna añadida en vez de recalcularse aquí.
     *
     * Quien nunca ha recibido formación no sale: no hay fecha, y no la hay porque
     * nadie ha fijado ninguna. Va declarado en `Fuente::Formacion`.
     *
     * @param  Builder<Persona>  $consulta
     * @return list<Vencimiento>
     */
    public function deFormacion(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->select('personas.*')
            ->selectRaw(Persona::expresionRenovacionFormativa().' as renovacion_formativa')
            ->orderBy('renovacion_formativa')
            ->get()
            ->map(function (Persona $persona) use ($hoy): Vencimiento {
                $fecha = Carbon::parse((string) $persona->getAttribute('renovacion_formativa'))->startOfDay();
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $persona->id,
                    fuente: Fuente::Formacion,
                    titulo: (string) $persona->getAttribute('nombre'),
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    // Sin responsable a propósito: la persona ES la fila. Por eso
                    // el filtro de responsable excluye esta fuente en vez de
                    // dejarla intacta — ver `FiltrosVencimiento::quiere()`.
                    responsable: null,
                    tono: $this->tono($dias),
                    estadoTono: $dias < 0 ? 'caducada' : 'implantado',
                    estadoEtiqueta: $dias < 0 ? 'Formación caducada' : 'Formación vigente',
                );
            })
            ->values()
            ->all();
    }

    /**
     * Los indicadores cuyo último periodo cerrado pasó sin medirse.
     *
     * **La fecha es el fin de ese periodo, y se calcula en PHP.** No hay columna:
     * sale de `Periodicidad::periodoAnteriorA()`, que es la misma función que usa
     * `Indicador::periodoSinMedir()`. Reescribirla en SQL para poder acotar por
     * rango sería la tercera copia de una regla que ya está en dos sitios, y aquí
     * se puede resolver en memoria: los indicadores de una organización son
     * decenas, no decenas de miles.
     *
     * Por eso esta fuente no pasa por `acotar()`: filtra después, sobre la lista.
     *
     * @return list<Vencimiento>
     */
    public function deIndicadores(Carbon $desde, Carbon $hasta, FiltrosVencimiento $filtros): array
    {
        $hoy = Carbon::today();

        $consulta = $filtros->acotar(Indicador::query()->periodoSinMedir(), 'indicadores.created_at');

        return $consulta
            ->with('responsable:id,name')
            ->get()
            ->map(function (Indicador $indicador) use ($hoy): Vencimiento {
                [, $fin] = $indicador->periodicidad->periodoAnteriorA($hoy);

                $fecha = Carbon::parse($fin)->startOfDay();
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $indicador->id,
                    fuente: Fuente::Indicador,
                    titulo: "{$indicador->codigo} — {$indicador->nombre}",
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $indicador->responsable?->name,
                    tono: $this->tono($dias),
                    /*
                     * Nunca en verde: si está en esta lista es que el periodo
                     * cerró sin medición. Lo único que cambia es si además ya se
                     * pasó de fecha, que es lo que gasta el rojo.
                     */
                    estadoTono: $dias < 0 ? 'caducada' : 'no_iniciado',
                    estadoEtiqueta: $dias < 0 ? 'Periodo sin medir' : 'Periodo abierto',
                );
            })
            /*
             * El recorte por el tramo va aquí y no en la consulta, por lo dicho
             * arriba. `entre()` lo espera acotado: las casillas de relleno de la
             * rejilla son días de verdad y el mes de al lado no.
             */
            ->filter(fn (Vencimiento $vencimiento): bool => $vencimiento->dia >= $desde->toDateString() && $vencimiento->dia <= $hasta->toDateString())
            ->values()
            ->all();
    }

    /**
     * Las medidas pendientes del plan de adecuación que llegan a su fecha
     * objetivo.
     *
     * Con esto, la limitación que el plan llevaba impresa —«el calendario todavía
     * no incluye las fechas objetivo»— deja de ser cierta.
     *
     * @param  Builder<Implantacion>  $consulta
     * @return list<Vencimiento>
     */
    public function deImplantaciones(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with(['responsable:id,name', 'requisito:id,codigo,titulo'])
            ->orderBy('implantaciones.fecha_objetivo')
            ->get()
            ->map(function (Implantacion $implantacion) use ($hoy): Vencimiento {
                /** @var Carbon $fecha */
                $fecha = $implantacion->fecha_objetivo;
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $implantacion->id,
                    fuente: Fuente::Implantacion,
                    // Con el código delante, como los documentos: en una lista de
                    // quince, «Registro de actividad» no dice de qué medida es.
                    titulo: trim("{$implantacion->requisito?->codigo} — {$implantacion->requisito?->titulo}", ' —'),
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $implantacion->responsable?->name,
                    tono: $this->tono($dias),
                    // Por debajo del rojo, su estado real: el enum ya declara el
                    // tono y la etiqueta de cada uno.
                    estadoTono: $dias < 0 ? 'caducada' : $implantacion->estado->tono(),
                    estadoEtiqueta: $dias < 0 ? 'Fuera de fecha objetivo' : $implantacion->estado->etiqueta(),
                );
            })
            ->values()
            ->all();
    }

    /**
     * Los compromisos periódicos a los que les toca.
     *
     * La fecha la calcula `Compromiso::expresionProxima()` —el último
     * `cubre_hasta` o, sin cumplimientos, `computa_desde` más la cadencia—, que
     * es la misma expresión que usan los scopes. Viene como columna añadida para
     * no resolverla otra vez por fila.
     *
     * @param  Builder<Compromiso>  $consulta
     * @return list<Vencimiento>
     */
    public function deObligaciones(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->select('compromisos.*')
            ->selectRaw(Compromiso::expresionProxima().' as proxima_fecha')
            ->orderBy('proxima_fecha')
            ->get()
            ->map(function (Compromiso $compromiso) use ($hoy): Vencimiento {
                $fecha = Carbon::parse((string) $compromiso->getAttribute('proxima_fecha'))->startOfDay();
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $compromiso->id,
                    fuente: Fuente::Obligacion,
                    titulo: $compromiso->titulo,
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $compromiso->responsable?->name,
                    tono: $this->tono($dias),
                    estadoTono: $dias < 0 ? 'caducada' : 'implantado',
                    estadoEtiqueta: $dias < 0 ? 'Fuera de plazo' : 'Al día',
                );
            })
            ->values()
            ->all();
    }

    /**
     * Las pruebas de continuidad planificadas que vencen: § 4.11 y `op.cont.3`.
     *
     * **Sólo las `planificada` llegan aquí**, porque `previstaEntre()` ya las
     * filtra: una realizada o una cancelada son terminales
     * (`EstadoPrueba::esTerminal()`) y no tienen nada pendiente que anunciar.
     *
     * @param  Builder<PruebaContinuidad>  $consulta
     * @return list<Vencimiento>
     */
    public function dePruebas(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->orderBy('pruebas_continuidad.fecha_prevista')
            ->get()
            ->map(function (PruebaContinuidad $prueba) use ($hoy): Vencimiento {
                /** @var Carbon $fecha */
                $fecha = $prueba->fecha_prevista;
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $prueba->id,
                    fuente: Fuente::PruebaContinuidad,
                    // Con el código delante, como los documentos y las medidas:
                    // en una lista de quince, el título solo no dice cuál es.
                    titulo: "{$prueba->codigo} — {$prueba->titulo}",
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $prueba->responsable?->name,
                    tono: $this->tono($dias),
                    estadoTono: $dias < 0 ? 'caducada' : $prueba->estado->tono(),
                    estadoEtiqueta: $dias < 0 ? 'Sin realizar' : $prueba->estado->etiqueta(),
                );
            })
            ->values()
            ->all();
    }

    /**
     * Los BIA aprobados cuya revisión toca, o ya tocaba: § 4.11.
     *
     * **Sólo los `aprobado` llegan aquí**, porque `revisionEntre()` ya los
     * filtra: un BIA en borrador conserva su `fecha_revision` a propósito —ver
     * `CambiarEstadoBia`— pero esa fecha no es una revisión vigente que se
     * pueda incumplir mientras no vuelva a estar aprobado.
     *
     * @param  Builder<BiaServicio>  $consulta
     * @return list<Vencimiento>
     */
    public function deBias(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with(['activo:id,nombre', 'responsable:id,name'])
            ->orderBy('bia_servicios.fecha_revision')
            ->get()
            ->map(function (BiaServicio $bia) use ($hoy): Vencimiento {
                /** @var Carbon $fecha */
                $fecha = $bia->fecha_revision;
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $bia->id,
                    fuente: Fuente::Bia,
                    titulo: "BIA — {$bia->activo?->nombre}",
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $bia->responsable?->name,
                    tono: $this->tono($dias),
                    /*
                     * Un BIA aprobado cuya revisión se pasó sigue aprobado: lo
                     * que está vencido es la revisión, no el análisis. Mismo
                     * criterio que `deDocumentos()`.
                     */
                    estadoTono: $dias < 0 ? 'caducada' : 'implantado',
                    estadoEtiqueta: $dias < 0 ? 'Revisión vencida' : 'Vigente',
                );
            })
            ->values()
            ->all();
    }

    /**
     * Los proveedores cuya reevaluación toca, o ya tocaba: § 4.9.
     *
     * Un proveedor homologado cuya reevaluación se pasó sigue homologado: lo
     * vencido es la comprobación, no la relación. Mismo criterio que
     * `deBias()` y `deDocumentos()`.
     *
     * @param  Builder<Proveedor>  $consulta
     * @return list<Vencimiento>
     */
    public function deProveedores(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->orderBy('proveedores.proxima_evaluacion')
            ->get()
            ->map(function (Proveedor $proveedor) use ($hoy): Vencimiento {
                /** @var Carbon $fecha */
                $fecha = $proveedor->proxima_evaluacion;
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $proveedor->id,
                    fuente: Fuente::Proveedor,
                    titulo: "Reevaluar — {$proveedor->nombre}",
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $proveedor->responsable?->name,
                    tono: $this->tono($dias),
                    estadoTono: $dias < 0 ? 'caducada' : $proveedor->estado->tono(),
                    estadoEtiqueta: $dias < 0 ? 'Reevaluación vencida' : $proveedor->estado->etiqueta(),
                );
            })
            ->values()
            ->all();
    }

    /**
     * Las vulnerabilidades sin arreglo cuyo plazo de remediación vence, o ya
     * venció.
     *
     * @param  Builder<Vulnerabilidad>  $consulta
     * @return list<Vencimiento>
     */
    public function deVulnerabilidades(Builder $consulta): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->orderBy('vulnerabilidades.fecha_limite')
            ->get()
            ->map(function (Vulnerabilidad $vulnerabilidad) use ($hoy): Vencimiento {
                /** @var Carbon $fecha */
                $fecha = $vulnerabilidad->fecha_limite;
                $dias = (int) $hoy->diffInDays($fecha, false);

                return new Vencimiento(
                    id: $vulnerabilidad->id,
                    fuente: Fuente::Vulnerabilidad,
                    titulo: "{$vulnerabilidad->codigo} — {$vulnerabilidad->titulo}",
                    dia: $fecha->toDateString(),
                    fecha: $fecha->format('d/m/Y'),
                    dias: $dias,
                    responsable: $vulnerabilidad->responsable?->name,
                    tono: $this->tono($dias),
                    estadoTono: $dias < 0 ? 'caducada' : $vulnerabilidad->estado->tono(),
                    estadoEtiqueta: $dias < 0 ? 'Fuera de plazo' : $vulnerabilidad->estado->etiqueta(),
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Evidencia>|Builder<Tarea>  $consulta
     * @return list<Vencimiento>
     */
    private function filas(Builder $consulta, string $columna, Fuente $fuente): array
    {
        $hoy = Carbon::today();

        return $consulta
            ->with('responsable:id,name')
            ->orderBy($columna)
            ->get()
            ->map(function (Model $fila) use ($hoy, $columna, $fuente): Vencimiento {
                /** @var ?Carbon $fecha */
                $fecha = $fila->getAttribute($columna);

                // Con signo: `diffInDays` sin más devuelve siempre positivo y algo
                // vencido hace cuatro días parecería vencer dentro de cuatro, que
                // es lo contrario de lo que pasa.
                $dias = $fecha === null ? 0 : (int) $hoy->diffInDays($fecha, false);

                [$estadoTono, $estadoEtiqueta] = $this->estado($fila, $fuente, $dias);

                return new Vencimiento(
                    id: (int) $fila->getKey(),
                    fuente: $fuente,
                    titulo: (string) $fila->getAttribute('titulo'),
                    dia: $fecha?->toDateString() ?? '',
                    fecha: $fecha?->format('d/m/Y') ?? '—',
                    dias: $dias,
                    responsable: $fila->getRelationValue('responsable')?->getAttribute('name'),
                    tono: $this->tono($dias),
                    estadoTono: $estadoTono,
                    estadoEtiqueta: $estadoEtiqueta,
                );
            })
            ->values()
            ->all();
    }

    /**
     * Qué es la cosa, y con qué tono se pinta en el calendario.
     *
     * **Lo vencido gana.** Una tarea en curso que se pasó de fecha es, ante
     * todo, algo que se pasó de fecha: es el único uso que DESIGN.md reserva al
     * rojo y es lo que tiene que saltar a la vista al abrir el mes.
     *
     * Por debajo de eso, una tarea trae su estado —pendiente, en curso,
     * bloqueada— y una evidencia su vigencia. Ninguna de las dos gasta rojo.
     *
     * @return array{string, string}
     */
    private function estado(Model $fila, Fuente $fuente, int $dias): array
    {
        if ($dias < 0) {
            return ['caducada', $fuente === Fuente::Tarea ? 'Vencida' : 'Caducada'];
        }

        if ($fuente === Fuente::Evidencia) {
            return ['implantado', 'Vigente'];
        }

        $estado = $fila->getAttribute('estado');

        return $estado instanceof EstadoTarea
            ? [$estado->tono(), $estado->etiqueta()]
            : ['no_iniciado', 'Pendiente'];
    }

    /**
     * El rojo es de lo que ya se ha pasado, aquí como en la tabla.
     *
     * Una semana es lo que cabe en un sprint: más allá, avisar de que algo vence
     * no dice nada que la fecha no diga ya.
     */
    private function tono(int $dias): string
    {
        return match (true) {
            $dias < 0 => 'caducada',
            $dias <= 7 => 'en_progreso',
            default => 'implantado',
        };
    }
}
