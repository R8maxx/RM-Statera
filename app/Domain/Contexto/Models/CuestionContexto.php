<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Models;

use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\Signo;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Contexto\CuestionContextoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Una cuestión interna o externa: una casilla del DAFO. Cláusula 4.1 de ISO.
 *
 * **Vive entre análisis**, con `analisis_alta_id` y `analisis_baja_id`. No se
 * copia en cada revisión porque su identidad es lo que sostiene los vínculos: una
 * amenaza que abrió un riesgo el año pasado tiene que seguir siendo la misma
 * amenaza este año, o el riesgo se queda apuntando a una fila muerta.
 *
 * **El ámbito y el signo no son columnas**: se derivan del tipo, que es lo que
 * define un DAFO. Guardarlos sería la misma información en tres sitios que pueden
 * discrepar, y reclasificar una cuestión pasaría de cambiar un campo a cambiar
 * tres y acordarse de los tres.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property TipoCuestion $tipo
 * @property string $titulo
 * @property ?string $descripcion
 * @property MateriaCuestion $materia
 * @property bool $es_climatica
 * @property ?int $responsable_id
 * @property int $analisis_alta_id
 * @property ?int $analisis_baja_id
 * @property ?string $motivo_baja
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class CuestionContexto extends Model
{
    /** @use HasFactory<CuestionContextoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'cuestiones_contexto';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'tipo',
        'titulo',
        'descripcion',
        'materia',
        'es_climatica',
        'responsable_id',
        'analisis_alta_id',
        'analisis_baja_id',
        'motivo_baja',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<AnalisisContexto, $this> */
    public function analisisAlta(): BelongsTo
    {
        return $this->belongsTo(AnalisisContexto::class, 'analisis_alta_id');
    }

    /** @return BelongsTo<AnalisisContexto, $this> */
    public function analisisBaja(): BelongsTo
    {
        return $this->belongsTo(AnalisisContexto::class, 'analisis_baja_id');
    }

    /**
     * Los riesgos que esta cuestión ha abierto.
     *
     * N:M porque una amenaza puede abrir varios riesgos y un riesgo puede venir de
     * dos cuestiones a la vez. Es lo que hace que el DAFO no sea un papel suelto:
     * «dependemos de un solo proveedor de nube» deja de ser una frase en un acta y
     * pasa a ser el origen declarado de R-014.
     *
     * @return BelongsToMany<Riesgo, $this>
     */
    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'cuestion_riesgo', 'cuestion_contexto_id', 'riesgo_id')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /**
     * El trabajo que sale de esta cuestión.
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'cuestion_tarea', 'cuestion_contexto_id', 'tarea_id')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /**
     * Las que siguen sobre la mesa: no las ha retirado ningún análisis.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('cuestiones_contexto.analisis_baja_id');
    }

    /** @param  Builder<$this>  $query */
    public function scopeDeTipo(Builder $query, TipoCuestion $tipo): void
    {
        $query->where('cuestiones_contexto.tipo', $tipo);
    }

    /**
     * Las que van en contra: debilidades y amenazas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAdversas(Builder $query): void
    {
        $query->whereIn('cuestiones_contexto.tipo', array_map(
            static fn (TipoCuestion $tipo): string => $tipo->value,
            array_filter(
                TipoCuestion::cases(),
                static fn (TipoCuestion $tipo): bool => $tipo->signo() === Signo::Adverso,
            ),
        ));
    }

    /**
     * Las que van en contra y no han acabado en ningún riesgo.
     *
     * Es el indicador que de verdad se mira: un DAFO cuyas amenazas no aparecen por
     * ninguna parte en el análisis de riesgos es un DAFO que se escribió para el
     * auditor y no para trabajar con él. No es un incumplimiento —puede que la
     * amenaza no dé para un riesgo— y por eso no va en rojo; es una pregunta
     * pendiente.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinRiesgo(Builder $query): void
    {
        $query->vigentes()->adversas()->whereDoesntHave('riesgos');
    }

    /**
     * Las que van en contra, siguen vigentes y no tienen ninguna tarea abierta.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinTrabajo(Builder $query): void
    {
        $query->vigentes()->adversas()->whereDoesntHave('tareas', static function (Builder $tareas): void {
            /** @var Builder<Tarea> $tareas */
            $tareas->abiertas();
        });
    }

    /**
     * Las del cambio climático, que es lo que la enmienda 1:2024 hace preguntar.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeClimaticas(Builder $query): void
    {
        $query->where('cuestiones_contexto.es_climatica', true);
    }

    public function estaVigente(): bool
    {
        return $this->analisis_baja_id === null;
    }

    public function ambito(): Ambito
    {
        return $this->tipo->ambito();
    }

    public function signo(): Signo
    {
        return $this->tipo->signo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCuestion::class,
            'materia' => MateriaCuestion::class,
            'es_climatica' => 'boolean',
        ];
    }

    protected static function newFactory(): CuestionContextoFactory
    {
        return CuestionContextoFactory::new();
    }
}
