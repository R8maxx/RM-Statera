<?php

declare(strict_types=1);

namespace App\Domain\Objetivo\Models;

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Objetivo\Avance;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Objetivo\ObjetivoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un objetivo de seguridad de la información: la cláusula 6.2 de ISO 27001.
 *
 * Es a lo que la organización se compromete, y lo que la revisión por la
 * dirección pregunta al cerrar el año. Hasta aquí el producto **medía** —§ 4.14,
 * cláusula 9.1— y no había dónde comprometerse a una cifra; son dos cosas
 * distintas y la norma las pide las dos.
 *
 * **Lo que la 6.2 pide, y dónde está cada cosa:**
 *
 * | 6.2 | Dónde |
 * |---|---|
 * | Qué se hará (planificación a) | `tareas()`, N:M |
 * | Qué recursos (b) | `recursos`, texto libre |
 * | Quién responde (c) | `responsable_id` |
 * | Cuándo se termina (d) | `fecha_objetivo`, exigida al aprobar |
 * | Cómo se evalúan los resultados (e) | `indicadores()`, N:M |
 * | Que sea medible | `indicadores()` otra vez, y por eso el § 4.14 fue antes |
 *
 * **El veredicto lo declara una persona; lo derivado se enseña al lado y no lo
 * sobrescribe nunca.** Al cierre, quien firma decide si el objetivo se alcanzó;
 * `Avance` dice lo que las cifras cuentan mientras tanto. Es el precedente exacto
 * del riesgo residual, y el invariante 4 no aplica aquí: aquél es una derivación
 * legal con una respuesta correcta en el BOE, y esto no tiene BOE.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property ?string $descripcion
 * @property EstadoObjetivo $estado
 * @property ?int $responsable_id
 * @property ?string $recursos
 * @property ?Carbon $fecha_objetivo
 * @property ?Carbon $fecha_cierre
 * @property ?int $aprobado_por_id
 * @property ?Carbon $aprobado_en
 * @property ?string $nota_aprobacion
 */
class Objetivo extends Model
{
    /** @use HasFactory<ObjetivoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'objetivos_seguridad';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'descripcion',
        'estado',
        'responsable_id',
        'recursos',
        'fecha_objetivo',
        'fecha_cierre',
        'aprobado_por_id',
        'aprobado_en',
        'nota_aprobacion',
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
     * Cómo se evalúan los resultados (6.2, planificación e).
     *
     * @return BelongsToMany<Indicador, $this>
     */
    public function indicadores(): BelongsToMany
    {
        return $this->belongsToMany(Indicador::class, 'indicador_objetivo')
            ->withPivot(['vinculado_por_id', 'created_at']);
    }

    /**
     * Las actuaciones: qué se va a hacer (6.2, planificación a).
     *
     * @return BelongsToMany<Tarea, $this>
     */
    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'objetivo_tarea')
            ->withPivot(['vinculada_por_id', 'created_at']);
    }

    /** @return HasMany<ObjetivoTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(ObjetivoTransicion::class)->orderByDesc('created_at');
    }

    /**
     * Cómo van sus indicadores. Ver `Avance`: se deriva y no se guarda.
     *
     * Pide `indicadores.ultimaMedicion` cargado; quien lo llame en una lista tiene
     * que traerlo, o son dos consultas por fila.
     */
    public function avance(): Avance
    {
        return Avance::de($this->indicadores);
    }

    /**
     * Vencido es distinto de sin plazo, como en tareas y en no conformidades.
     *
     * Sólo vence lo **comprometido**: un objetivo en borrador al que nadie ha
     * puesto fecha no está fuera de plazo, está sin aprobar. Por eso la base
     * tampoco le exige la fecha hasta que se firma.
     */
    public function haVencido(): bool
    {
        return ! $this->estado->esCerrado()
            && $this->estado->esComprometido()
            && $this->fecha_objetivo !== null
            && $this->fecha_objetivo->isBefore(Carbon::today());
    }

    /**
     * Los que siguen vivos: propuestos y aprobados.
     *
     * Es el scope sobre el que se cuenta todo lo demás, igual que el cumplimiento
     * se cuenta sobre lo exigible. Uno retirado no está pendiente: está cerrado,
     * con su motivo en el histórico.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVivos(Builder $query): void
    {
        $query->whereIn('objetivos_seguridad.estado', [
            EstadoObjetivo::Propuesto->value,
            EstadoObjetivo::Aprobado->value,
        ]);
    }

    /**
     * Los que la dirección ya firmó y siguen en marcha.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAprobados(Builder $query): void
    {
        $query->where('objetivos_seguridad.estado', EstadoObjetivo::Aprobado->value);
    }

    /**
     * Aprobados que se pasaron de fecha sin cerrarse.
     *
     * **Éste es el rojo del módulo**, y no quedarse corto respecto a la cifra: un
     * objetivo comprometido cuyo plazo pasó y que nadie ha cerrado es la 6.2 sin
     * terminar, y es lo primero que la revisión por la dirección encuentra.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVencidos(Builder $query): void
    {
        $query->aprobados()
            ->whereNotNull('objetivos_seguridad.fecha_objetivo')
            ->whereDate('objetivos_seguridad.fecha_objetivo', '<', Carbon::today());
    }

    /**
     * Vivos sin ningún indicador que los evalúe.
     *
     * Es la 6.2 e) sin hacer —«cómo se evaluarán los resultados»— y el campo que
     * el auditor mira primero, precisamente porque es el que se deja para luego.
     * Un objetivo sin indicador no es medible, y la cláusula exige que lo sea.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinIndicador(Builder $query): void
    {
        $query->vivos()->whereDoesntHave('indicadores');
    }

    /**
     * Vivos sin ninguna actuación viva detrás.
     *
     * Misma forma que `NoConformidad::sinAccion()` e `Implantacion::sinTrabajo()`,
     * y por lo mismo: un objetivo registrado y sin nada en marcha es el que se
     * queda quieto sin que nadie lo note. Se mira contra `Tarea::abiertas()` —el
     * mismo scope que cuentan el aviso diario y el tablero— y no contra el número
     * de vínculos: una actuación descartada no está haciendo avanzar nada.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinActuacion(Builder $query): void
    {
        $query->vivos()->whereDoesntHave('tareas', static function (Builder $tareas): void {
            /** @var Builder<Tarea> $tareas */
            $tareas->abiertas();
        });
    }

    /**
     * Resuelve `/objetivos/{objetivo}/indicadores/{indicador}` acotando el
     * indicador a su objetivo.
     *
     * **Hay que escribirlo a mano, y es el tercer sitio del producto donde pasa lo
     * mismo.** `scopeBindings()` deduce la relación pluralizando el nombre del
     * parámetro **en inglés** —`indicador` → `indicadors`— y aquí el dominio se
     * nombra en español. Sin esto la ruta responde 500 con un «Call to undefined
     * method» que no menciona ni la ruta ni la relación, y de paso deja de acotar:
     * el indicador de otro objetivo se desvincularía desde éste.
     *
     * Los precedentes exactos son `Documento::resolveChildRouteBinding()` —para
     * `version` → `versions`— e `Indicador::resolveChildRouteBinding()` —para
     * `medicion` → `medicions`—, y **lo cazó igual que allí un test de aislamiento
     * y no una revisión**. `tarea` no hace falta declararla: su plural inglés
     * coincide con el español, que es justo lo que hace que este fallo sea
     * intermitente y difícil de ver leyendo las rutas.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $campo
     * @return Model|null
     */
    public function resolveChildRouteBinding($childType, $value, $campo)
    {
        if ($childType === 'indicador') {
            return $this->indicadores()->where($campo ?? 'indicadores.id', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $campo);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoObjetivo::class,
            'fecha_objetivo' => 'date',
            'fecha_cierre' => 'date',
            'aprobado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): ObjetivoFactory
    {
        return ObjetivoFactory::new();
    }
}
