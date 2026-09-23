<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Models;

use App\Domain\Activo\Models\Activo;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\UmbralTolerable;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Continuidad\BiaServicioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El análisis de impacto en el negocio de un servicio: § 4.11.
 *
 * **Un servicio, un BIA.** No hay histórico de BIA sucesivos como en
 * `AnalisisContexto`: `EditarBia` reescribe la fila vigente y la devuelve a
 * borrador, y lo que queda del pasado vive en `transiciones`, no en filas
 * nuevas de `bia_servicios`.
 *
 * El umbral tolerable (MTPD) **no es una columna**: lo deriva
 * `UmbralTolerable::de()` de los cinco tramos, y `rtoIncoherente()` es la
 * comprobación que lo enfrenta al RTO declarado.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $activo_id
 * @property NivelImpacto $impacto_4h
 * @property NivelImpacto $impacto_1d
 * @property NivelImpacto $impacto_3d
 * @property NivelImpacto $impacto_1s
 * @property NivelImpacto $impacto_1m
 * @property int $rto_horas
 * @property int $rpo_horas
 * @property ?string $justificacion
 * @property EstadoBia $estado
 * @property ?int $responsable_id
 * @property ?int $aprobado_por_id
 * @property ?Carbon $fecha_aprobacion
 * @property ?Carbon $fecha_revision
 */
class BiaServicio extends Model
{
    /** @use HasFactory<BiaServicioFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'bia_servicios';

    protected $fillable = [
        'organizacion_id',
        'activo_id',
        'impacto_4h',
        'impacto_1d',
        'impacto_3d',
        'impacto_1s',
        'impacto_1m',
        'rto_horas',
        'rpo_horas',
        'justificacion',
        'estado',
        'responsable_id',
        'aprobado_por_id',
        'fecha_aprobacion',
        'fecha_revision',
    ];

    /** @return BelongsTo<Activo, $this> */
    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<User, $this> */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /** @return HasMany<BiaServicioTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(BiaServicioTransicion::class)->orderBy('created_at');
    }

    /**
     * Si el RTO declarado promete más de lo que el propio BIA tolera.
     *
     * Equivale exactamente a `scopeRtoIncoherente()`, escrito en SQL con un
     * `CASE` sobre los mismos tramos: la misma regla en los dos sitios, y cada
     * una con su propio test, porque divergir aquí es justamente el fallo que
     * el módulo no se puede permitir (prioridad 1 de cobertura de tests).
     */
    public function rtoIncoherente(): bool
    {
        $horas = UmbralTolerable::horas($this);

        return $horas !== null && $this->rto_horas > $horas;
    }

    /**
     * Los que ya deberían haberse revisado.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRevisionVencida(Builder $query): void
    {
        $query->whereNotNull('bia_servicios.fecha_revision')
            ->whereDate('bia_servicios.fecha_revision', '<', Carbon::today());
    }

    /**
     * Los que se revisaron entre dos fechas, ambas incluidas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRevisionEntre(Builder $query, Carbon $desde, Carbon $hasta): void
    {
        $query->whereNotNull('bia_servicios.fecha_revision')
            ->whereDate('bia_servicios.fecha_revision', '>=', $desde->toDateString())
            ->whereDate('bia_servicios.fecha_revision', '<=', $hasta->toDateString());
    }

    /**
     * Los que vencen dentro de los próximos `$dias`, sin contar los ya vencidos.
     *
     * Misma forma que `Tarea::scopePorVencer()`, por el mismo motivo: el aviso
     * diario junta vencidos y por vencer, y una ventana que se contara distinto
     * daría dos listas que no se pueden leer seguidas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRevisionPorVencer(Builder $query, int $dias = 30): void
    {
        $query->whereNotNull('bia_servicios.fecha_revision')
            ->whereDate('bia_servicios.fecha_revision', '>=', Carbon::today())
            ->whereDate('bia_servicios.fecha_revision', '<=', Carbon::today()->addDays($dias));
    }

    /**
     * El mismo `rtoIncoherente()` del modelo, en SQL.
     *
     * Un `CASE` sin `ELSE` sobre los cinco tramos: si ninguno llega a
     * `muy_alto` el `CASE` vale `NULL`, `rto_horas > NULL` es `NULL` y la fila
     * queda fuera del `WHERE` — que es exactamente «sin umbral, no hay nada que
     * comparar» de `UmbralTolerable::horas()`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRtoIncoherente(Builder $query): void
    {
        $query->whereRaw(<<<'SQL'
            bia_servicios.rto_horas > CASE
                WHEN bia_servicios.impacto_4h = 'muy_alto' THEN 4
                WHEN bia_servicios.impacto_1d = 'muy_alto' THEN 24
                WHEN bia_servicios.impacto_3d = 'muy_alto' THEN 72
                WHEN bia_servicios.impacto_1s = 'muy_alto' THEN 168
                WHEN bia_servicios.impacto_1m = 'muy_alto' THEN 720
            END
        SQL);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'impacto_4h' => NivelImpacto::class,
            'impacto_1d' => NivelImpacto::class,
            'impacto_3d' => NivelImpacto::class,
            'impacto_1s' => NivelImpacto::class,
            'impacto_1m' => NivelImpacto::class,
            'estado' => EstadoBia::class,
            'fecha_aprobacion' => 'date',
            'fecha_revision' => 'date',
        ];
    }

    protected static function newFactory(): BiaServicioFactory
    {
        return BiaServicioFactory::new();
    }
}
