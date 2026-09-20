<?php

declare(strict_types=1);

namespace App\Domain\Mejora\Models;

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Mejora\MejoraFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una oportunidad de mejora: la cláusula 10.1 de ISO 27001.
 *
 * Es el sitio donde vive lo que se puede hacer mejor **sin que nada incumpla**, y
 * hasta este módulo sólo existía dentro de una auditoría como
 * `TipoHallazgo::OportunidadMejora`.
 *
 * **No es una no conformidad con otro nombre**, y las dos tablas están separadas
 * por un motivo aritmético antes que conceptual: «no conformidades abiertas» es
 * cifra del panel, cálculo de indicador y entrada de la 9.3, y meter aquí las
 * mejoras contaría una idea como un incumplimiento en los tres sitios.
 *
 * Y por eso esta clase es **mucho más corta** que `NoConformidad`: sin causa raíz,
 * sin corrección inmediata y sin verificación de eficacia. No hay nada que
 * verificar porque no había nada roto.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property OrigenMejora $origen
 * @property ?int $hallazgo_id
 * @property string $titulo
 * @property ?string $descripcion
 * @property ?string $beneficio_esperado
 * @property EstadoMejora $estado
 * @property ?int $responsable_id
 * @property Carbon $fecha_deteccion
 * @property ?Carbon $fecha_prevista
 * @property ?Carbon $fecha_cierre
 */
class Mejora extends Model
{
    /** @use HasFactory<MejoraFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'mejoras';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'origen',
        'hallazgo_id',
        'titulo',
        'descripcion',
        'beneficio_esperado',
        'estado',
        'responsable_id',
        'fecha_deteccion',
        'fecha_prevista',
        'fecha_cierre',
    ];

    /** @return BelongsTo<Hallazgo, $this> */
    public function hallazgo(): BelongsTo
    {
        return $this->belongsTo(Hallazgo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Lo que se va a hacer para materializarla.
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'mejora_tarea')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /** @return HasMany<MejoraTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(MejoraTransicion::class)->orderByDesc('created_at');
    }

    /**
     * Pasada de fecha, que **no es lo mismo que vencida**.
     *
     * Se nombra así a propósito y no `haVencido()`: una mejora con la fecha pasada
     * no incumple nada —nadie se comprometió a ella, que es justo lo que la
     * distingue de un objetivo de la 6.2 y de una acción correctiva—. Se señala
     * para que no se quede olvidada, y **no gasta rojo**.
     */
    public function sePasoDeFecha(): bool
    {
        return ! $this->estado->esCerrada()
            && $this->fecha_prevista !== null
            && $this->fecha_prevista->isBefore(Carbon::today());
    }

    /**
     * Las que siguen abiertas.
     *
     * Es el scope sobre el que se cuenta todo lo demás. Una descartada no está
     * pendiente: está cerrada, con su motivo en el histórico.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->whereIn('mejoras.estado', [
            EstadoMejora::Propuesta->value,
            EstadoMejora::EnCurso->value,
        ]);
    }

    /**
     * Abiertas con la fecha prevista pasada.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePasadasDeFecha(Builder $query): void
    {
        $query->abiertas()
            ->whereNotNull('mejoras.fecha_prevista')
            ->whereDate('mejoras.fecha_prevista', '<', Carbon::today());
    }

    /**
     * Propuestas que nadie ha empezado.
     *
     * Es la cifra honesta de este registro: un buzón de ideas al que nadie vuelve
     * es lo que la 10.1 no acepta como mejora continua. Y no es una alarma —no
     * empezar una idea es una decisión válida—, es lo que hay que mirar antes de
     * una revisión por la dirección.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinEmpezar(Builder $query): void
    {
        $query->where('mejoras.estado', EstadoMejora::Propuesta->value);
    }

    /**
     * Abiertas sin ninguna tarea viva detrás.
     *
     * Misma forma que `NoConformidad::sinAccion()` y `Objetivo::sinActuacion()`, y
     * se mira contra `Tarea::abiertas()` —el mismo scope que cuentan el aviso
     * diario y el tablero— y no contra el número de vínculos.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinTrabajo(Builder $query): void
    {
        $query->abiertas()->whereDoesntHave('tareas', static function (Builder $tareas): void {
            /** @var Builder<Tarea> $tareas */
            $tareas->abiertas();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origen' => OrigenMejora::class,
            'estado' => EstadoMejora::class,
            'fecha_deteccion' => 'date',
            'fecha_prevista' => 'date',
            'fecha_cierre' => 'date',
        ];
    }

    protected static function newFactory(): MejoraFactory
    {
        return MejoraFactory::new();
    }
}
