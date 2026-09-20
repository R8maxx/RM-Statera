<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Persona\AccionFormativaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una sesión de formación o de concienciación: `mp.per.4` y `mp.per.3`.
 *
 * **La hoja de firmas apunta a `evidencias`** con una foránea simple y no con una
 * pivote: el escaneo es exactamente lo que ese repositorio ya sabe guardar —con su
 * hash, su caducidad y su disco con Object Lock— y montar un segundo almacén para
 * esto sería duplicar la parte cara del § 4.6.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property TipoAccionFormativa $tipo
 * @property Carbon $fecha
 * @property ?string $duracion_horas
 * @property ?string $contenido
 * @property ?int $evidencia_id
 */
class AccionFormativa extends Model
{
    /** @use HasFactory<AccionFormativaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'acciones_formativas';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'tipo',
        'fecha',
        'duracion_horas',
        'contenido',
        'evidencia_id',
    ];

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    /** @return HasMany<Asistencia, $this> */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Quiénes fueron convocados, hayan asistido o no.
     *
     * @return BelongsToMany<Persona, $this>
     */
    public function convocadas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'asistencias')
            ->withPivot(['asistio', 'registrada_en']);
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinAsistencia(Builder $query): void
    {
        $query->whereDoesntHave('asistencias', static function (Builder $asistencias): void {
            /** @var Builder<Asistencia> $asistencias */
            $asistencias->where('asistio', true);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAccionFormativa::class,
            'fecha' => 'date',
            'duracion_horas' => 'decimal:2',
        ];
    }

    protected static function newFactory(): AccionFormativaFactory
    {
        return AccionFormativaFactory::new();
    }
}
