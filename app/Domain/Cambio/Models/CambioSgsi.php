<?php

declare(strict_types=1);

namespace App\Domain\Cambio\Models;

use App\Domain\Cambio\Enums\AmbitoCambio;
use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Enums\OrigenCambio;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Cambio\CambioSgsiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un cambio del SGSI: la cláusula 6.3 de ISO 27001.
 *
 * Lo que la norma pide es que el cambio **se haga de forma planificada**, y lo
 * que el auditor pregunta de un cambio planificado son cuatro cosas: para qué
 * (`proposito`), qué arrastra (`consecuencias`), cómo sigue el sistema en pie
 * mientras tanto (`integridad`) y con qué (`recursos`). Más quién lo decidió,
 * que es la firma.
 *
 * **Sin `AcotadoPorAlcance`**: un cambio del sistema de gestión no cuelga de un
 * sistema de información, y el auditor externo que ve un sistema ve también cómo
 * se gobierna el SGSI que lo contiene.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property ?string $descripcion
 * @property AmbitoCambio $ambito
 * @property OrigenCambio $origen
 * @property ?string $proposito
 * @property ?string $consecuencias
 * @property ?string $integridad
 * @property ?string $recursos
 * @property EstadoCambio $estado
 * @property ?int $responsable_id
 * @property Carbon $fecha_propuesta
 * @property ?Carbon $fecha_prevista
 * @property ?Carbon $fecha_implantacion
 * @property ?Carbon $fecha_cierre
 * @property ?int $aprobado_por_id
 * @property ?Carbon $aprobado_en
 * @property ?string $revision
 */
class CambioSgsi extends Model
{
    /** @use HasFactory<CambioSgsiFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'cambios_sgsi';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'descripcion',
        'ambito',
        'origen',
        'proposito',
        'consecuencias',
        'integridad',
        'recursos',
        'estado',
        'responsable_id',
        'fecha_propuesta',
        'fecha_prevista',
        'fecha_implantacion',
        'fecha_cierre',
        'aprobado_por_id',
        'aprobado_en',
        'revision',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<User, $this> */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /**
     * Lo que se va a hacer para llevarlo a cabo.
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'cambio_sgsi_tarea')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /** @return HasMany<CambioSgsiTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(CambioSgsiTransicion::class)->orderByDesc('created_at');
    }

    /**
     * Aprobado, con la fecha prevista pasada y sin implantar.
     *
     * Es el único rojo del registro, y es de plazo y no de estado: la dirección
     * firmó un compromiso con fecha y la fecha pasó. Un cambio propuesto con la
     * fecha pasada no está fuera de plazo, está sin aprobar.
     */
    public function estaFueraDePlazo(): bool
    {
        return $this->estado === EstadoCambio::Aprobado
            && $this->fecha_prevista !== null
            && $this->fecha_prevista->isBefore(Carbon::today());
    }

    /**
     * Los que siguen abiertos: todo menos revisado y descartado.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAbiertos(Builder $query): void
    {
        $query->whereIn('cambios_sgsi.estado', [
            EstadoCambio::Propuesto->value,
            EstadoCambio::Aprobado->value,
            EstadoCambio::Implantado->value,
        ]);
    }

    /**
     * Esperando firma.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinAprobar(Builder $query): void
    {
        $query->where('cambios_sgsi.estado', EstadoCambio::Propuesto->value);
    }

    /**
     * La misma condición que `estaFueraDePlazo()`, para contar y filtrar.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFueraDePlazo(Builder $query): void
    {
        $query->where('cambios_sgsi.estado', EstadoCambio::Aprobado->value)
            ->whereNotNull('cambios_sgsi.fecha_prevista')
            ->whereDate('cambios_sgsi.fecha_prevista', '<', Carbon::today());
    }

    /**
     * Implantados y todavía sin mirar si sirvieron.
     *
     * **No es una alarma**: la 6.3 no fija plazo para esa comprobación, y
     * pintarla de rojo sería inventarse una obligación. Es lo que conviene
     * cerrar antes de la revisión por la dirección.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinRevisar(Builder $query): void
    {
        $query->where('cambios_sgsi.estado', EstadoCambio::Implantado->value);
    }

    /**
     * Aprobados sin ninguna tarea viva detrás.
     *
     * Sólo los aprobados: uno propuesto no tiene por qué tener trabajo todavía,
     * y uno implantado ya lo hizo. Se mira contra `Tarea::abiertas()`, el mismo
     * scope que cuentan el aviso diario y el tablero.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinTrabajo(Builder $query): void
    {
        $query->where('cambios_sgsi.estado', EstadoCambio::Aprobado->value)
            ->whereDoesntHave('tareas', static function (Builder $tareas): void {
                /** @var Builder<Tarea> $tareas */
                $tareas->abiertas();
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ambito' => AmbitoCambio::class,
            'origen' => OrigenCambio::class,
            'estado' => EstadoCambio::class,
            'fecha_propuesta' => 'date',
            'fecha_prevista' => 'date',
            'fecha_implantacion' => 'date',
            'fecha_cierre' => 'date',
            'aprobado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): CambioSgsiFactory
    {
        return CambioSgsiFactory::new();
    }
}
