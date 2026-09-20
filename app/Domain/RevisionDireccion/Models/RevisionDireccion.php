<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\RevisionDireccion\RevisionDireccionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Una revisión por la dirección: § 4.15 y la cláusula 9.3 de ISO 27001.
 *
 * **Choca de nombre con `RevisionInventario`**, que es otra cosa: aquélla es el
 * «inventario mantenido» que piden A.5.9 y `op.exp.1`, y vive en `/revisiones`.
 * Ésta es la reunión en la que la dirección revisa el SGSI, y vive en
 * `/revision-direccion`. Se anota y no se renombra lo que ya está, como con
 * `Contexto` frente a `ContextoOrganizacion`.
 *
 * **Lo que la convierte en un hecho es aprobarla**: ahí se congelan las siete
 * entradas de la 9.3.2 en `instantanea` y el trigger de PostgreSQL vuelve la fila
 * inmutable. Consultarlas en vivo haría que el acta de marzo enseñara las cifras
 * de octubre, que es el fallo que este producto ya ha evitado cuatro veces.
 *
 * **Las salidas son tareas** (9.3.3), y la pivote se lee en los dos sentidos: de
 * ésta hacia delante son sus decisiones, y desde la siguiente revisión hacia atrás
 * son «el estado de las acciones de revisiones previas» — la primera entrada
 * obligatoria de la 9.3.2.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property Carbon $fecha
 * @property Carbon $periodo_desde
 * @property Carbon $periodo_hasta
 * @property ?string $asistentes
 * @property EstadoRevision $estado
 * @property ?array<string, mixed> $instantanea
 * @property ?string $conclusiones
 * @property ?int $aprobada_por_id
 * @property ?Carbon $aprobada_en
 */
class RevisionDireccion extends Model
{
    /** @use HasFactory<RevisionDireccionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'revisiones_direccion';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'fecha',
        'periodo_desde',
        'periodo_hasta',
        'asistentes',
        'estado',
        'instantanea',
        'conclusiones',
        'aprobada_por_id',
        'aprobada_en',
    ];

    /** @return BelongsTo<User, $this> */
    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por_id');
    }

    /**
     * Las salidas de la revisión (9.3.3).
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'revision_tarea', 'revision_direccion_id')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /** Si todavía se le pueden tocar las entradas, los asistentes y el acta. */
    public function admiteCambios(): bool
    {
        return $this->estado->admiteCambios();
    }

    public function etiqueta(): string
    {
        return $this->codigo;
    }

    /**
     * El periodo revisado, escrito como se lee en un acta.
     *
     * Con el año siempre, incluso cuando los dos extremos caen en el mismo: «del
     * 1 al 31 de enero» obliga a buscar el año en otra parte, y un acta se lee
     * suelta y fuera de la herramienta.
     */
    public function periodo(): string
    {
        return sprintf(
            'del %s al %s',
            $this->periodo_desde->format('d/m/Y'),
            $this->periodo_hasta->format('d/m/Y'),
        );
    }

    /**
     * Las aprobadas, de la más reciente a la más antigua.
     *
     * Es el scope que usa la entrada de «acciones de revisiones previas»: la
     * anterior a una revisión es la última aprobada con fecha anterior a la suya.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAprobadas(Builder $query): void
    {
        $query->where('revisiones_direccion.estado', EstadoRevision::Aprobada->value);
    }

    /**
     * Las que siguen abiertas: convocadas o celebrándose.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAbiertas(Builder $query): void
    {
        $query->whereIn('revisiones_direccion.estado', [
            EstadoRevision::Planificada->value,
            EstadoRevision::EnCurso->value,
        ]);
    }

    /**
     * La revisión aprobada inmediatamente anterior a ésta.
     *
     * **Por fecha de celebración y no por `created_at`**: una revisión del
     * ejercicio pasado puede registrarse en Statera después que la de este año, y
     * ordenar por cuándo se tecleó daría «acciones previas» de una reunión que
     * todavía no había ocurrido.
     */
    public function anterior(): ?self
    {
        return self::query()
            ->aprobadas()
            ->whereKeyNot($this->id)
            ->whereDate('fecha', '<=', $this->fecha)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoRevision::class,
            'fecha' => 'date',
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'aprobada_en' => 'datetime',
            'instantanea' => 'array',
        ];
    }

    protected static function newFactory(): RevisionDireccionFactory
    {
        return RevisionDireccionFactory::new();
    }
}
