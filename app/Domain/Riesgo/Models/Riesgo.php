<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Models;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Concerns\AcotadoPorAlcance;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Riesgo\MetodologiaVigente;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Riesgo\RiesgoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un riesgo del análisis: una amenaza sobre unos activos, con su valoración.
 *
 * **El riesgo no guarda su valoración.** La guarda `riesgo_valoraciones`, una fila
 * por evaluación, y aquí sólo está lo que no cambia al revaluar: qué amenaza, qué
 * activos, quién responde y cuándo toca volver a mirarlo. Denormalizar el nivel
 * actual a una columna sería abrir la puerta a que se desincronice del histórico
 * que lo justifica, que es el mismo argumento por el que la valoración efectiva de
 * un activo se calcula y no se almacena.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property ?int $amenaza_id
 * @property ?string $amenaza_libre
 * @property ?string $vulnerabilidad
 * @property ?int $propietario_id
 * @property ?Carbon $fecha_revision
 * @property ?string $notas
 */
class Riesgo extends Model
{
    use AcotadoPorAlcance;

    /** @use HasFactory<RiesgoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    /**
     * Un riesgo es de los activos sobre los que se da, y por ellos, de sus
     * sistemas. Mismo mecanismo que `Activo`: el scope viaja por la relación.
     *
     * @param  Builder<static>  $consulta
     * @param  list<int>  $sistemas
     */
    public function acotarAlAlcance(Builder $consulta, array $sistemas): void
    {
        $consulta->whereHas('activos');
    }

    protected $table = 'riesgos';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'amenaza_id',
        'amenaza_libre',
        'vulnerabilidad',
        'propietario_id',
        'fecha_revision',
        'notas',
    ];

    /** @return BelongsTo<Amenaza, $this> */
    public function amenaza(): BelongsTo
    {
        return $this->belongsTo(Amenaza::class);
    }

    /** El «risk owner» de ISO 6.1.3 f): quien responde de la decisión. */
    /** @return BelongsTo<User, $this> */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    /**
     * Sobre qué activos pesa. N:M, y no una clave: «robo de un portátil» es UN
     * riesgo sobre treinta portátiles.
     *
     * @return BelongsToMany<Activo, $this>
     */
    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Activo::class, 'activo_riesgo')
            ->withPivot(['vinculado_por_id', 'created_at']);
    }

    /**
     * Las salvaguardas: los controles que se apoyan contra este riesgo.
     *
     * Apuntan a `implantaciones` y no a `requisitos`, que es la diferencia entre
     * «el ENS pide cifrado» y «nosotros lo tenemos implantado en este sistema». Y
     * es lo que hace que un control valga a la vez de salvaguarda de un riesgo y
     * de prueba de cumplimiento sin registrarlo dos veces.
     *
     * @return BelongsToMany<Implantacion, $this>
     */
    public function salvaguardas(): BelongsToMany
    {
        return $this->belongsToMany(Implantacion::class, 'riesgo_implantacion')
            ->withPivot(['nota', 'vinculada_por_id', 'created_at']);
    }

    /** @return HasMany<RiesgoValoracion, $this> */
    public function valoraciones(): HasMany
    {
        return $this->hasMany(RiesgoValoracion::class)->orderByDesc('valorada_en')->orderByDesc('id');
    }

    /**
     * La que cuenta hoy. Es una y sólo una, garantizado por índice único parcial.
     *
     * @return HasOne<RiesgoValoracion, $this>
     */
    public function valoracionVigente(): HasOne
    {
        return $this->hasOne(RiesgoValoracion::class)->where('vigente', true);
    }

    /** Cómo se nombra la amenaza, venga del catálogo o de texto libre. */
    public function nombreAmenaza(): string
    {
        return $this->amenaza?->etiqueta() ?? (string) $this->amenaza_libre;
    }

    public function revisionVencida(): bool
    {
        return $this->fecha_revision !== null && $this->fecha_revision->isBefore(Carbon::today());
    }

    /**
     * El residual declarado baja del intrínseco y ninguna salvaguarda está
     * implantada.
     *
     * **Es el hallazgo que un auditor busca de verdad**: «me dices que el riesgo
     * es bajo porque tienes salvaguardas, y las salvaguardas están sin empezar».
     * La herramienta no sobrescribe la declaración —el residual lo decide una
     * persona y lo aprueba el propietario del riesgo—, pero sí señala la
     * contradicción, exactamente como `Activo::esperaBorradoSeguro()` señala un
     * equipo retirado sin constancia de borrado.
     */
    public function residualSinRespaldo(): bool
    {
        $vigente = $this->valoracionVigente;

        if ($vigente === null || ! $vigente->rebajaElRiesgo()) {
            return false;
        }

        return ! $this->salvaguardas()
            ->where('implantaciones.estado', EstadoImplantacion::Implantado->value)
            ->exists();
    }

    /**
     * La exposición de hoy por encima del apetito declarado.
     *
     * Se mide sobre el residual cuando existe y sobre el intrínseco cuando no:
     * lo que interesa es lo que queda **después** de tratar, no lo que había al
     * empezar.
     *
     * **El umbral se resuelve aquí dentro cuando no se pasa, y eso es a propósito
     * aunque un scope que pide un servicio no sea bonito.** El indicador del panel
     * y el filtro de la tabla invocan los scopes por nombre y sin argumentos
     * —`$consulta->{$scope}()`, `Filtro::porScope()`—, así que un scope con
     * parámetro obligatorio obligaría a escribir la condición una segunda vez para
     * el filtro. Y esa es exactamente la duplicación que deja el panel diciendo 12
     * y la tabla enseñando 9.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSobreUmbral(Builder $query, ?int $umbral = null): void
    {
        $umbral ??= app(MetodologiaVigente::class)->para()->umbralAceptacion;

        $query->whereHas(
            'valoracionVigente',
            fn (Builder $valoracion) => $valoracion->whereRaw('COALESCE(riesgo_residual, riesgo_intrinseco) >= ?', [$umbral]),
        );
    }

    /**
     * Valorado pero sin firmar.
     *
     * No incluye los que no se han valorado todavía: «nadie lo ha medido» y
     * «está medido y nadie lo ha aprobado» son dos faltas distintas y se
     * arreglan de formas distintas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinAceptar(Builder $query): void
    {
        $query->whereHas(
            'valoracionVigente',
            fn (Builder $valoracion) => $valoracion->whereNull('aceptada_en'),
        );
    }

    /** @param Builder<$this> $query */
    public function scopeSinValorar(Builder $query): void
    {
        $query->whereDoesntHave('valoracionVigente');
    }

    /**
     * La misma condición que `residualSinRespaldo()`, en SQL.
     *
     * Escrita dos veces por necesidad —una para una fila, otra para el conjunto—
     * y con un test que las compara, porque el día que diverjan el panel dirá 12
     * y la tabla enseñará 9 y nadie volverá a fiarse del panel.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeResidualSinRespaldo(Builder $query): void
    {
        $query
            ->whereHas('valoracionVigente', fn (Builder $valoracion) => $valoracion
                ->whereNotNull('riesgo_residual')
                ->whereColumn('riesgo_residual', '<', 'riesgo_intrinseco'))
            ->whereDoesntHave('salvaguardas', fn (Builder $salvaguarda) => $salvaguarda
                ->where('implantaciones.estado', EstadoImplantacion::Implantado->value));
    }

    /** @param Builder<$this> $query */
    public function scopeRevisionVencida(Builder $query): void
    {
        $query->whereNotNull('fecha_revision')->whereDate('fecha_revision', '<', Carbon::today());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_revision' => 'date',
        ];
    }

    protected static function newFactory(): RiesgoFactory
    {
        return RiesgoFactory::new();
    }
}
