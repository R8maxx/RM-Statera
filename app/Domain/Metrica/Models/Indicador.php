<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Models;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\CumplimientoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Metrica\IndicadorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Qué mide la organización, cada cuánto y contra qué objetivo. Cláusula 9.1.
 *
 * **Choca de nombre corto con `App\Http\Resources\Panel\Indicador`**, que es
 * otra cosa: aquél es «una cifra que pide acción, con el camino para ir a
 * verla», la baldosa del panel que nació en el inventario y se generalizó a
 * cualquier módulo con tabla. Éste tiene objetivo, periodicidad, responsable y
 * serie histórica. Es el caso de `Contexto` frente a `ContextoOrganizacion` y se
 * resuelve igual: **no se renombra nada**, se anota, y los dos ficheros donde
 * convivan importan uno con alias. Renombrar el VO toca seis resúmenes de panel,
 * los tipos generados y `TiraIndicadores.vue` para ganar cero.
 *
 * El contexto se llama `Metrica` y no `Indicador` por lo mismo: § 4.14 se titula
 * «Métricas», y `App\Domain\Indicador\Models\Indicador` tartamudea.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $descripcion
 * @property OrigenMedicion $origen
 * @property ?CalculoIndicador $calculo
 * @property ?string $formula_o_fuente
 * @property ?int $marco_id
 * @property UnidadIndicador $unidad
 * @property SentidoIndicador $sentido
 * @property Periodicidad $periodicidad
 * @property ?float $objetivo
 * @property ?int $responsable_id
 * @property bool $activo
 */
class Indicador extends Model
{
    /** @use HasFactory<IndicadorFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'indicadores';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'nombre',
        'descripcion',
        'origen',
        'calculo',
        'formula_o_fuente',
        'marco_id',
        'unidad',
        'sentido',
        'periodicidad',
        'objetivo',
        'responsable_id',
        'activo',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * El marco al que se acota, si se acota a alguno.
     *
     * Del catálogo global, que no lleva `organizacion_id` (invariante 2).
     *
     * @return BelongsTo<Marco, $this>
     */
    public function marco(): BelongsTo
    {
        return $this->belongsTo(Marco::class, 'marco_id');
    }

    /**
     * La serie, de lo más reciente a lo más antiguo.
     *
     * Ordenada por **periodo** y no por `created_at`: una medición de marzo
     * apuntada en abril tiene que salir antes que la de abril, o la serie que se
     * pinta no es la serie que ocurrió.
     *
     * @return HasMany<Medicion, $this>
     */
    public function mediciones(): HasMany
    {
        return $this->hasMany(Medicion::class)->orderByDesc('periodo_inicio');
    }

    /** @return HasOne<Medicion, $this> */
    public function ultimaMedicion(): HasOne
    {
        return $this->hasOne(Medicion::class)->ofMany('periodo_inicio', 'max');
    }

    /**
     * Cómo va respecto a su objetivo. **Se deriva, no se guarda.**
     *
     * Los cuatro casos no son dos: «sin objetivo» y «sin medir» no son «fuera de
     * objetivo», por el argumento de `EstadoControl::PorConfirmar`. Un panel que
     * los colapsara saldría en rojo el día que se crea el primer indicador.
     */
    public function cumplimiento(?Medicion $medicion = null): CumplimientoIndicador
    {
        $medicion ??= $this->ultimaMedicion;

        if ($medicion === null) {
            return CumplimientoIndicador::SinMedir;
        }

        // El que se aplicó a ese periodo, no el de hoy: subir el listón en marzo
        // no puede reescribir el veredicto de enero.
        $objetivo = $medicion->objetivo;

        if ($objetivo === null) {
            return CumplimientoIndicador::SinObjetivo;
        }

        return $this->sentido->alcanza((float) $medicion->valor, (float) $objetivo)
            ? CumplimientoIndicador::EnObjetivo
            : CumplimientoIndicador::FueraDeObjetivo;
    }

    /**
     * El último periodo que ya ha terminado, que es el que toca medir.
     *
     * Se mide el cerrado y no el que está en curso: una cifra a medias habría
     * que corregirla al día siguiente, y la serie contaría un trimestre que
     * todavía no ha pasado.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodoACerrar(?Carbon $hoy = null): array
    {
        return $this->periodicidad->periodoAnteriorA($hoy ?? Carbon::today());
    }

    /**
     * Si el último periodo cerrado se quedó sin medir.
     *
     * **Éste es el rojo del módulo, y no estar por debajo del objetivo.** Quedarse
     * corto respecto a una cifra que la propia organización se puso es la
     * distancia que queda; no medir habiéndose comprometido a medir cada
     * trimestre es la 9.1 sin hacer.
     */
    public function tienePeriodoSinMedir(?Carbon $hoy = null): bool
    {
        if (! $this->activo) {
            return false;
        }

        [$inicio] = $this->periodoACerrar($hoy);

        return ! $this->mediciones()->whereDate('periodo_inicio', $inicio)->exists();
    }

    /**
     * Los que se siguen midiendo.
     *
     * Retirar no es borrar: un indicador que deja de medirse conserva su serie,
     * y esa serie es la que explica por qué se dejó de medir.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('indicadores.activo', true);
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinObjetivo(Builder $query): void
    {
        $query->whereNull('indicadores.objetivo');
    }

    /**
     * Los que no tienen ninguna medición todavía.
     *
     * Un indicador declarado y nunca medido es una promesa, no un seguimiento, y
     * es lo primero que se comprueba en una auditoría de la 9.1.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinMedir(Builder $query): void
    {
        $query->whereDoesntHave('mediciones');
    }

    /**
     * Los que se saltaron el último periodo cerrado.
     *
     * **Es el rojo del módulo**, y por eso tiene que ser un scope y no un bucle
     * en PHP: lo invocan por nombre la cifra del panel y el filtro de la tabla,
     * que es lo que garantiza que pulsar el número enseñe exactamente ese número.
     *
     * Va por un `OR` por periodicidad y no por una fecha común porque el periodo
     * cerrado no es el mismo para un indicador mensual que para uno anual. Son
     * cuatro ramas, no cuatro consultas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePeriodoSinMedir(Builder $query, ?Carbon $hoy = null): void
    {
        $hoy ??= Carbon::today();

        $query->where('indicadores.activo', true)
            ->where(function (Builder $agrupado) use ($hoy): void {
                foreach (Periodicidad::cases() as $periodicidad) {
                    [$inicio] = $periodicidad->periodoAnteriorA($hoy);

                    $agrupado->orWhere(
                        fn (Builder $rama): Builder => $rama
                            ->where('indicadores.periodicidad', $periodicidad->value)
                            ->whereDoesntHave(
                                'mediciones',
                                fn (Builder $mediciones): Builder => $mediciones->whereDate('periodo_inicio', $inicio),
                            ),
                    );
                }
            });
    }

    /**
     * Los que en su última medición no alcanzaron el objetivo.
     *
     * **Aquí la regla está escrita dos veces** —en SQL y en
     * `SentidoIndicador::alcanza()`— y no hay forma de evitarlo: una la aplica
     * PostgreSQL sobre miles de filas y la otra decide el badge de una. Es el
     * mismo caso que `ValoracionEfectiva`, que tiene dos entradas y un test que
     * fija que coinciden; sin él, la tabla enseñaría una cifra y la ficha otra.
     * Ese test es `Metricas/CumplimientoCoincideTest`, y recorre la matriz de los
     * dos sentidos contra el umbral, por encima y por debajo.
     *
     * Se compara contra el objetivo **congelado en la fila**, no contra el del
     * indicador: subir el listón en marzo no repinta enero.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFueraDeObjetivo(Builder $query): void
    {
        $query->whereExists(function (QueryBuilder $existe): void {
            $existe->selectRaw('1')
                ->from('mediciones')
                ->whereColumn('mediciones.indicador_id', 'indicadores.id')
                ->whereNotNull('mediciones.objetivo')
                ->whereRaw('mediciones.periodo_inicio = (select max(m2.periodo_inicio) from mediciones m2 where m2.indicador_id = indicadores.id)')
                ->whereRaw(sprintf(
                    "((indicadores.sentido = '%s' and mediciones.valor < mediciones.objetivo) "
                    ."or (indicadores.sentido = '%s' and mediciones.valor > mediciones.objetivo))",
                    SentidoIndicador::MayorMejor->value,
                    SentidoIndicador::MenorMejor->value,
                ));
        });
    }

    /**
     * Resuelve `/indicadores/{indicador}/mediciones/{medicion}` acotando la
     * medición a su indicador.
     *
     * **Hay que escribirlo a mano, y es el segundo sitio del producto donde pasa
     * lo mismo.** `scopeBindings()` deduce la relación pluralizando el nombre del
     * parámetro **en inglés** —`medicion` → `medicions`— y aquí el dominio se
     * nombra en español. Sin esto la ruta responde 500 con un «Call to undefined
     * method» que no menciona ni la ruta ni la relación, y de paso deja de acotar:
     * la medición de otro indicador se borraría desde éste.
     *
     * El precedente exacto es `Documento::resolveChildRouteBinding()`, y lo cazó
     * igual que allí un test de aislamiento y no una revisión.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $campo
     * @return Model|null
     */
    public function resolveChildRouteBinding($childType, $value, $campo)
    {
        if ($childType === 'medicion') {
            return $this->mediciones()->where($campo ?? 'mediciones.id', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $campo);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origen' => OrigenMedicion::class,
            'calculo' => CalculoIndicador::class,
            'unidad' => UnidadIndicador::class,
            'sentido' => SentidoIndicador::class,
            'periodicidad' => Periodicidad::class,
            'objetivo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): IndicadorFactory
    {
        return IndicadorFactory::new();
    }
}
