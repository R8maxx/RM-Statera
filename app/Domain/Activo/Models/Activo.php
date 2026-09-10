<?php

declare(strict_types=1);

namespace App\Domain\Activo\Models;

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Database\Factories\ActivoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Una pieza del inventario: lo que hay que proteger.
 *
 * Guarda su valoración PROPIA en las cinco dimensiones. La efectiva —el máximo
 * con la de todo lo que depende de él— la calcula
 * `App\Domain\Activo\ValoracionEfectiva` recorriendo el grafo, y no se almacena:
 * una copia denormalizada se desincroniza del grafo que la justifica, que es
 * exactamente lo que el auditor contrasta.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $descripcion
 * @property TipoActivo $tipo
 * @property ?string $subtipo
 * @property ?string $marca_modelo
 * @property ?string $especificaciones
 * @property ?string $sistema_operativo
 * @property ?Carbon $fin_soporte_so
 * @property ?string $identificador
 * @property ?int $propietario_id
 * @property ?int $custodio_id
 * @property ?string $departamento
 * @property ?string $ubicacion
 * @property ?Carbon $fin_garantia
 * @property EstadoCicloVida $estado_ciclo_vida
 * @property Clasificacion $clasificacion
 * @property EstadoControl $cifrado
 * @property EstadoControl $copia_seguridad
 * @property ?Carbon $ultima_revision
 * @property ?Carbon $etiquetado_en
 * @property ?string $observaciones
 * @property string $valor_c
 * @property string $valor_i
 * @property string $valor_d
 * @property string $valor_a
 * @property string $valor_t
 * @property ?Carbon $fecha_alta
 * @property ?Carbon $fecha_baja
 * @property ?Carbon $borrado_seguro_en
 * @property ?string $nota_baja
 */
class Activo extends Model
{
    /** @use HasFactory<ActivoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'activos';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'subtipo',
        'marca_modelo',
        'especificaciones',
        'sistema_operativo',
        'fin_soporte_so',
        'identificador',
        'propietario_id',
        'custodio_id',
        'departamento',
        'ubicacion',
        'fin_garantia',
        'estado_ciclo_vida',
        'clasificacion',
        'cifrado',
        'copia_seguridad',
        'ultima_revision',
        'etiquetado_en',
        'observaciones',
        'valor_c',
        'valor_i',
        'valor_d',
        'valor_a',
        'valor_t',
        'fecha_alta',
        'fecha_baja',
        'borrado_seguro_en',
        'nota_baja',
    ];

    /**
     * La columna donde vive el nivel de cada dimensión.
     *
     * Está aquí y no repartida por el código porque las CTE de
     * `GrafoActivos` y `ValoracionEfectiva` construyen SQL con estos nombres:
     * si alguien renombra una columna, este mapa es el sitio donde se entera.
     *
     * @return array<string, string>
     */
    public static function columnasDeValoracion(): array
    {
        $columnas = [];

        foreach (Dimension::cases() as $dimension) {
            $columnas[$dimension->value] = 'valor_'.mb_strtolower($dimension->value);
        }

        return $columnas;
    }

    /**
     * Quien responde del activo.
     *
     * @return BelongsTo<User, $this>
     */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    /**
     * Quien lo usa y lo tiene en su poder.
     *
     * Separado del propietario a propósito: cuando un portátil cambia de manos
     * sólo cambia el custodio. El activo conserva su código y la etiqueta pegada
     * en la carcasa sigue valiendo.
     *
     * @return BelongsTo<User, $this>
     */
    public function custodio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodio_id');
    }

    /**
     * Los sistemas en cuyo alcance está declarado este activo.
     *
     * N:M a propósito: el mismo servidor entra en el alcance del SGSI de ISO y
     * del sistema del ENS, y duplicarlo para que quepa en los dos sería volver
     * al problema que el producto resuelve.
     *
     * @return BelongsToMany<Sistema, $this>
     */
    public function sistemas(): BelongsToMany
    {
        return $this->belongsToMany(Sistema::class, 'activo_sistema')
            ->withPivot(['organizacion_id', 'created_at']);
    }

    /**
     * De qué depende este activo: lo que necesita para funcionar.
     *
     * @return BelongsToMany<self, $this>
     */
    public function dependeDe(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'activo_dependencias', 'activo_id', 'depende_de_id')
            ->withPivot(['nota', 'created_at']);
    }

    /**
     * Qué depende de este activo: lo que se cae si él cae.
     *
     * Es la dirección por la que sube la valoración, y la que se mira antes de
     * tocar nada en producción.
     *
     * @return BelongsToMany<self, $this>
     */
    public function dependientes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'activo_dependencias', 'depende_de_id', 'activo_id')
            ->withPivot(['nota', 'created_at']);
    }

    /**
     * La valoración propia como value object, para reutilizar lo que ya sabe
     * comparar y ordenar niveles.
     */
    public function valoracion(): ValoracionDimensiones
    {
        $niveles = [];

        foreach (self::columnasDeValoracion() as $dimension => $columna) {
            $niveles[$dimension] = (string) $this->getAttribute($columna);
        }

        return ValoracionDimensiones::desdeArray($niveles);
    }

    /**
     * La categoría que le correspondería por su valoración propia: el máximo de
     * las cinco. Nula cuando las cinco son `na`.
     */
    public function categoria(): ?CategoriaEns
    {
        return $this->valoracion()->categoria();
    }

    /**
     * Un activo retirado sin constancia del borrado seguro es un hallazgo, no un
     * activo cerrado: el soporte sigue por ahí con los datos dentro.
     */
    public function esperaBorradoSeguro(): bool
    {
        return ! $this->estado_ciclo_vida->estaVigente() && $this->borrado_seguro_en === null;
    }

    /** Si lleva —o debería llevar— una etiqueta QR pegada encima. */
    public function llevaEtiqueta(): bool
    {
        return $this->tipo->esFisico() && $this->estado_ciclo_vida->estaVigente();
    }

    /**
     * Sin revisar desde hace más de un año.
     *
     * Un activo que nunca se ha revisado cuenta como pendiente: es justo el que
     * lleva ahí desde el alta inicial sin que nadie lo haya vuelto a mirar.
     */
    public function sinRevisar(int $meses = 12): bool
    {
        return $this->ultima_revision === null
            || $this->ultima_revision->lt(Carbon::today()->subMonths($meses));
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinRevisar(Builder $query, int $meses = 12): void
    {
        $query->where(function (Builder $anidada) use ($meses): void {
            $anidada->whereNull('ultima_revision')
                ->orWhereDate('ultima_revision', '<', Carbon::today()->subMonths($meses));
        });
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinPropietario(Builder $query): void
    {
        $query->whereNull('propietario_id');
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinIdentificador(Builder $query): void
    {
        $query->where(fn (Builder $anidada) => $anidada->whereNull('identificador')->orWhere('identificador', ''));
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinUbicacion(Builder $query): void
    {
        $query->where(fn (Builder $anidada) => $anidada->whereNull('ubicacion')->orWhere('ubicacion', ''));
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinCifrado(Builder $query): void
    {
        $query->where('cifrado', EstadoControl::No->value);
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinCopia(Builder $query): void
    {
        $query->where('copia_seguridad', EstadoControl::No->value);
    }

    /** @param  Builder<$this>  $query */
    public function scopeControlPorConfirmar(Builder $query): void
    {
        $query->where(function (Builder $anidada): void {
            $anidada->where('cifrado', EstadoControl::PorConfirmar->value)
                ->orWhere('copia_seguridad', EstadoControl::PorConfirmar->value);
        });
    }

    /** @param  Builder<$this>  $query */
    public function scopeInformacionRestringida(Builder $query): void
    {
        $query->where('clasificacion', Clasificacion::Restringido->value);
    }

    /**
     * Retirado o dado de baja sin constancia de qué se hizo con lo que
     * contenía.
     *
     * Es un hallazgo de `mp.si.5`, no un dato incompleto: el soporte sigue por
     * ahí con la información dentro. Existía por activo —`esperaBorradoSeguro()`—
     * pero no se contaba en ninguna parte, así que nadie lo veía hasta abrir la
     * ficha de uno.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeEsperaBorradoSeguro(Builder $query): void
    {
        $query->whereIn('estado_ciclo_vida', [
            EstadoCicloVida::Retirado->value,
            EstadoCicloVida::DadoDeBaja->value,
        ])->whereNull('borrado_seguro_en');
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinSoporte(Builder $query): void
    {
        $query->where(function (Builder $anidada): void {
            $anidada->whereDate('fin_soporte_so', '<', Carbon::today())
                ->orWhereDate('fin_garantia', '<', Carbon::today());
        });
    }

    /** @param  Builder<$this>  $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereIn('estado_ciclo_vida', array_values(array_map(
            static fn (EstadoCicloVida $estado): string => $estado->value,
            array_filter(
                EstadoCicloVida::cases(),
                static fn (EstadoCicloVida $estado): bool => $estado->estaVigente(),
            ),
        )));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoActivo::class,
            'estado_ciclo_vida' => EstadoCicloVida::class,
            'clasificacion' => Clasificacion::class,
            'cifrado' => EstadoControl::class,
            'copia_seguridad' => EstadoControl::class,
            'fecha_alta' => 'date',
            'fecha_baja' => 'date',
            'fin_soporte_so' => 'date',
            'fin_garantia' => 'date',
            'ultima_revision' => 'date',
            'borrado_seguro_en' => 'datetime',
            'etiquetado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): ActivoFactory
    {
        return ActivoFactory::new();
    }
}
