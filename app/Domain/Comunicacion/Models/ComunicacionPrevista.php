<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion\Models;

use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Obligacion\Cadencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Comunicacion\ComunicacionPrevistaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una línea del plan de comunicación: la cláusula 7.4.
 *
 * Qué se comunica (`titulo`), cuándo (`periodicidad_meses`), a quién (las partes
 * interesadas y `destinatarios_otros`), quién (`responsable_id`) y cómo
 * (`canal`). Las cinco preguntas de la norma, cada una en su columna.
 *
 * **La próxima fecha se deriva y no se guarda**, con la misma regla que
 * `Compromiso::PROXIMA`: el último `cubre_hasta` de lo emitido o, sin nada,
 * `computa_desde` más la cadencia. Guardarla en columna serían dos sitios que se
 * desincronizan el día que alguien borre una comunicación.
 *
 * Una previsión sin cadencia —«al cambiar la política»— **no vence nunca**, y es
 * una respuesta legítima: no sale en el calendario ni en el panel.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property ?string $descripcion
 * @property CanalComunicacion $canal
 * @property ?int $responsable_id
 * @property ?int $periodicidad_meses
 * @property ?Carbon $computa_desde
 * @property ?string $destinatarios_otros
 * @property ?Carbon $retirada_en
 * @property ?string $motivo_retirada
 */
class ComunicacionPrevista extends Model
{
    /** @use HasFactory<ComunicacionPrevistaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    /**
     * La próxima vez que toca, en SQL. Sólo tiene sentido con cadencia: sin ella
     * es nula, y los scopes de vencimiento la dejan fuera por eso mismo.
     */
    private const PROXIMA = <<<'SQL'
        coalesce(
            (select max(c.cubre_hasta) from comunicaciones c where c.comunicacion_prevista_id = comunicaciones_previstas.id),
            comunicaciones_previstas.computa_desde + make_interval(months => comunicaciones_previstas.periodicidad_meses)
        )
        SQL;

    protected $table = 'comunicaciones_previstas';

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'descripcion',
        'canal',
        'responsable_id',
        'periodicidad_meses',
        'computa_desde',
        'destinatarios_otros',
        'retirada_en',
        'motivo_retirada',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * A quién, cuando es una parte interesada registrada.
     *
     * @return BelongsToMany<ParteInteresada, $this>
     */
    public function partesInteresadas(): BelongsToMany
    {
        return $this->belongsToMany(ParteInteresada::class, 'comunicacion_prevista_parte_interesada')
            ->withPivot('organizacion_id');
    }

    /** @return HasMany<Comunicacion, $this> */
    public function comunicaciones(): HasMany
    {
        return $this->hasMany(Comunicacion::class)->orderByDesc('fecha');
    }

    /** La expresión SQL de la próxima fecha, para ordenar y para seleccionar. */
    public static function expresionProxima(): string
    {
        return '('.self::PROXIMA.')';
    }

    public function estaRetirada(): bool
    {
        return $this->retirada_en !== null;
    }

    public function cadencia(): ?Cadencia
    {
        return $this->periodicidad_meses === null ? null : new Cadencia($this->periodicidad_meses);
    }

    /**
     * La próxima vez que toca, en PHP. Misma regla que `PROXIMA`.
     *
     * Nula si no tiene cadencia. Lee la relación si está cargada, como
     * `Compromiso::proximaFecha()`, para no convertir una tabla en N+1.
     */
    public function proximaFecha(): ?Carbon
    {
        $cadencia = $this->cadencia();

        if ($cadencia === null || $this->computa_desde === null) {
            return null;
        }

        $ultimo = $this->relationLoaded('comunicaciones')
            ? $this->comunicaciones->max('cubre_hasta')
            : $this->comunicaciones()->max('cubre_hasta');

        return $ultimo === null
            ? $cadencia->despuesDe($this->computa_desde)
            : Carbon::parse((string) $ultimo)->startOfDay();
    }

    public function vencida(): bool
    {
        return ! $this->estaRetirada() && ($this->proximaFecha()?->lt(Carbon::today()) ?? false);
    }

    /** @param Builder<$this> $query */
    public function scopeActivas(Builder $query): void
    {
        $query->whereNull('comunicaciones_previstas.retirada_en');
    }

    /** @param Builder<$this> $query */
    public function scopePeriodicas(Builder $query): void
    {
        $query->activas()->whereNotNull('comunicaciones_previstas.periodicidad_meses');
    }

    /**
     * Las periódicas a las que ya se les pasó la fecha. Estrictamente anterior a
     * hoy, como `Compromiso::vencidos()`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVencidas(Builder $query): void
    {
        $query->periodicas()->whereRaw(self::PROXIMA.' < ?', [Carbon::today()->toDateString()]);
    }

    /**
     * Las que tocan dentro de los próximos `$dias`, sin contar lo vencido.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePorVencer(Builder $query, int $dias = 30): void
    {
        $query->proximaEntre(Carbon::today(), Carbon::today()->addDays($dias));
    }

    /** @param Builder<$this> $query */
    public function scopeProximaEntre(Builder $query, Carbon $desde, Carbon $hasta): void
    {
        $query->periodicas()->whereRaw(
            self::PROXIMA.' BETWEEN ? AND ?',
            [$desde->toDateString(), $hasta->toDateString()],
        );
    }

    /** @param Builder<$this> $query */
    public function scopeSinResponsable(Builder $query): void
    {
        $query->activas()->whereNull('comunicaciones_previstas.responsable_id');
    }

    /**
     * Sin a quién: ni partes interesadas ni otros destinatarios.
     *
     * Una comunicación sin destinatario es un deseo, no un plan: la 7.4 pide
     * decir a quién.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinDestinatarios(Builder $query): void
    {
        $query->activas()
            ->whereDoesntHave('partesInteresadas')
            ->where(static function (Builder $sinOtros): void {
                $sinOtros->whereNull('comunicaciones_previstas.destinatarios_otros')
                    ->orWhereRaw("trim(comunicaciones_previstas.destinatarios_otros) = ''");
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'canal' => CanalComunicacion::class,
            'periodicidad_meses' => 'integer',
            'computa_desde' => 'date',
            'retirada_en' => 'date',
        ];
    }

    protected static function newFactory(): ComunicacionPrevistaFactory
    {
        return ComunicacionPrevistaFactory::new();
    }
}
