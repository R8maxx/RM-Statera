<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Models;

use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use Database\Factories\Auditoria\AuditoriaPuntoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una línea de la checklist: qué dijo el auditor de una medida concreta.
 *
 * **Apunta a la implantación y no al requisito.** Es la diferencia entre «el ENS
 * pide cifrado» y «lo tenemos puesto en este sistema», y lo que se audita es lo
 * segundo. Es el mismo criterio por el que las salvaguardas de un riesgo apuntan
 * a `implantaciones`.
 *
 * **Y por eso los dos campos congelados.** Una implantación cambia: el motor
 * puede dejar de exigirla tras una revaloración y su estado se mueve cada semana.
 * Si la auditoría de marzo leyera el registro de octubre, su denominador
 * cambiaría bajo los pies y la fila **mentiría** — que es exactamente el motivo
 * por el que `riesgo_valoraciones` congela su escala y sus salvaguardas. Al
 * cerrar, `CerrarAuditoria` escribe aquí lo que la implantación decía ese día.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $auditoria_id
 * @property int $implantacion_id
 * @property ResultadoPunto $resultado
 * @property ?string $nota
 * @property ?string $exigencia_congelada
 * @property ?EstadoImplantacion $estado_congelado
 */
class AuditoriaPunto extends Model
{
    /** @use HasFactory<AuditoriaPuntoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;

    protected $table = 'auditoria_puntos';

    protected $fillable = [
        'organizacion_id',
        'auditoria_id',
        'implantacion_id',
        'resultado',
        'nota',
        'exigencia_congelada',
        'estado_congelado',
    ];

    /** @return BelongsTo<Auditoria, $this> */
    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class);
    }

    /** @return BelongsTo<Implantacion, $this> */
    public function implantacion(): BelongsTo
    {
        return $this->belongsTo(Implantacion::class);
    }

    /** @return HasMany<Hallazgo, $this> */
    public function hallazgos(): HasMany
    {
        return $this->hasMany(Hallazgo::class);
    }

    /** @param  Builder<$this>  $query */
    public function scopeRevisados(Builder $query): void
    {
        $query->where('auditoria_puntos.resultado', '!=', ResultadoPunto::Pendiente->value);
    }

    /**
     * Los que piden un hallazgo detrás que los explique y no lo tienen.
     *
     * Un «no conforme» sin hallazgo es una casilla marcada sin decir qué pasa, y
     * es lo que hace que una auditoría no se pueda cerrar bien.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinHallazgo(Builder $query): void
    {
        $query
            ->whereIn('auditoria_puntos.resultado', [
                ResultadoPunto::NoConforme->value,
                ResultadoPunto::Observacion->value,
            ])
            ->whereDoesntHave('hallazgos');
    }

    /**
     * El estado de la implantación tal como cuenta para esta auditoría.
     *
     * Congelado si está cerrada, y el de hoy si sigue abierta: mientras se
     * audita, lo que vale es lo que diga el registro.
     */
    public function estadoAuditado(): ?EstadoImplantacion
    {
        return $this->estado_congelado ?? $this->implantacion?->estado;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoPunto::class,
            'estado_congelado' => EstadoImplantacion::class,
        ];
    }

    protected static function newFactory(): AuditoriaPuntoFactory
    {
        return AuditoriaPuntoFactory::new();
    }
}
