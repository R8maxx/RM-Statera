<?php

declare(strict_types=1);

namespace App\Domain\Obligacion\Models;

use App\Domain\Obligacion\Cadencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Obligacion\CompromisoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Algo que esta organización se ha comprometido a hacer cada tanto.
 *
 * Es a `Obligacion` lo que `Implantacion` es a `Requisito`: el catálogo dice qué
 * hay que hacer y con qué cadencia mínima, y esto es lo que una organización
 * concreta ha asumido. Puede además no salir de ningún catálogo —`obligacion_id`
 * nula—, porque alguien puede comprometerse a algo que no le exige nadie.
 *
 * **La próxima fecha se deriva y no se guarda.** Es el último `cubre_hasta` de
 * los cumplimientos y, si no hay ninguno, `computa_desde` más la cadencia. La
 * expresión está escrita **una sola vez**, en `PROXIMA`, y la leen los tres
 * scopes y el atributo: guardarla en una columna serían dos sitios que se
 * desincronizan el día que alguien borre un cumplimiento.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property ?int $obligacion_id
 * @property ?int $sistema_id
 * @property string $codigo
 * @property string $titulo
 * @property ?string $descripcion
 * @property int $periodicidad_meses
 * @property Carbon $computa_desde
 * @property ?int $responsable_id
 * @property bool $activo
 * @property ?string $notas
 * @property ?string $motivo_retirada
 */
class Compromiso extends Model
{
    /** @use HasFactory<CompromisoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    /**
     * La próxima vez que toca, en SQL.
     *
     * El `coalesce` es lo que hace que un compromiso recién asumido ya tenga
     * fecha: sin cumplimientos, la primera cae a una cadencia de `computa_desde`.
     *
     * `make_interval(months => …)` y no concatenar la cifra dentro de una cadena:
     * la columna es del propio compromiso y no hay nada que interpolar, pero un
     * `(x || ' months')::interval` invita a que el día de mañana alguien meta ahí
     * un valor de la petición.
     */
    private const PROXIMA = <<<'SQL'
        coalesce(
            (select max(c.cubre_hasta) from compromiso_cumplimientos c where c.compromiso_id = compromisos.id),
            compromisos.computa_desde + make_interval(months => compromisos.periodicidad_meses)
        )
        SQL;

    protected $table = 'compromisos';

    protected $fillable = [
        'organizacion_id',
        'obligacion_id',
        'sistema_id',
        'codigo',
        'titulo',
        'descripcion',
        'periodicidad_meses',
        'computa_desde',
        'responsable_id',
        'activo',
        'notas',
        'motivo_retirada',
    ];

    /** @return BelongsTo<Obligacion, $this> */
    public function obligacion(): BelongsTo
    {
        return $this->belongsTo(Obligacion::class);
    }

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return HasMany<CompromisoCumplimiento, $this> */
    public function cumplimientos(): HasMany
    {
        return $this->hasMany(CompromisoCumplimiento::class)->orderByDesc('fecha');
    }

    /** La expresión SQL de la próxima fecha, para ordenar y para seleccionar. */
    public static function expresionProxima(): string
    {
        return '('.self::PROXIMA.')';
    }

    /** @param Builder<$this> $query */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    /**
     * Lo que ya se pasó de fecha.
     *
     * Estrictamente anterior a hoy, igual que `Tarea::vencidas()` y
     * `Evidencia::caducadas()`: lo que vence hoy todavía no se ha pasado.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVencidos(Builder $query): void
    {
        $query->activos()->whereRaw(self::PROXIMA.' < ?', [Carbon::today()->toDateString()]);
    }

    /**
     * Lo que vence dentro de los próximos `$dias`, sin contar lo vencido.
     *
     * **Noventa días por defecto y no treinta**, que es la ventana de
     * `Tarea::porVencer()`. No es un descuido: contratar a la entidad que audita
     * o preparar la ventana del INES no se hace en un mes, y un aviso que llega
     * cuando ya no da tiempo a reaccionar es un aviso que no sirve.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePorVencer(Builder $query, int $dias = 90): void
    {
        $query->activos()->whereRaw(
            self::PROXIMA.' BETWEEN ? AND ?',
            [Carbon::today()->toDateString(), Carbon::today()->addDays($dias)->toDateString()],
        );
    }

    /** @param Builder<$this> $query */
    public function scopeProximaEntre(Builder $query, Carbon $desde, Carbon $hasta): void
    {
        $query->activos()->whereRaw(
            self::PROXIMA.' BETWEEN ? AND ?',
            [$desde->toDateString(), $hasta->toDateString()],
        );
    }

    /** @param Builder<$this> $query */
    public function scopeSinResponsable(Builder $query): void
    {
        $query->activos()->whereNull('responsable_id');
    }

    /**
     * Los que nunca se han cumplido: una promesa, no un control.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeNuncaCumplidos(Builder $query): void
    {
        $query->activos()->whereDoesntHave('cumplimientos');
    }

    public function cadencia(): Cadencia
    {
        return new Cadencia($this->periodicidad_meses);
    }

    /**
     * La próxima vez que toca, en PHP.
     *
     * Misma regla que `PROXIMA`: si las dos divergieran, la tabla y el calendario
     * dirían cosas distintas del mismo compromiso.
     *
     * **Lee la relación cuando está cargada y sólo consulta si no lo está.** Era
     * siempre un `max()` contra la base, y eso convertía la tabla en N+1 del peor
     * tipo: `ObligacionRecurso` la llama desde la columna del vencimiento y otra
     * vez desde el estado —vía `vencido()`—, así que una página de veinticinco
     * filas lanzaba cincuenta consultas con la relación ya cargada al lado.
     *
     * Quien pinte muchas filas debería además traer la fecha por SQL
     * —`expresionProxima()` como columna añadida—, que es lo que hace el recurso;
     * esto es el camino de una fila suelta.
     */
    public function proximaFecha(): Carbon
    {
        $ultimo = $this->relationLoaded('cumplimientos')
            ? $this->cumplimientos->max('cubre_hasta')
            : $this->cumplimientos()->max('cubre_hasta');

        return $ultimo === null
            ? $this->cadencia()->despuesDe($this->computa_desde)
            : Carbon::parse((string) $ultimo)->startOfDay();
    }

    public function vencido(): bool
    {
        return $this->proximaFecha()->lt(Carbon::today());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'computa_desde' => 'date',
            'periodicidad_meses' => 'integer',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): CompromisoFactory
    {
        return CompromisoFactory::new();
    }
}
