<?php

declare(strict_types=1);

namespace App\Domain\Obligacion\Models;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Obligacion\Enums\ReferenciaCumplimiento;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Models\User;
use Database\Factories\Obligacion\CompromisoCumplimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La prueba de que un compromiso se cumplió una vez.
 *
 * **Las dos fechas no son una.** `fecha` es cuándo se cumplió y `created_at`
 * cuándo se apuntó: la del auditor es la primera y la de la traza es la segunda.
 * Es el mismo reparto que `medidaEn` frente a `registradaPor` en una medición.
 *
 * **`cubre_hasta` se congela al registrar**, con la cadencia vigente entonces.
 * Subir la periodicidad de anual a semestral en marzo no puede repintar como
 * fuera de plazo un cumplimiento de enero que en enero estaba al día — misma
 * familia que `documento_versiones.fecha_proxima_revision`.
 *
 * **Sin `updated_at` y sin `RegistraTraza`.** Esto es histórico y no se edita:
 * corregir un cumplimiento mal apuntado es borrarlo y registrar el bueno. Y no
 * lleva traza propia porque la fila **es** la traza; lo que sí registra es quién
 * la escribió, en `registrado_por_id`.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $compromiso_id
 * @property Carbon $fecha
 * @property Carbon $cubre_hasta
 * @property ?int $auditoria_id
 * @property ?int $revision_direccion_id
 * @property ?int $documento_id
 * @property ?int $prueba_continuidad_id
 * @property ?int $evidencia_id
 * @property ?string $nota
 * @property ?int $registrado_por_id
 * @property Carbon $created_at
 */
class CompromisoCumplimiento extends Model
{
    /** @use HasFactory<CompromisoCumplimientoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;

    public const UPDATED_AT = null;

    protected $table = 'compromiso_cumplimientos';

    protected $fillable = [
        'organizacion_id',
        'compromiso_id',
        'fecha',
        'cubre_hasta',
        'auditoria_id',
        'revision_direccion_id',
        'documento_id',
        'prueba_continuidad_id',
        'evidencia_id',
        'nota',
        'registrado_por_id',
    ];

    /** @return BelongsTo<Compromiso, $this> */
    public function compromiso(): BelongsTo
    {
        return $this->belongsTo(Compromiso::class);
    }

    /** @return BelongsTo<Auditoria, $this> */
    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class);
    }

    /** @return BelongsTo<RevisionDireccion, $this> */
    public function revisionDireccion(): BelongsTo
    {
        return $this->belongsTo(RevisionDireccion::class, 'revision_direccion_id');
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<PruebaContinuidad, $this> */
    public function pruebaContinuidad(): BelongsTo
    {
        return $this->belongsTo(PruebaContinuidad::class);
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    /**
     * A qué registro apunta, si apunta a alguno.
     *
     * Devuelve el enum y el id, que es todo lo que hace falta para pintar el
     * enlace: `ReferenciaCumplimiento` sabe la etiqueta, el icono y la URL. Así
     * la ficha no importa los tres módulos para enseñar una línea.
     *
     * @return array{referencia: ReferenciaCumplimiento, id: int}|null
     */
    public function referencia(): ?array
    {
        foreach (ReferenciaCumplimiento::cases() as $referencia) {
            $id = $this->{$referencia->columna()};

            if ($id !== null) {
                return ['referencia' => $referencia, 'id' => (int) $id];
            }
        }

        return null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cubre_hasta' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CompromisoCumplimientoFactory
    {
        return CompromisoCumplimientoFactory::new();
    }
}
