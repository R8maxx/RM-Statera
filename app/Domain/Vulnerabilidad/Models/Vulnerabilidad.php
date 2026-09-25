<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad\Models;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Concerns\AcotadoPorAlcance;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\OrigenVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Models\User;
use Database\Factories\Vulnerabilidad\VulnerabilidadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una vulnerabilidad técnica: invariante 8, A.8.8 y `op.exp.4`.
 *
 * **Lleva `AcotadoPorAlcance`** aunque no tenga `sistema_id`: es de los activos
 * donde está, y por ellos de sus sistemas, igual que un riesgo. El auditor
 * externo ve las de lo que audita. Una sin activos no se ve desde un alcance,
 * porque no se sabe dónde está.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property ?string $descripcion
 * @property ?string $cve
 * @property ?string $cvss_puntuacion
 * @property ?string $cvss_vector
 * @property Severidad $severidad
 * @property OrigenVulnerabilidad $origen
 * @property Carbon $fecha_deteccion
 * @property ?Carbon $fecha_limite
 * @property EstadoVulnerabilidad $estado
 * @property ?int $responsable_id
 * @property ?int $proveedor_id
 * @property ?int $riesgo_id
 * @property ?int $incidente_id
 * @property ?string $remediacion
 * @property ?string $motivo_aceptacion
 * @property ?int $aceptada_por_id
 * @property ?Carbon $aceptada_en
 * @property ?string $verificacion
 * @property ?int $verificada_por_id
 * @property ?Carbon $cerrada_en
 */
class Vulnerabilidad extends Model
{
    use AcotadoPorAlcance;

    /** @use HasFactory<VulnerabilidadFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'vulnerabilidades';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'descripcion',
        'cve',
        'cvss_puntuacion',
        'cvss_vector',
        'severidad',
        'origen',
        'fecha_deteccion',
        'responsable_id',
        'proveedor_id',
        'riesgo_id',
        'incidente_id',
        'remediacion',
    ];

    /**
     * Es de los activos donde está. El `whereHas` no repite la lista: la
     * consulta de `activos()` ya pasa por el mismo scope en `Activo`.
     *
     * @param  Builder<static>  $consulta
     * @param  list<int>  $sistemas
     */
    public function acotarAlAlcance(Builder $consulta, array $sistemas): void
    {
        $consulta->whereHas('activos');
    }

    /** Si se pasó el plazo sin que nadie aplicara el arreglo. */
    public function fueraDePlazo(): bool
    {
        return $this->estado->correPlazo()
            && $this->fecha_limite !== null
            && $this->fecha_limite->lt(Carbon::today());
    }

    /** @return BelongsToMany<Activo, $this> */
    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Activo::class, 'vulnerabilidad_activo')->withPivot('organizacion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<Proveedor, $this> */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** @return BelongsTo<Riesgo, $this> */
    public function riesgo(): BelongsTo
    {
        return $this->belongsTo(Riesgo::class);
    }

    /** @return BelongsTo<Incidente, $this> */
    public function incidente(): BelongsTo
    {
        return $this->belongsTo(Incidente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function aceptadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aceptada_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verificadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificada_por_id');
    }

    /** @return HasMany<VulnerabilidadTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(VulnerabilidadTransicion::class);
    }

    /** @return BelongsToMany<Tarea, $this> */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'vulnerabilidad_tarea')
            ->withPivot(['organizacion_id', 'vinculada_por_id', 'created_at']);
    }

    /**
     * Las que siguen sin arreglo y ya pasaron su plazo. Estrictamente antes de
     * hoy, como `Tarea::vencidas()`.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeFueraDePlazo(Builder $consulta): void
    {
        $consulta->whereIn('estado', [EstadoVulnerabilidad::Abierta->value, EstadoVulnerabilidad::EnRemediacion->value])
            ->whereNotNull('fecha_limite')
            ->whereDate('fecha_limite', '<', Carbon::today());
    }

    /** @param  Builder<self>  $consulta */
    public function scopeVivas(Builder $consulta): void
    {
        $consulta->whereIn('estado', [
            EstadoVulnerabilidad::Abierta->value,
            EstadoVulnerabilidad::EnRemediacion->value,
            EstadoVulnerabilidad::Mitigada->value,
        ]);
    }

    /**
     * Arregladas y sin verificar: el paso que más se olvida.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeSinVerificar(Builder $consulta): void
    {
        $consulta->where('estado', EstadoVulnerabilidad::Mitigada->value);
    }

    /** @param  Builder<self>  $consulta */
    public function scopeAceptadas(Builder $consulta): void
    {
        $consulta->where('estado', EstadoVulnerabilidad::Aceptada->value);
    }

    /** @param  Builder<self>  $consulta */
    public function scopeCriticasAbiertas(Builder $consulta): void
    {
        $consulta->where('severidad', Severidad::Critica->value)
            ->whereIn('estado', [EstadoVulnerabilidad::Abierta->value, EstadoVulnerabilidad::EnRemediacion->value]);
    }

    /** @param  Builder<self>  $consulta */
    public function scopePlazoEntre(Builder $consulta, Carbon $desde, Carbon $hasta): void
    {
        $consulta->whereIn('estado', [EstadoVulnerabilidad::Abierta->value, EstadoVulnerabilidad::EnRemediacion->value])
            ->whereNotNull('fecha_limite')
            ->whereDate('fecha_limite', '>=', $desde)
            ->whereDate('fecha_limite', '<=', $hasta);
    }

    /** @param  Builder<self>  $consulta */
    public function scopePlazoPorVencer(Builder $consulta, int $dias): void
    {
        $this->scopePlazoEntre($consulta, Carbon::today(), Carbon::today()->addDays($dias));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severidad' => Severidad::class,
            'origen' => OrigenVulnerabilidad::class,
            'estado' => EstadoVulnerabilidad::class,
            'fecha_deteccion' => 'date',
            'fecha_limite' => 'date',
            'aceptada_en' => 'datetime',
            'cerrada_en' => 'datetime',
        ];
    }

    protected static function newFactory(): VulnerabilidadFactory
    {
        return VulnerabilidadFactory::new();
    }
}
