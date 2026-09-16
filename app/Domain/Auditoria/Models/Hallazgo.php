<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Models;

use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Auditoria\HallazgoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Los que la cláusula 10.2 obliga a tratar.
     *
     * La relación con `no_conformidades` —y el scope de «sin tratar» que sale de
     * ella— llega con el § 4.13, que es la otra mitad de este módulo: un hallazgo
     * sin no conformidad detrás no cierra nada. Hasta entonces esto es sólo la
     * frontera entre lo que obliga y lo que avisa.
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
