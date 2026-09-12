<?php

declare(strict_types=1);

namespace App\Domain\Tarea\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Models\User;
use Database\Factories\TareaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una tarea del plan de acción: qué hay que hacer, quién y para cuándo.
 *
 * El vínculo con implantaciones es N:M por lo mismo que el de evidencias
 * (invariante 6): «revisar la política de contraseñas» cierra a la vez un
 * control de ISO y tres medidas del ENS, y anotarla dos veces sería volver a las
 * hojas de cálculo duplicadas.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $titulo
 * @property ?string $descripcion
 * @property OrigenTarea $origen
 * @property EstadoTarea $estado
 * @property PrioridadTarea $prioridad
 * @property ?int $responsable_id
 * @property ?Carbon $fecha_limite
 * @property ?Carbon $fecha_cierre
 * @property ?string $coste_estimado
 * @property ?string $notas
 */
class Tarea extends Model
{
    /** @use HasFactory<TareaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'tareas';

    protected $fillable = [
        'organizacion_id',
        'titulo',
        'descripcion',
        'origen',
        'estado',
        'prioridad',
        'responsable_id',
        'fecha_limite',
        'fecha_cierre',
        'coste_estimado',
        'notas',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Los requisitos que esta tarea hace avanzar, de cualquier marco.
     *
     * @return BelongsToMany<Implantacion, $this>
     */
    public function implantaciones(): BelongsToMany
    {
        return $this->belongsToMany(Implantacion::class, 'implantacion_tarea')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /** @return HasMany<TareaTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(TareaTransicion::class)->orderByDesc('created_at');
    }

    /**
     * Vencida es distinto de sin plazo.
     *
     * Una tarea sin fecha límite no es que no corra prisa: es que nadie ha dicho
     * para cuándo. La diferencia importa cuando se cuenta, igual que con la
     * caducidad de una evidencia, y por eso no se colapsan las dos en un
     * booleano.
     */
    public function haVencido(): bool
    {
        return ! $this->estado->esCerrada()
            && $this->fecha_limite !== null
            && $this->fecha_limite->isBefore(Carbon::today());
    }

    /**
     * Abiertas: lo que sigue siendo trabajo.
     *
     * Es el scope con el que se cuenta todo lo demás, igual que el cumplimiento
     * se cuenta sobre lo exigible y el inventario sobre lo vigente. Una tarea
     * descartada no está pendiente: está cerrada, con su motivo en el histórico.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->whereNotIn('estado', [EstadoTarea::Hecha->value, EstadoTarea::Descartada->value]);
    }

    /** @param  Builder<$this>  $query */
    public function scopeVencidas(Builder $query): void
    {
        $query->abiertas()
            ->whereNotNull('fecha_limite')
            ->whereDate('fecha_limite', '<', Carbon::today());
    }

    /**
     * Las que vencen dentro de los próximos `$dias`, sin contar las vencidas.
     *
     * Misma forma que `Evidencia::porCaducar()`, y a propósito: el aviso diario
     * junta las dos y una ventana que se contara distinto daría dos listas que no
     * se pueden leer seguidas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePorVencer(Builder $query, int $dias = 30): void
    {
        $query->abiertas()
            ->whereNotNull('fecha_limite')
            ->whereDate('fecha_limite', '>=', Carbon::today())
            ->whereDate('fecha_limite', '<=', Carbon::today()->addDays($dias));
    }

    /** @param  Builder<$this>  $query */
    public function scopeSinResponsable(Builder $query): void
    {
        $query->abiertas()->whereNull('responsable_id');
    }

    /**
     * Alguien la cogió y no puede avanzar.
     *
     * Es distinto de que nadie la haya cogido, y por eso se cuenta aparte: una
     * pide asignarla y la otra pide destrabar algo. Es además la que más cara
     * sale de no ver, porque no se mueve sola.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBloqueadas(Builder $query): void
    {
        $query->where('estado', EstadoTarea::Bloqueada->value);
    }

    /**
     * Abiertas y sin fecha: nunca van a vencer ni a salir en ningún aviso.
     *
     * Mismo caso que una evidencia sin caducidad: no es que no corra prisa, es
     * que nadie ha dicho para cuándo, y colapsar las dos cosas esconde justo las
     * que nadie ha fechado nunca.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinPlazo(Builder $query): void
    {
        $query->abiertas()->whereNull('fecha_limite');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origen' => OrigenTarea::class,
            'estado' => EstadoTarea::class,
            'prioridad' => PrioridadTarea::class,
            'fecha_limite' => 'date',
            'fecha_cierre' => 'date',
        ];
    }

    protected static function newFactory(): TareaFactory
    {
        return TareaFactory::new();
    }
}
