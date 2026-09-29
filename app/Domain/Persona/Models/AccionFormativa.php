<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Adjunto\Concerns\ConAdjuntos;
use App\Domain\Adjunto\Concerns\TieneAdjuntos;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Persona\Enums\ImparticionFormacion;
use App\Domain\Persona\Enums\ModalidadFormacion;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Proveedor\Models\Proveedor;
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
 * @property ?ModalidadFormacion $modalidad
 * @property ?ImparticionFormacion $imparte
 * @property ?int $ponente_persona_id
 * @property ?int $proveedor_id
 * @property ?string $ponente_nombre
 */
class AccionFormativa extends Model implements ConAdjuntos
{
    /** @use HasFactory<AccionFormativaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;
    use TieneAdjuntos;

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
        'modalidad',
        'imparte',
        'ponente_persona_id',
        'proveedor_id',
        'ponente_nombre',
    ];

    /** La hoja de firmas escaneada, el material, el certificado del proveedor. */
    public function tablaDeAdjuntos(): string
    {
        return 'accion_formativa_adjunto';
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidencia(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class);
    }

    /**
     * Quien la impartió, si fue alguien de la plantilla.
     *
     * @return BelongsTo<Persona, $this>
     */
    public function ponente(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'ponente_persona_id');
    }

    /**
     * La empresa que la impartió, si fue externa y está dada de alta.
     *
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
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
            ->withPivot(['asistio', 'ausencia', 'registrada_en']);
    }

    /**
     * Hasta cuándo cuenta como formación reciente para quien asistió.
     *
     * La misma cadencia que `Persona::MESES_DE_VIGENCIA_FORMATIVA` y no otra: es
     * la fecha que la ficha enseña y la que propone para la sesión siguiente.
     */
    public function vigenteHasta(): Carbon
    {
        return $this->fecha->copy()->addMonthsNoOverflow(Persona::MESES_DE_VIGENCIA_FORMATIVA);
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
            'modalidad' => ModalidadFormacion::class,
            'imparte' => ImparticionFormacion::class,
            'fecha' => 'date',
            'duracion_horas' => 'decimal:2',
        ];
    }

    protected static function newFactory(): AccionFormativaFactory
    {
        return AccionFormativaFactory::new();
    }
}
