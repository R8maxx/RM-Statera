<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Persona\PuestoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un puesto de la organización: § 4.8 y la caracterización de `mp.per.1`.
 *
 * **No es un rol ENS ni un rol de Statera.** `RolEns` son los cinco cargos que el
 * Anexo II designa por sistema, y `Domain\Autorizacion\Enums\Rol` decide quién
 * puede tocar qué dentro de la herramienta. Esto es el puesto de trabajo: lo que
 * pone en el contrato.
 *
 * La jerarquía vive aquí —`reporta_a_id`— y no en la persona, así que el
 * organigrama no se mueve porque alguien entre o se vaya.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property ?int $reporta_a_id
 * @property ?string $mision
 * @property ?string $funciones
 * @property ?string $competencias
 */
class Puesto extends Model
{
    /** @use HasFactory<PuestoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'reporta_a_id',
        'mision',
        'funciones',
        'competencias',
    ];

    /** @return BelongsTo<Puesto, $this> */
    public function reportaA(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporta_a_id');
    }

    /** @return HasMany<Puesto, $this> */
    public function dependientes(): HasMany
    {
        return $this->hasMany(self::class, 'reporta_a_id')->orderBy('titulo');
    }

    /**
     * Todas las asignaciones, vigentes y cerradas.
     *
     * **Sin joins ni orden**, como `Auditoria::puntos()`: el *route model binding*
     * acotado resuelve el hijo con un `where` sin cualificar, y con otra tabla
     * unida la consulta muere con «column reference "id" is ambiguous», un error
     * que no menciona ni la ruta ni la relación.
     *
     * @return HasMany<AsignacionPuesto, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionPuesto::class);
    }

    /** Está caracterizado si dice qué competencia pide: es lo que mira `mp.per.1`. */
    public function estaCaracterizado(): bool
    {
        return filled($this->competencias);
    }

    /**
     * Los que no dicen qué competencia piden.
     *
     * Es la cifra del módulo, y **no va en rojo**: un puesto sin caracterizar es
     * la distancia que queda, no un incumplimiento — en categoría básica
     * `mp.per.1` está en `no_aplica`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinCaracterizar(Builder $query): void
    {
        $query->where(function (Builder $consulta): void {
            $consulta->whereNull('puestos.competencias')
                ->orWhereRaw('length(btrim(puestos.competencias)) = 0');
        });
    }

    /**
     * Los que no ocupa nadie hoy.
     *
     * Una vacante no es un error —se crea el puesto antes de cubrirlo—, pero es
     * lo que contesta «¿a quién le falta jefe?» cuando alguien se va.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVacantes(Builder $query): void
    {
        $query->whereDoesntHave('asignaciones', function (Builder $consulta): void {
            $consulta->whereNull('hasta');
        });
    }

    protected static function newFactory(): PuestoFactory
    {
        return PuestoFactory::new();
    }
}
