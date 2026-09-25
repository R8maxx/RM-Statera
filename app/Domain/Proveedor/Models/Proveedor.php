<?php

declare(strict_types=1);

namespace App\Domain\Proveedor\Models;

use App\Domain\Activo\Models\Activo;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Enums\ModeloNube;
use App\Domain\Proveedor\Enums\UbicacionDatos;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Proveedor\ProveedorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un proveedor o tercero con el que trabaja la organización (§ 4.9).
 *
 * **No lleva `AcotadoPorAlcance`**, y no es un olvido: no tiene `sistema_id`, y
 * un proveedor sirve a la organización y no a un sistema. El auditor externo lo
 * ve igual que la política o el contexto. `AlcanceDelAuditorTest` sólo exige el
 * trait a lo que tiene la columna.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $cif
 * @property string $servicio_prestado
 * @property EstadoProveedor $estado
 * @property ?Criticidad $criticidad_derivada
 * @property ?Criticidad $criticidad_declarada
 * @property ?string $justificacion_criticidad
 * @property bool $es_nube
 * @property ?ModeloNube $modelo_nube
 * @property UbicacionDatos $ubicacion_datos
 * @property ?string $ubicacion_detalle
 * @property bool $es_subencargado_rgpd
 * @property ?int $responsable_id
 * @property ?string $notas
 * @property ?Carbon $proxima_evaluacion
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'proveedores';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'nombre',
        'cif',
        'servicio_prestado',
        'criticidad_declarada',
        'justificacion_criticidad',
        'es_nube',
        'modelo_nube',
        'ubicacion_datos',
        'ubicacion_detalle',
        'es_subencargado_rgpd',
        'responsable_id',
        'notas',
    ];

    /**
     * La que manda: la declarada si la hay, y si no la derivada.
     *
     * Nunca nula: el `CHECK` exige una de las dos. Que la declarada quede por
     * debajo de la derivada sólo con justificación lo comprueba
     * `CriticidadProveedor` al guardar.
     */
    public function criticidad(): Criticidad
    {
        return $this->criticidad_declarada ?? $this->criticidad_derivada ?? Criticidad::Baja;
    }

    /** Si la declarada rebaja lo que dicen sus activos. */
    public function rebajaLaDerivada(): bool
    {
        return $this->criticidad_declarada !== null
            && $this->criticidad_derivada !== null
            && $this->criticidad_declarada->peso() < $this->criticidad_derivada->peso();
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return HasMany<Activo, $this> */
    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class);
    }

    /** @return HasMany<ProveedorEvaluacion, $this> */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(ProveedorEvaluacion::class);
    }

    /** @return HasOne<ProveedorEvaluacion, $this> */
    public function ultimaEvaluacion(): HasOne
    {
        return $this->hasOne(ProveedorEvaluacion::class)->ofMany(['fecha' => 'max', 'id' => 'max']);
    }

    /** @return HasMany<ProveedorCertificacion, $this> */
    public function certificaciones(): HasMany
    {
        return $this->hasMany(ProveedorCertificacion::class);
    }

    /** @return HasMany<ProveedorTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(ProveedorTransicion::class);
    }

    /** @return BelongsToMany<Tarea, $this> */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'proveedor_tarea')
            ->withPivot(['organizacion_id', 'vinculada_por_id', 'created_at']);
    }

    /**
     * Los que se reevalúan y ya se pasaron. Estrictamente antes de hoy, como
     * `Tarea::vencidas()`: lo que vence hoy todavía no se ha pasado.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeReevaluacionVencida(Builder $consulta): void
    {
        $consulta->where('estado', '<>', EstadoProveedor::Retirado->value)
            ->whereNotNull('proxima_evaluacion')
            ->whereDate('proxima_evaluacion', '<', Carbon::today());
    }

    /** @param  Builder<self>  $consulta */
    public function scopeReevaluacionPorVencer(Builder $consulta, int $dias): void
    {
        $consulta->where('estado', '<>', EstadoProveedor::Retirado->value)
            ->whereNotNull('proxima_evaluacion')
            ->whereDate('proxima_evaluacion', '>=', Carbon::today())
            ->whereDate('proxima_evaluacion', '<=', Carbon::today()->addDays($dias));
    }

    /** @param  Builder<self>  $consulta */
    public function scopeReevaluacionEntre(Builder $consulta, Carbon $desde, Carbon $hasta): void
    {
        $consulta->where('estado', '<>', EstadoProveedor::Retirado->value)
            ->whereNotNull('proxima_evaluacion')
            ->whereDate('proxima_evaluacion', '>=', $desde)
            ->whereDate('proxima_evaluacion', '<=', $hasta);
    }

    /**
     * Los que nunca se han evaluado y no están retirados. Uno de criticidad alta
     * así es lo primero que pregunta un auditor.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeSinEvaluar(Builder $consulta): void
    {
        $consulta->where('estado', '<>', EstadoProveedor::Retirado->value)->whereDoesntHave('evaluaciones');
    }

    /**
     * Los que se siguen usando con algún certificado ya caducado.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeConCertificacionCaducada(Builder $consulta): void
    {
        $consulta->where('estado', '<>', EstadoProveedor::Retirado->value)
            ->whereHas('certificaciones', static function (Builder $certificacion): void {
                /** @var Builder<ProveedorCertificacion> $certificacion */
                $certificacion->caducadas();
            });
    }

    /** @param  Builder<self>  $consulta */
    public function scopeCondicionados(Builder $consulta): void
    {
        $consulta->where('estado', EstadoProveedor::Condicionado->value);
    }

    /**
     * El hijo de una ruta anidada, acotado a este proveedor.
     *
     * `scopeBindings()` deduciría la relación pluralizando en inglés —
     * `certificacion` → `certificacions`— y la ruta respondería 500 y además
     * dejaría de acotar. Es el mismo fallo que `routing.md` cuenta cuatro veces.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  ?string  $campo
     */
    public function resolveChildRouteBinding($childType, $value, $campo): ?Model
    {
        if ($childType === 'certificacion') {
            return $this->certificaciones()->where($campo ?? 'proveedor_certificaciones.id', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $campo);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoProveedor::class,
            'criticidad_derivada' => Criticidad::class,
            'criticidad_declarada' => Criticidad::class,
            'es_nube' => 'boolean',
            'modelo_nube' => ModeloNube::class,
            'ubicacion_datos' => UbicacionDatos::class,
            'es_subencargado_rgpd' => 'boolean',
            'proxima_evaluacion' => 'date',
        ];
    }

    protected static function newFactory(): ProveedorFactory
    {
        return ProveedorFactory::new();
    }
}
