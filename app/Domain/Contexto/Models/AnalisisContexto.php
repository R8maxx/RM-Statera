<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Models;

use App\Domain\Contexto\Enums\EstadoAnalisis;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Contexto\AnalisisContextoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una revisión entera del contexto de la organización: § 4.1, cláusulas 4.1 a 4.3.
 *
 * **Es la unidad versionada del módulo, y las cuestiones y las partes no lo son.**
 * Copiarlas enteras en cada revisión —el patrón literal de `riesgo_valoraciones`—
 * rompería los vínculos: un riesgo apuntaría a la cuestión de marzo y en octubre
 * apuntaría a una fila muerta. Así que ellas viven, con su alta y su baja referidas
 * a un análisis, y lo que se congela aquí es la **instantánea**.
 *
 * **La instantánea no es redundante con las tablas.** Sin ella, editar una cuestión
 * en 2027 cambiaría lo que dijo el análisis de 2026, y «¿qué ha cambiado en el
 * contexto?» —entrada obligatoria de la cláusula 9.3— no se contesta. Es el mismo
 * razonamiento que hay escrito para `riesgo_valoraciones.salvaguardas` y para
 * `documento_versiones.instantanea`, y de paso es lo que le da histórico al alcance
 * declarado de cada sistema sin tocar `sistemas`.
 *
 * **`vigente` no es columna**: es `estado = 'aprobado'`, y que haya uno como mucho
 * lo garantiza un índice único parcial, igual que el borrador de un documento.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property ?int $numero
 * @property Carbon $fecha_analisis
 * @property EstadoAnalisis $estado
 * @property ?bool $clima_pertinente
 * @property ?string $clima_justificacion
 * @property ?string $nota
 * @property ?array<string, mixed> $instantanea
 * @property ?int $creado_por_id
 * @property ?int $aprobado_por_id
 * @property ?Carbon $aprobado_en
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class AnalisisContexto extends Model
{
    /** @use HasFactory<AnalisisContextoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'analisis_contexto';

    protected $fillable = [
        'organizacion_id',
        'numero',
        'fecha_analisis',
        'estado',
        'clima_pertinente',
        'clima_justificacion',
        'nota',
        'instantanea',
        'creado_por_id',
        'aprobado_por_id',
        'aprobado_en',
    ];

    /** @return BelongsTo<User, $this> */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /**
     * Las cuestiones que este análisis dio de alta.
     *
     * **No son «las cuestiones del análisis»**, que es otra cosa y vive en la
     * instantánea: aquí están las que nacieron aquí, y las que siguen vigentes de
     * análisis anteriores no aparecen. La relación sirve para la comparativa —qué
     * entró y qué salió—, no para pintar el DAFO.
     *
     * @return HasMany<CuestionContexto, $this>
     */
    public function cuestionesDadasDeAlta(): HasMany
    {
        return $this->hasMany(CuestionContexto::class, 'analisis_alta_id');
    }

    /** @return HasMany<CuestionContexto, $this> */
    public function cuestionesRetiradas(): HasMany
    {
        return $this->hasMany(CuestionContexto::class, 'analisis_baja_id');
    }

    /** @return HasMany<ParteInteresada, $this> */
    public function partesDadasDeAlta(): HasMany
    {
        return $this->hasMany(ParteInteresada::class, 'analisis_alta_id');
    }

    /** @return HasMany<ParteInteresada, $this> */
    public function partesRetiradas(): HasMany
    {
        return $this->hasMany(ParteInteresada::class, 'analisis_baja_id');
    }

    /**
     * El que está aprobado y aún no ha sido sustituido. Hay uno como mucho.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVigente(Builder $query): void
    {
        $query->where('analisis_contexto.estado', EstadoAnalisis::Aprobado);
    }

    /**
     * El que se está preparando. Hay uno como mucho.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBorrador(Builder $query): void
    {
        $query->whereNull('analisis_contexto.numero');
    }

    public function esBorrador(): bool
    {
        return $this->numero === null;
    }

    /**
     * Cómo se nombra en una lista y en el historial.
     *
     * Sin número no hay nada declarado, así que se llama por lo que es. Es el mismo
     * reparto que `DocumentoVersion::etiqueta()`.
     */
    public function etiqueta(): string
    {
        return $this->numero === null
            ? 'Borrador'
            : 'Análisis n.º '.$this->numero;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoAnalisis::class,
            'fecha_analisis' => 'date',
            'clima_pertinente' => 'boolean',
            'instantanea' => 'array',
            'aprobado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): AnalisisContextoFactory
    {
        return AnalisisContextoFactory::new();
    }
}
