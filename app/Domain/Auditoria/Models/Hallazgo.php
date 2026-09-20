<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Models;

use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Auditoria\HallazgoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Lo que el auditor encontró.
 *
 * **Cuelga del punto de la checklist, no de la pareja (auditoría, requisito).**
 * Con la pareja, nada impediría un punto marcado «conforme» con una no
 * conformidad mayor encima del mismo requisito: el mismo hecho registrado dos
 * veces y contradiciéndose. Colgándolo del punto, esa contradicción deja de ser
 * expresable.
 *
 * **Y el punto es opcional**, contra la letra de § 2.2. Una auditoría ISO produce
 * hallazgos que no cuelgan de ninguna medida —«el programa de auditoría interna
 * no está definido», «la dirección no ha revisado el SGSI»—, y con la columna
 * obligatoria acabarían colgados de un requisito arbitrario, que es el vicio que
 * `OrigenTarea::Propia` existe para evitar.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $auditoria_id
 * @property ?int $auditoria_punto_id
 * @property TipoHallazgo $tipo
 * @property string $descripcion
 */
class Hallazgo extends Model
{
    /** @use HasFactory<HallazgoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'hallazgos';

    protected $fillable = [
        'organizacion_id',
        'auditoria_id',
        'auditoria_punto_id',
        'tipo',
        'descripcion',
    ];

    /** @return BelongsTo<Auditoria, $this> */
    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class);
    }

    /** @return BelongsTo<AuditoriaPunto, $this> */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(AuditoriaPunto::class, 'auditoria_punto_id');
    }

    /**
     * El tratamiento, cuando lo tiene.
     *
     * `hasOne` y no `hasMany` porque la base lo impone con un índice único sobre
     * `no_conformidades.hallazgo_id`: un hallazgo se trata una vez. Con dos filas,
     * «sin tratar» dependería de cuál se mirara y el registro enseñaría el mismo
     * hecho dos veces.
     *
     * @return HasOne<NoConformidad, $this>
     */
    public function noConformidad(): HasOne
    {
        return $this->hasOne(NoConformidad::class);
    }

    /**
     * El otro tratamiento posible, desde la cláusula 10.1.
     *
     * `hasOne` por lo mismo que arriba: `mejoras.hallazgo_id` lleva índice único.
     * Los dos son excluyentes por el tipo del hallazgo —una oportunidad de mejora
     * no admite no conformidad y al revés tampoco—, así que en la práctica sólo
     * uno de los dos puede tener fila.
     *
     * @return HasOne<Mejora, $this>
     */
    public function mejora(): HasOne
    {
        return $this->hasOne(Mejora::class);
    }

    /**
     * Los que la cláusula 10.2 obliga a tratar.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeNoConformidades(Builder $query): void
    {
        $query->whereIn('hallazgos.tipo', [
            TipoHallazgo::NcMayor->value,
            TipoHallazgo::NcMenor->value,
        ]);
    }

    /**
     * Los que obligan a abrir una no conformidad y no la tienen.
     *
     * Es la costura entre las dos mitades del módulo, y la que hay que poder
     * enseñar: una no conformidad mayor sin tratamiento detrás es un hallazgo de
     * la auditoría siguiente. **No incluye la anulada**: anular es decidir que
     * aquello no era una no conformidad, y eso sí es tratarlo — con su motivo
     * escrito, que es lo que el auditor va a leer.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinTratar(Builder $query): void
    {
        $query->noConformidades()->whereDoesntHave('noConformidad');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tipo' => TipoHallazgo::class];
    }

    protected static function newFactory(): HallazgoFactory
    {
        return HallazgoFactory::new();
    }
}
