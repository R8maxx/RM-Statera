<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Models;

use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Enums\TipoPrueba;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Continuidad\PruebaContinuidadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Una comprobación de un plan de continuidad: § 4.11 y `op.cont.3`.
 *
 * **La evidencia de que el plan no es sólo papel.** Un plan de continuidad sin
 * pruebas encima es una promesa sin contrastar; de ahí que `documento_id`
 * lleve `restrictOnDelete` en la migración —no se borra el plan mientras
 * queden pruebas que lo demuestran— y que `Documento` no pueda borrarse en
 * cascada sobre esta tabla.
 *
 * **`servicios()` es una pivote propia y no `Documento::serviciosCubiertos()`
 * reutilizada.** Una prueba concreta puede cubrir sólo una parte de los
 * servicios que el plan declara, y lleva encima el RTO y el RPO
 * **alcanzados** —cifras de esta prueba, no del plan ni del BIA—.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property int $documento_id
 * @property TipoPrueba $tipo
 * @property EstadoPrueba $estado
 * @property Carbon $fecha_prevista
 * @property ?Carbon $fecha_realizacion
 * @property ?ResultadoPrueba $resultado
 * @property ?string $conclusiones
 * @property ?string $motivo_cancelacion
 * @property ?int $evidencia_id
 * @property ?int $responsable_id
 */
class PruebaContinuidad extends Model
{
    /** @use HasFactory<PruebaContinuidadFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'pruebas_continuidad';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'documento_id',
        'tipo',
        'estado',
        'fecha_prevista',
        'fecha_realizacion',
        'resultado',
        'conclusiones',
        'motivo_cancelacion',
        'evidencia_id',
        'responsable_id',
    ];

    /**
     * El plan de continuidad que esta prueba comprueba.
     *
     * @return BelongsTo<Documento, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'documento_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Los servicios que cubrió esta prueba, con lo que de verdad se alcanzó.
     *
     * @return BelongsToMany<Activo, $this>
     */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Activo::class, 'prueba_continuidad_servicio')
            ->withPivot(['rto_alcanzado_horas', 'rpo_alcanzado_horas'])
            ->withTimestamps();
    }

    /** @return HasMany<PruebaContinuidadTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(PruebaContinuidadTransicion::class)->orderBy('created_at');
    }

    /**
     * El trabajo correctivo que dejó esta prueba: § 4.11, costuras.
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'prueba_continuidad_tarea')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /**
     * La no conformidad que trata lo que esta prueba destapó, si la hay.
     *
     * **`HasOne` y no `HasMany`**, por el índice único sobre
     * `no_conformidades.prueba_continuidad_id`: una prueba se trata una vez.
     * Espejo exacto de `Incidente::noConformidad()`.
     *
     * @return HasOne<NoConformidad, $this>
     */
    public function noConformidad(): HasOne
    {
        return $this->hasOne(NoConformidad::class);
    }

    /**
     * Si el RTO alcanzado en esta prueba se pasó del objetivo que el BIA del
     * servicio prometía. `true` = se pasó, `false` = se cumplió, `null` = falta
     * uno de los dos datos para poder decirlo.
     *
     * **Eager-load a mano cuando se recorre una lista.** Este método consulta
     * `servicios` si no está ya cargada —una consulta por prueba— y, si no se
     * le pasa `$bia`, consulta además `BiaServicio` por `activo_id` —una
     * consulta por servicio—. Quien pinte una tabla de pruebas tiene que
     * cargar `servicios` con `with('servicios')` y resolver el BIA de cada
     * servicio una sola vez —por ejemplo con
     * `BiaServicio::query()->whereIn('activo_id', $ids)->get()->keyBy('activo_id')`—
     * y pasarlo aquí, en vez de dejar que cada llamada dispare su propia
     * consulta.
     */
    public function excedeRto(Activo $servicio, ?BiaServicio $bia = null): ?bool
    {
        $cubierto = $this->servicios->firstWhere('id', $servicio->id);
        $alcanzado = $cubierto?->getAttribute('pivot')?->getAttribute('rto_alcanzado_horas');

        if ($alcanzado === null) {
            return null;
        }

        $bia ??= BiaServicio::query()->where('activo_id', $servicio->id)->first();

        if ($bia === null) {
            return null;
        }

        return (int) $alcanzado > $bia->rto_horas;
    }

    /**
     * Las planificadas cuya fecha ya pasó.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVencidas(Builder $query): void
    {
        $query->where('pruebas_continuidad.estado', EstadoPrueba::Planificada->value)
            ->whereDate('pruebas_continuidad.fecha_prevista', '<', Carbon::today());
    }

    /**
     * Las planificadas que vencen dentro de los próximos `$dias`, sin contar
     * las ya vencidas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePorVencer(Builder $query, int $dias = 30): void
    {
        $query->where('pruebas_continuidad.estado', EstadoPrueba::Planificada->value)
            ->whereDate('pruebas_continuidad.fecha_prevista', '>=', Carbon::today())
            ->whereDate('pruebas_continuidad.fecha_prevista', '<=', Carbon::today()->addDays($dias));
    }

    /**
     * Las previstas entre dos fechas, ambas incluidas, sin filtrar por estado.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePrevistaEntre(Builder $query, Carbon $desde, Carbon $hasta): void
    {
        $query->whereDate('pruebas_continuidad.fecha_prevista', '>=', $desde->toDateString())
            ->whereDate('pruebas_continuidad.fecha_prevista', '<=', $hasta->toDateString());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPrueba::class,
            'estado' => EstadoPrueba::class,
            'resultado' => ResultadoPrueba::class,
            'fecha_prevista' => 'date',
            'fecha_realizacion' => 'date',
        ];
    }

    protected static function newFactory(): PruebaContinuidadFactory
    {
        return PruebaContinuidadFactory::new();
    }
}
