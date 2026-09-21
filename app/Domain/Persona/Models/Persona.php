<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Adjunto\Concerns\ConAdjuntos;
use App\Domain\Adjunto\Concerns\TieneAdjuntos;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Persona\PersonaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Una persona de la organización: § 4.8, cláusula 5.3 y `mp.per.*`.
 *
 * **No es un `User`.** `users` son cuentas de Statera —quien entra, mira y cierra
 * tareas— y esto es el registro de plantilla: quien firma un acuerdo, asiste a la
 * formación y puede ser designado responsable de seguridad, tenga o no cuenta. La
 * mayoría no la tiene, y por eso los responsables de activos, tareas y evidencias
 * siguen apuntando a `users` y no se migran: asignar una tarea a quien no puede
 * entrar a cerrarla no sirve de nada.
 *
 * `user_id` es el puente entre los dos mundos, y puede estar vacío.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $nombre_pila
 * @property ?string $apellido1
 * @property ?string $apellido2
 * @property-read string $nombre el completo, que calcula PostgreSQL
 * @property ?string $nif
 * @property ?string $telefono
 * @property ?string $telefono_fijo
 * @property ?string $direccion
 * @property ?Carbon $fecha_nacimiento
 * @property ?string $email
 * @property ?int $user_id
 * @property Carbon $fecha_alta
 * @property ?Carbon $fecha_baja
 * @property ?string $notas
 */
class Persona extends Model implements ConAdjuntos
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;
    use TieneAdjuntos;

    /**
     * Cuántos meses vale una formación antes de considerarse caducada.
     *
     * **Doce, y es una convención del producto y no de la norma.** El ENS no pone
     * un número en `mp.per.4`: dice que la formación se imparta «periódicamente».
     * Doce meses es el ciclo con el que trabajan la revisión por la dirección, la
     * auditoría interna y el informe INES, así que es la cadencia contra la que
     * esta organización ya mide todo lo demás. Va declarado en el módulo, y quien
     * quiera otra cadencia la fija con un indicador propio.
     */
    public const MESES_DE_VIGENCIA_FORMATIVA = 12;

    /**
     * `nombre` NO está, y no es un olvido: la calcula PostgreSQL.
     *
     * Ver el accesor de abajo y la migración `ampliar_datos_de_la_persona`.
     */
    protected $fillable = [
        'organizacion_id',
        'codigo',
        'nombre_pila',
        'apellido1',
        'apellido2',
        'nif',
        'telefono',
        'telefono_fijo',
        'direccion',
        'fecha_nacimiento',
        'email',
        'user_id',
        'fecha_alta',
        'fecha_baja',
        'notas',
    ];

    /**
     * Tras insertar hay que releer la fila, porque `nombre` la calcula la base.
     *
     * El `INSERT` de Eloquent sólo recupera el `id`, así que una persona recién
     * creada llega **sin `nombre`** y lo primero que lo lea recibe `null`: un
     * `TypeError` en la excepción de `DesignarRol`, un mensaje que empieza por un
     * espacio en el controlador, o un `sprintf` con un hueco. Y ninguno de los
     * tres menciona la palabra «generada».
     *
     * Va aquí y no en cada llamador por lo mismo que la regla de una transición
     * vive en la acción de dominio y no en el `FormRequest`: vale igual para el
     * controlador, para el seeder, para una factory y para un importador. Es el
     * mismo problema que resuelven a mano `CrearTarea`, `RegistrarAuditoria` y
     * `GenerarDocumento::encolar()` con sus valores por defecto de la base; la
     * diferencia es que aquí la columna **nunca** se puede escribir desde PHP, así
     * que no hay forma de adelantarla.
     *
     * La alternativa era recomponer el nombre en PHP, y sería la misma regla
     * escrita dos veces — justo lo que la columna generada existe para evitar.
     */
    protected static function booted(): void
    {
        static::created(static function (self $persona): void {
            $persona->refresh();
        });
    }

    /**
     * El nombre completo lo calcula la base y aquí sólo se lee.
     *
     * Es **columna generada `STORED`** y no un accesor de PHP, porque
     * `PersonaRecurso` la ordena, la busca y la usa de `ordenPorDefecto()`: eso
     * exige una columna de SQL de verdad. Y no se escribe al lado de sus partes
     * porque sería el mismo dato en dos sitios que pueden discrepar.
     *
     * El `set` que lanza no es paranoia. Sacarla de `$fillable` tapa la
     * asignación masiva, pero `$persona->nombre = 'x'` seguiría llegando a la
     * base, y allí PostgreSQL contesta «cannot insert a non-DEFAULT value into
     * column "nombre"» — un error que no menciona ni el modelo ni la línea que
     * lo escribió.
     *
     * @return Attribute<string, never>
     */
    protected function nombre(): Attribute
    {
        return Attribute::make(
            set: fn (): never => throw new LogicException(
                'personas.nombre la calcula la base desde nombre_pila, apellido1 y apellido2: escribe esas tres.',
            ),
        );
    }

    /** Los títulos, contratos y demás papeles de esta persona. */
    public function tablaDeAdjuntos(): string
    {
        return 'persona_adjunto';
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<DesignacionRol, $this> */
    public function designaciones(): HasMany
    {
        return $this->hasMany(DesignacionRol::class)->orderByDesc('desde');
    }

    /**
     * Las acciones formativas a las que **asistió**.
     *
     * La pivote guarda además a quién se convocó y no fue, que es un hecho
     * distinto; esta relación se queda con las asistencias de verdad porque es lo
     * que `mp.per.4` pide poder enseñar.
     *
     * @return BelongsToMany<AccionFormativa, $this>
     */
    public function formacion(): BelongsToMany
    {
        return $this->belongsToMany(AccionFormativa::class, 'asistencias')
            ->withPivot(['asistio', 'registrada_en'])
            ->wherePivot('asistio', true);
    }

    /** @return HasMany<Asistencia, $this> */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Las asignaciones de puesto, vigentes y cerradas.
     *
     * **Sin joins ni orden**, como `Auditoria::puntos()` y `Puesto::asignaciones()`:
     * el *route model binding* acotado resuelve el hijo con un `where` sin
     * cualificar, y con otra tabla unida muere con «column reference "id" is
     * ambiguous», un error que no menciona ni la ruta ni la relación.
     *
     * @return HasMany<AsignacionPuesto, $this>
     */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionPuesto::class);
    }

    /**
     * El puesto que ocupa hoy, si ocupa alguno.
     *
     * **Se deriva de la asignación vigente y no se guarda en `personas`**, que es
     * lo mismo que `activa` con `fecha_baja` y `vigente` con el estado del
     * análisis del contexto: con una columna al lado, cambiar de puesto sería
     * escribir en dos sitios y acordarse de los dos.
     */
    public function puestoVigente(): ?Puesto
    {
        return $this->asignaciones
            ->first(static fn (AsignacionPuesto $asignacion): bool => $asignacion->estaVigente())
            ?->puesto;
    }

    /** @return HasMany<AcuerdoConfidencialidad, $this> */
    public function acuerdos(): HasMany
    {
        return $this->hasMany(AcuerdoConfidencialidad::class)->orderByDesc('fecha_firma');
    }

    /** @return HasMany<PasoPersona, $this> */
    public function pasos(): HasMany
    {
        return $this->hasMany(PasoPersona::class)->orderBy('tipo')->orderBy('orden');
    }

    /**
     * En plantilla: **se deriva, no se guarda**.
     *
     * Mismo criterio que `vigente` en el análisis del contexto y que el ámbito de
     * una cuestión del DAFO. Con una columna `activa` al lado de `fecha_baja`,
     * reincorporar a alguien sería cambiar un campo y no acordarse de dos.
     */
    public function estaActiva(): bool
    {
        return $this->fecha_baja === null;
    }

    /** El acuerdo de confidencialidad vigente, si lo hay. */
    public function acuerdoVigente(): ?AcuerdoConfidencialidad
    {
        return $this->acuerdos
            ->first(static fn (AcuerdoConfidencialidad $acuerdo): bool => $acuerdo->estaVigente());
    }

    /**
     * Si la checklist de salida está terminada.
     *
     * **Es la pregunta que el auditor hace de verdad** —un acceso que nadie revocó
     * es el hallazgo clásico—, y por eso se mira sólo en quien ya no está: una
     * checklist de baja sin empezar en alguien que sigue trabajando no es una
     * laguna, es que todavía no toca.
     */
    public function esperaCierreDeBaja(): bool
    {
        if ($this->estaActiva()) {
            return false;
        }

        return $this->pasos
            ->where('tipo', TipoPasoPersona::Baja)
            ->contains(static fn (PasoPersona $paso): bool => $paso->hecho_en === null);
    }

    /**
     * Las que siguen en plantilla.
     *
     * Es el scope sobre el que se cuenta todo lo demás, igual que el cumplimiento
     * se cuenta sobre lo exigible: pedirle formación al año a quien se fue en marzo
     * pone un techo que la organización no puede alcanzar.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->whereNull('personas.fecha_baja');
    }

    /**
     * Activas sin ninguna asistencia en los últimos doce meses.
     *
     * Es `mp.per.3` y `mp.per.4` sin hacer, y el numerador del indicador de
     * personal formado sale de restar esto del total de activas — no de una
     * segunda consulta con la condición contraria, que sería la misma regla
     * escrita dos veces.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinFormacionReciente(Builder $query, ?Carbon $hoy = null): void
    {
        $desde = ($hoy ?? Carbon::today())->copy()->subMonths(self::MESES_DE_VIGENCIA_FORMATIVA);

        $query->activas()->whereDoesntHave('asistencias', static function (Builder $asistencias) use ($desde): void {
            /** @var Builder<Asistencia> $asistencias */
            $asistencias->where('asistio', true)
                ->whereHas('accionFormativa', static function (Builder $acciones) use ($desde): void {
                    /** @var Builder<AccionFormativa> $acciones */
                    $acciones->whereDate('fecha', '>=', $desde);
                });
        });
    }

    /**
     * Activas sin ningún acuerdo de confidencialidad vigente.
     *
     * `mp.per.2`: los deberes y obligaciones tienen que constar por escrito, y sin
     * papel firmado la medida está declarada y no probada.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinAcuerdoVigente(Builder $query, ?Carbon $hoy = null): void
    {
        $dia = $hoy ?? Carbon::today();

        $query->activas()->whereDoesntHave('acuerdos', static function (Builder $acuerdos) use ($dia): void {
            /** @var Builder<AcuerdoConfidencialidad> $acuerdos */
            $acuerdos->where(static function (Builder $vigencia) use ($dia): void {
                $vigencia->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $dia);
            });
        });
    }

    /**
     * Las que se fueron con la checklist de salida a medias.
     *
     * Ver `esperaCierreDeBaja()`: es el hallazgo clásico, y el hermano de
     * `Activo::esperaBorradoSeguro()` — la herramienta no corrige el dato, lo pone
     * delante.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeConBajaSinCerrar(Builder $query): void
    {
        $query->whereNotNull('personas.fecha_baja')
            ->whereHas('pasos', static function (Builder $pasos): void {
                /** @var Builder<PasoPersona> $pasos */
                $pasos->where('tipo', TipoPasoPersona::Baja->value)->whereNull('hecho_en');
            });
    }

    /**
     * `scopeBindings()` deduce la relación pluralizando el nombre del parámetro
     * **en inglés** —`designacion` → `designacions`— y aquí el dominio se nombra
     * en español. Sin esto, `/personas/{persona}/designaciones/{designacion}`
     * responde 500 con un «Call to undefined method» que no menciona ni la ruta ni
     * la relación, y de paso deja de acotar: el nombramiento de otra persona se
     * revocaría desde ésta.
     *
     * **Cuarta vez en el producto.** Los precedentes son
     * `Documento::resolveChildRouteBinding()`, `Indicador` y `Objetivo`, y las
     * tres las cazó un test de aislamiento y no una revisión. `acuerdo` y `paso`
     * no hacen falta: su plural inglés coincide con el español, que es justo lo
     * que hace este fallo difícil de ver leyendo las rutas.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $campo
     * @return Model|null
     */
    public function resolveChildRouteBinding($childType, $value, $campo)
    {
        if ($childType === 'designacion') {
            return $this->designaciones()->where($campo ?? 'designaciones_rol.id', $value)->first();
        }

        // `asignacion` → `asignacions` en inglés, que no es la tabla. Quinta vez
        // en el producto que este plural hay que escribirlo a mano.
        if ($childType === 'asignacion') {
            return $this->asignaciones()->where($campo ?? 'asignaciones_puesto.id', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $campo);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date',
            'fecha_baja' => 'date',
            'fecha_nacimiento' => 'date',
        ];
    }

    protected static function newFactory(): PersonaFactory
    {
        return PersonaFactory::new();
    }
}
