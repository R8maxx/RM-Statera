<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Models;

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\NoConformidad\NoConformidadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una no conformidad y su tratamiento: § 4.13 y la cláusula 10.2 de ISO.
 *
 * Es lo que convierte un hallazgo en algo que se cierra. Un hallazgo dice qué se
 * encontró; esto dice por qué pasó, qué se hizo, quién responde y **si funcionó**.
 *
 * **Las acciones correctivas son tareas, y el vínculo es N:M.** Una acción
 * correctiva tiene responsable, plazo, estado y coste, que es literalmente una
 * tarea; con una columna de texto quedaría fuera del tablero, del calendario, del
 * aviso diario y del presupuesto del plan de adecuación. Y N:M porque «implantar
 * MFA» cierra a la vez una no conformidad de la auditoría ISO y otra de la
 * autoevaluación del ENS — el mismo argumento del invariante 6.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property OrigenNoConformidad $origen
 * @property ?int $hallazgo_id
 * @property string $descripcion
 * @property ?string $correccion_inmediata
 * @property ?string $analisis_causa_raiz
 * @property EstadoNoConformidad $estado
 * @property ?int $responsable_id
 * @property Carbon $fecha_deteccion
 * @property ?Carbon $fecha_prevista
 * @property ?Carbon $fecha_cierre
 * @property ?Carbon $fecha_verificacion
 * @property ?int $verificada_por_id
 * @property ?string $resultado_verificacion
 */
class NoConformidad extends Model
{
    /** @use HasFactory<NoConformidadFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'no_conformidades';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'origen',
        'hallazgo_id',
        'incidente_id',
        'descripcion',
        'correccion_inmediata',
        'analisis_causa_raiz',
        'estado',
        'responsable_id',
        'fecha_deteccion',
        'fecha_prevista',
        'fecha_cierre',
        'fecha_verificacion',
        'verificada_por_id',
        'resultado_verificacion',
    ];

    /** @return BelongsTo<Hallazgo, $this> */
    public function hallazgo(): BelongsTo
    {
        return $this->belongsTo(Hallazgo::class);
    }

    /**
     * El incidente del que salió, si salió de uno.
     *
     * Espejo exacto de `hallazgo()`: único, `nullOnDelete`, y con un `CHECK` que
     * impide que vengan de un hallazgo y de un incidente a la vez.
     *
     * @return BelongsTo<Incidente, $this>
     */
    public function incidente(): BelongsTo
    {
        return $this->belongsTo(Incidente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verificadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificada_por_id');
    }

    /**
     * Las acciones correctivas.
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'no_conformidad_tarea')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /** @return HasMany<NoConformidadTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(NoConformidadTransicion::class)->orderByDesc('created_at');
    }

    /**
     * Vencida es distinto de sin plazo, como en tareas.
     *
     * Una no conformidad sin fecha prevista no es que no corra prisa: es que
     * nadie ha dicho para cuándo, y colapsar las dos cosas esconde justo las que
     * nadie ha fechado nunca.
     */
    public function haVencido(): bool
    {
        return ! $this->estado->esCerrada()
            && $this->fecha_prevista !== null
            && $this->fecha_prevista->isBefore(Carbon::today());
    }

    /**
     * Abiertas: lo que sigue siendo trabajo.
     *
     * Es el scope sobre el que se cuenta todo lo demás, igual que el cumplimiento
     * se cuenta sobre lo exigible. Una anulada no está pendiente: está cerrada,
     * con su motivo en el histórico.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->whereIn('no_conformidades.estado', [
            EstadoNoConformidad::Abierta->value,
            EstadoNoConformidad::EnTratamiento->value,
        ]);
    }

    /** @param  Builder<$this>  $query */
    public function scopeVencidas(Builder $query): void
    {
        $query->abiertas()
            ->whereNotNull('no_conformidades.fecha_prevista')
            ->whereDate('no_conformidades.fecha_prevista', '<', Carbon::today());
    }

    /**
     * Tratadas y sin comprobar que la corrección sirvió.
     *
     * Es **la pregunta del auditor**, y la razón de que `Cerrada` y `Verificada`
     * sean dos estados: sin separarlas, «cerrada» se lee como «resuelta» y la
     * cláusula 10.2 e) se queda sin hacer en el sitio donde no se nota.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePendientesDeVerificar(Builder $query): void
    {
        $query->where('no_conformidades.estado', EstadoNoConformidad::Cerrada->value);
    }

    /**
     * Abiertas sin ninguna acción correctiva viva detrás.
     *
     * Misma forma que `Implantacion::sinTrabajo()`, y por lo mismo: una no
     * conformidad registrada y sin nada en marcha es la que se queda quieta sin
     * que nadie lo note. Se mira contra `Tarea::abiertas()` —el mismo scope que
     * cuentan el aviso diario y el tablero— y no contra el número de vínculos:
     * una acción correctiva descartada no está tratando nada.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinAccion(Builder $query): void
    {
        $query->abiertas()->whereDoesntHave('tareas', static function (Builder $tareas): void {
            /** @var Builder<Tarea> $tareas */
            $tareas->abiertas();
        });
    }

    /**
     * Abiertas sin análisis de causa raíz.
     *
     * La cláusula 10.2 b) lo pide por escrito, y es el paso que se salta cuando
     * hay prisa: sin causa, la acción correctiva trata el síntoma y la no
     * conformidad vuelve el año que viene. Cuenta como vacío el texto en blanco,
     * no sólo el nulo — un espacio rellena la columna y no dice nada.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinCausaRaiz(Builder $query): void
    {
        $query->abiertas()->where(static function (Builder $consulta): void {
            $consulta->whereNull('no_conformidades.analisis_causa_raiz')
                ->orWhereRaw('length(trim(no_conformidades.analisis_causa_raiz)) = 0');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origen' => OrigenNoConformidad::class,
            'estado' => EstadoNoConformidad::class,
            'fecha_deteccion' => 'date',
            'fecha_prevista' => 'date',
            'fecha_cierre' => 'date',
            'fecha_verificacion' => 'date',
        ];
    }

    protected static function newFactory(): NoConformidadFactory
    {
        return NoConformidadFactory::new();
    }
}
