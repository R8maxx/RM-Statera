<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\Obsolescencia;
use App\Domain\Activo\ValoracionEfectiva;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Definicion\Accion;
use App\Http\Resources\Definicion\Columna;
use App\Http\Resources\Definicion\Etiquetas;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Http\Resources\Definicion\ValorEtiquetado;
use App\Http\Resources\Enums\MetodoAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * El inventario: lo que hay que proteger.
 *
 * Dos columnas cargan con el peso de la tabla. «Valoración» enseña la
 * **efectiva** —lo que el activo vale contando lo que se apoya en él—, no la que
 * alguien tecleó: una base de datos valorada «bajo» que sostiene un servicio
 * esencial vale «alto», y ese es justo el activo que una hoja de cálculo deja
 * desprotegido. Y «Tipo» lleva color e icono porque un inventario se recorre de
 * arriba abajo buscando una familia, no leyendo fila por fila.
 *
 * El resto de columnas nacen ocultas. Son veintitantas y la vista guardada en el
 * navegador ya deja sacar las que cada puesto necesita; enseñarlas todas de
 * salida daría una tabla que sólo se lee en horizontal.
 *
 * @extends Recurso<Activo>
 */
final class ActivoRecurso extends Recurso
{
    /**
     * La valoración efectiva de todos los activos de la organización.
     *
     * Se resuelve una vez por petición y no fila a fila: cada una es un recorrido
     * del grafo, y hacerlo por fila serían cincuenta CTE recursivas por página.
     *
     * @var array<int, ValoracionDimensiones>|null
     */
    private ?array $efectivas = null;

    public function __construct(
        private readonly ValoracionEfectiva $valoracionEfectiva,
        private readonly Obsolescencia $obsolescencia,
    ) {}

    public function clave(): string
    {
        return 'activos';
    }

    public function etiquetas(): Etiquetas
    {
        return new Etiquetas(
            singular: 'Activo',
            plural: 'Activos',
            descripcion: 'Lo que hay que proteger, valorado en las cinco dimensiones. La valoración sube por el grafo: lo que sostiene algo importante es importante.',
            vacio: 'Todavía no hay ningún activo en el inventario. El primero se da de alta desde aquí.',
        );
    }

    /** @return Builder<Activo> */
    public function consulta(): Builder
    {
        return Activo::query()
            ->with(['propietario', 'custodio', 'sistemas'])
            ->withCount(['dependeDe as dependencias_count', 'dependientes as dependientes_count']);
    }

    /** @return list<Columna> */
    public function columnas(): array
    {
        return [
            Columna::texto('codigo', 'Código')->ordenable()->anclada()->ancho('8rem'),
            Columna::texto('nombre', 'Nombre')->ordenable(),
            Columna::badge('tipo', 'Tipo')
                ->ordenable()
                ->formato(fn (Activo $activo): ValorEtiquetado => new ValorEtiquetado(
                    $activo->tipo->value,
                    $activo->tipo->etiqueta(),
                    'tipo:'.$activo->tipo->value,
                    $activo->tipo->icono(),
                )),
            Columna::texto('subtipo', 'Subtipo')
                ->ordenable()
                ->ayuda('La etiqueta operativa: «Portátil», «EC2», «Router». El tipo de arriba es la tipología MAGERIT, que es la del análisis de riesgos.')
                ->formato(fn (Activo $activo): ?string => $activo->subtipo),
            Columna::badge('valoracion', 'Valoración')
                ->ayuda('El máximo de las cinco dimensiones contando lo que depende de este activo. No se elige a mano: se hereda de lo que sostiene.')
                ->formato(fn (Activo $activo): ValorEtiquetado => $this->valoracion($activo)),
            Columna::badge('clasificacion', 'Clasificación')
                ->ordenable()
                ->ayuda('La etiqueta que se le pone a la información (mp.info.2). No sustituye al nivel del Anexo I: la clasificación se decide, el nivel se deriva del perjuicio.')
                ->formato(fn (Activo $activo): ValorEtiquetado => new ValorEtiquetado(
                    $activo->clasificacion->value,
                    $activo->clasificacion->etiqueta(),
                    $activo->clasificacion->tono(),
                    $activo->clasificacion->icono(),
                )),
            Columna::badge('cifrado', 'Cifrado')
                ->ordenable()
                ->ayuda('«Por confirmar» no es «No»: la ausencia de dato no es ausencia de cifrado.')
                ->formato(fn (Activo $activo): ValorEtiquetado => new ValorEtiquetado(
                    $activo->cifrado->value,
                    $activo->cifrado->etiqueta(),
                    $activo->cifrado->tono(),
                    $activo->cifrado->icono(),
                )),
            Columna::badge('copia_seguridad', 'Copia')
                ->ordenable()
                ->formato(fn (Activo $activo): ValorEtiquetado => new ValorEtiquetado(
                    $activo->copia_seguridad->value,
                    $activo->copia_seguridad->etiqueta(),
                    $activo->copia_seguridad->tono(),
                    $activo->copia_seguridad->icono(),
                )),
            Columna::badge('estado_ciclo_vida', 'Estado')
                ->ordenable()
                ->formato(fn (Activo $activo): ValorEtiquetado => new ValorEtiquetado(
                    $activo->estado_ciclo_vida->value,
                    $activo->esperaBorradoSeguro()
                        ? $activo->estado_ciclo_vida->etiqueta().', sin borrado seguro'
                        : $activo->estado_ciclo_vida->etiqueta(),
                    $activo->esperaBorradoSeguro() ? 'caducada' : $activo->estado_ciclo_vida->tono(),
                    // Un disco retirado con los datos dentro es un hallazgo, no
                    // un activo cerrado: se pinta como lo que es.
                    $activo->esperaBorradoSeguro() ? 'TriangleAlert' : $activo->estado_ciclo_vida->icono(),
                )),
            // Cuántas piezas se caen con él. Es la cifra que se mira antes de
            // tocar nada en producción, y la que ordena el análisis de impacto.
            Columna::numero('dependientes', 'Sostiene')
                ->ayuda('Cuántos activos dependen directamente de éste. Lo que se cae si él cae.')
                ->formato(fn (Activo $activo): int => (int) $activo->getAttribute('dependientes_count')),
            Columna::texto('propietario', 'Propietario')
                ->formato(fn (Activo $activo): ?string => $activo->propietario?->name),

            /* De aquí abajo, ocultas de salida. */

            Columna::texto('identificador', 'Nº serie / ID')
                ->oculta()
                ->ordenable()
                ->ayuda('Nº de serie, ARN, hostname o IP. Es el identificador estable del activo; el código lo pone la organización.')
                ->formato(fn (Activo $activo): ?string => $activo->identificador),
            Columna::texto('custodio', 'Custodio')
                ->oculta()
                ->formato(fn (Activo $activo): ?string => $activo->custodio?->name),
            Columna::texto('departamento', 'Departamento')->oculta()->ordenable(),
            Columna::texto('marca_modelo', 'Marca y modelo')->oculta()->ordenable(),
            // El sistema operativo y su soporte van juntos: la versión sola no
            // dice si hay un problema, hay que compararla con hoy.
            Columna::badge('sistema_operativo', 'Sistema operativo')
                ->oculta()
                ->ordenable()
                ->formato(fn (Activo $activo): ?ValorEtiquetado => $this->sistemaOperativo($activo)),
            Columna::fecha('fin_garantia', 'Fin garantía')->oculta()->ordenable(),
            Columna::badge('revision', 'Última revisión')
                ->oculta()
                ->ordenable('ultima_revision')
                ->ayuda('Un inventario que no se revisa no está mantenido, y eso es lo que pide A.5.9, no el listado.')
                ->formato(fn (Activo $activo): ValorEtiquetado => $this->revision($activo)),
            Columna::badge('valoracion_propia', 'Valoración propia')
                ->oculta()
                ->ayuda('Lo que valoró la organización, sin contar el grafo. Es lo que el auditor contrasta.')
                ->formato(function (Activo $activo): ValorEtiquetado {
                    $categoria = $activo->categoria();

                    return $categoria === null
                        ? new ValorEtiquetado(null, 'Sin valorar', 'no_iniciado')
                        : new ValorEtiquetado($categoria->value, $categoria->etiqueta(), $categoria->value);
                }),
            Columna::numero('dependencias', 'Depende de')
                ->oculta()
                ->formato(fn (Activo $activo): int => (int) $activo->getAttribute('dependencias_count')),
            Columna::texto('alcance', 'Alcance')
                ->oculta()
                ->ayuda('Los sistemas en cuyo alcance está declarado. Un mismo activo puede estar en varios.')
                ->formato(fn (Activo $activo): ?string => $activo->sistemas->isEmpty()
                    ? null
                    : $activo->sistemas->pluck('codigo')->implode(', ')),
            Columna::texto('ubicacion', 'Ubicación')->oculta()->ordenable(),
            Columna::fecha('fecha_alta', 'Alta')->ordenable()->oculta(),
            Columna::fecha('fecha_baja', 'Baja')->ordenable()->oculta(),
        ];
    }

    /** @return list<Filtro> */
    public function filtros(): array
    {
        return [
            Filtro::busqueda('q', 'Buscar', [
                'codigo' => 'codigo',
                'nombre' => 'nombre',
                'descripcion' => 'nombre',
                'identificador' => 'identificador',
                'marca_modelo' => 'marca_modelo',
                'ubicacion' => 'ubicacion',
            ])->placeholder('Buscar por código, nombre, nº de serie o modelo…'),
            Filtro::texto('codigo', 'Código'),
            Filtro::texto('nombre', 'Nombre'),
            Filtro::multiSelect('tipo', 'Tipo', array_map(
                static fn (TipoActivo $tipo): Opcion => new Opcion($tipo->value, $tipo->etiqueta()),
                TipoActivo::cases(),
            )),
            Filtro::texto('subtipo', 'Subtipo'),
            Filtro::multiSelect('estado_ciclo_vida', 'Estado', array_map(
                static fn (EstadoCicloVida $estado): Opcion => new Opcion($estado->value, $estado->etiqueta()),
                EstadoCicloVida::cases(),
            )),
            Filtro::multiSelect('clasificacion', 'Clasificación', array_map(
                static fn (Clasificacion $clasificacion): Opcion => new Opcion(
                    $clasificacion->value,
                    $clasificacion->etiqueta(),
                ),
                Clasificacion::cases(),
            )),
            Filtro::multiSelect('cifrado', 'Cifrado', $this->opcionesDeControl()),
            Filtro::multiSelect('copia_seguridad', 'Copia', $this->opcionesDeControl()),
            Filtro::select('propietario_id', 'Propietario', $this->usuarios(...))->enColumna('propietario'),
            Filtro::select('custodio_id', 'Custodio', $this->usuarios(...))->enColumna('custodio'),
            Filtro::texto('departamento', 'Departamento'),
            Filtro::texto('identificador', 'Nº serie / ID'),
            // Por relación y no por join: un activo en el alcance de dos sistemas
            // saldría dos veces en la tabla y la paginación contaría mal.
            Filtro::porRelacion('sistema_id', 'Alcance', 'sistemas', 'sistemas.id', fn (): array => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Sistema $sistema): Opcion => new Opcion((string) $sistema->id, $sistema->codigo.' — '.$sistema->nombre))
                ->all())->enColumna('alcance'),
            Filtro::texto('ubicacion', 'Ubicación'),
            Filtro::rangoFechas('fecha_alta', 'Alta'),

            /*
             * Los interruptores del panel de indicadores. Cada uno delega en el
             * MISMO scope con el que `ResumenInventario` cuenta, para que pulsar
             * una cifra enseñe exactamente esa cifra. Viven en el desplegable
             * «Filtros» y no bajo una columna: son preguntas sobre la fila
             * entera, no sobre un dato.
             */
            Filtro::porScope('sin_cifrado', 'Sin cifrado', 'sinCifrado')->sinColumna(),
            Filtro::porScope('sin_copia', 'Sin copia de seguridad', 'sinCopia')->sinColumna(),
            Filtro::porScope('por_confirmar', 'Cifrado o copia por confirmar', 'controlPorConfirmar')->sinColumna(),
            Filtro::porScope('sin_propietario', 'Sin propietario', 'sinPropietario')->sinColumna(),
            Filtro::porScope('sin_identificador', 'Sin nº de serie', 'sinIdentificador')->sinColumna(),
            Filtro::porScope('sin_ubicacion', 'Sin ubicación', 'sinUbicacion')->sinColumna(),
            Filtro::porScope('sin_revisar', 'Sin revisar en 12 meses', 'sinRevisar')->sinColumna(),
            Filtro::porScope('restringida', 'Con información restringida', 'informacionRestringida')->sinColumna(),
            Filtro::porScope('sin_soporte', 'Soporte o garantía vencidos', 'sinSoporte')->sinColumna(),
            Filtro::porScope('espera_borrado', 'Retirados sin borrado seguro', 'esperaBorradoSeguro')->sinColumna(),
        ];
    }

    /** @return list<Accion> */
    public function accionesFila(): array
    {
        return [
            Accion::ver('/activos/{id}'),
            Accion::editar('/activos/{id}/editar')->permiso(Permiso::ActivosGestionar->value),
            Accion::eliminar(
                '/activos/{id}',
                '¿Eliminar el activo? Se van con él sus dependencias declaradas, y lo que se apoyaba en él deja de heredar su valoración.',
            )->permiso(Permiso::ActivosGestionar->value),
        ];
    }

    /** @return list<Accion> */
    public function accionesMasivas(): array
    {
        return [
            (new Accion('revisar', 'Marcar como revisados hoy', '/activos/revision', MetodoAccion::Post))
                ->icono('CalendarCheck')
                ->permiso(Permiso::ActivosGestionar->value),
            (new Accion('etiquetas', 'Imprimir etiquetas', '/activos/etiquetas', MetodoAccion::Get))
                ->icono('QrCode'),
        ];
    }

    /** @return list<Accion> */
    public function accionesGenerales(): array
    {
        return [
            (new Accion('crear', 'Nuevo activo', '/activos/crear', MetodoAccion::Get))
                ->icono('Plus')
                ->permiso(Permiso::ActivosGestionar->value),
            (new Accion('todas-las-etiquetas', 'Etiquetas QR', '/activos/etiquetas', MetodoAccion::Get))
                ->icono('QrCode'),
        ];
    }

    /** Hace falta para las acciones masivas: revisar y etiquetar van por lote. */
    public function seleccionable(): bool
    {
        return true;
    }

    public function ordenPorDefecto(): string
    {
        return 'codigo';
    }

    /**
     * @return array<string, mixed>
     */
    public function extrasDeFila(Model $modelo): array
    {
        if (! $modelo instanceof Activo) {
            return [];
        }

        return [
            'heredada' => $this->efectiva($modelo)->nivelMaximo()->peso() > $modelo->valoracion()->nivelMaximo()->peso(),
        ];
    }

    /** @return list<Opcion> */
    private function opcionesDeControl(): array
    {
        return array_map(
            static fn (EstadoControl $control): Opcion => new Opcion($control->value, $control->etiqueta()),
            EstadoControl::cases(),
        );
    }

    /** @return list<Opcion> */
    private function usuarios(): array
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
            ->all();
    }

    private function sistemaOperativo(Activo $activo): ?ValorEtiquetado
    {
        if ($activo->sistema_operativo === null) {
            return null;
        }

        $vencido = $this->obsolescencia->soporteVencido($activo);

        return new ValorEtiquetado(
            $activo->sistema_operativo,
            $vencido ? $activo->sistema_operativo.' · sin soporte' : $activo->sistema_operativo,
            $vencido ? 'caducada' : 'no_iniciado',
        );
    }

    /**
     * «Nunca revisado» y «revisado hace catorce meses» se cuentan igual pero no
     * se leen igual: al primero le falta el alta en el registro, al segundo le
     * toca la vuelta. La etiqueta lo dice.
     */
    private function revision(Activo $activo): ValorEtiquetado
    {
        if ($activo->ultima_revision === null) {
            return new ValorEtiquetado(null, 'Nunca', 'caducada');
        }

        $etiqueta = $activo->ultima_revision->translatedFormat('d/m/Y');

        return $activo->sinRevisar()
            ? new ValorEtiquetado($activo->ultima_revision->toDateString(), $etiqueta.' · vencida', 'caducada')
            : new ValorEtiquetado($activo->ultima_revision->toDateString(), $etiqueta, 'implantado');
    }

    /**
     * «Sin valorar» y «No aplica» no son lo mismo, y aquí casi siempre es lo
     * primero: un activo recién dado de alta al que nadie ha mirado todavía. Que
     * se lea igual que una decisión tomada escondería el trabajo pendiente.
     */
    private function valoracion(Activo $activo): ValorEtiquetado
    {
        $efectiva = $this->efectiva($activo);
        $categoria = $efectiva->categoria();

        if ($categoria === null) {
            return new ValorEtiquetado(null, 'Sin valorar', 'no_iniciado');
        }

        $heredada = $efectiva->nivelMaximo()->peso() > $activo->valoracion()->nivelMaximo()->peso();

        return new ValorEtiquetado(
            $categoria->value,
            $heredada ? $efectiva->nivelMaximo()->etiqueta().' (heredado)' : $efectiva->nivelMaximo()->etiqueta(),
            $categoria->value,
        );
    }

    private function efectiva(Activo $activo): ValoracionDimensiones
    {
        $this->efectivas ??= $this->valoracionEfectiva->paraLaOrganizacion();

        return $this->efectivas[$activo->id] ?? $activo->valoracion();
    }
}
