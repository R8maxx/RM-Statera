<?php

declare(strict_types=1);

namespace App\Domain\Incidente\Models;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Concerns\AcotadoPorAlcance;
use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\PlazoNotificacion;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Incidente\IncidenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un incidente de seguridad: § 4.10 y `op.exp.7`.
 *
 * **Las cinco dimensiones son cinco columnas booleanas**, no filas ni JSONB: son
 * cinco, no van a ser seis, y son la primera pregunta de cualquier informe de
 * incidente. Mismo reparto que la valoración propia de un activo.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $codigo
 * @property string $titulo
 * @property string $descripcion
 * @property ?int $sistema_id
 * @property ClasificacionIncidente $clasificacion
 * @property PeligrosidadIncidente $peligrosidad
 * @property Carbon $fecha_deteccion
 * @property ?Carbon $fecha_inicio
 * @property bool $afecta_confidencialidad
 * @property bool $afecta_integridad
 * @property bool $afecta_disponibilidad
 * @property bool $afecta_autenticidad
 * @property bool $afecta_trazabilidad
 * @property ?string $impacto
 * @property ?string $acciones_contencion
 * @property ?string $leccion_aprendida
 * @property EstadoIncidente $estado
 * @property ?int $responsable_id
 * @property ?Carbon $fecha_cierre
 * @property bool $notificable_aepd
 * @property ?Carbon $notificado_aepd_en
 * @property bool $notificable_ccn_cert
 * @property ?Carbon $notificado_ccn_cert_en
 */
class Incidente extends Model
{
    use AcotadoPorAlcance;

    /** @use HasFactory<IncidenteFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    /**
     * Un incidente sin sistema no se ha atribuido a ninguno, y ocultárselo al
     * auditor sería esconder justo lo que está sin clasificar. Se acota lo
     * atribuido a otro sistema.
     *
     * @param  Builder<static>  $consulta
     * @param  list<int>  $sistemas
     */
    public function acotarAlAlcance(Builder $consulta, array $sistemas): void
    {
        $columna = $this->qualifyColumn('sistema_id');

        $consulta->where(fn (Builder $dentro) => $dentro->whereNull($columna)->orWhereIn($columna, $sistemas));
    }

    /**
     * Las cinco dimensiones del Anexo I, en su orden, y la columna que las
     * guarda.
     *
     * Cinco columnas booleanas y no filas ni JSONB: son cinco, no van a ser
     * seis, y se consultan y se indexan. Mismo reparto que la valoración propia
     * de un activo.
     *
     * @var array<string, string>
     */
    public const DIMENSIONES = [
        'afecta_confidencialidad' => 'Confidencialidad',
        'afecta_integridad' => 'Integridad',
        'afecta_disponibilidad' => 'Disponibilidad',
        'afecta_autenticidad' => 'Autenticidad',
        'afecta_trazabilidad' => 'Trazabilidad',
    ];

    protected $fillable = [
        'organizacion_id',
        'codigo',
        'titulo',
        'descripcion',
        'sistema_id',
        'clasificacion',
        'peligrosidad',
        'fecha_deteccion',
        'fecha_inicio',
        'afecta_confidencialidad',
        'afecta_integridad',
        'afecta_disponibilidad',
        'afecta_autenticidad',
        'afecta_trazabilidad',
        'impacto',
        'acciones_contencion',
        'leccion_aprendida',
        'estado',
        'responsable_id',
        'fecha_cierre',
        'notificable_aepd',
        'notificado_aepd_en',
        'notificable_ccn_cert',
        'notificado_ccn_cert_en',
    ];

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

    /** @return BelongsToMany<Activo, $this> */
    public function activos(): BelongsToMany
    {
        return $this->belongsToMany(Activo::class, 'incidente_activo')->withTimestamps();
    }

    /** @return HasMany<IncidenteTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(IncidenteTransicion::class)->orderBy('created_at');
    }

    /**
     * La no conformidad que lo trata, si la hay.
     *
     * **`HasOne` y no `HasMany`**, porque el índice único sobre
     * `no_conformidades.incidente_id` impone que se trate una vez: sin él,
     * «incidentes sin tratar» dependería de cuál de las dos filas se mirase.
     *
     * @return HasOne<NoConformidad, $this>
     */
    public function noConformidad(): HasOne
    {
        return $this->hasOne(NoConformidad::class);
    }

    /** El reloj de las 72 h del RGPD, o la ausencia de reloj. */
    public function plazoAepd(?Carbon $ahora = null): PlazoNotificacion
    {
        return PlazoNotificacion::aepd($this, $ahora);
    }

    public function notificacionCcnCert(): PlazoNotificacion
    {
        return PlazoNotificacion::ccnCert($this);
    }

    /**
     * Las dimensiones afectadas, en el orden del Anexo I.
     *
     * @return list<string>
     */
    public function dimensionesAfectadas(): array
    {
        $afectadas = [];

        foreach (self::DIMENSIONES as $columna => $etiqueta) {
            if ((bool) $this->getAttribute($columna)) {
                $afectadas[] = $etiqueta;
            }
        }

        return $afectadas;
    }

    /**
     * Los que siguen vivos.
     *
     * **`resuelto` cuenta como vivo**, y es la mitad del argumento del módulo: el
     * servicio está restablecido y todavía falta escribir qué se aprendió, que es
     * lo que `op.exp.7` pide. Sacarlo del recuento sería dar por bueno el paso que
     * todo el mundo se salta.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAbiertos(Builder $query): void
    {
        $query->where('incidentes.estado', '!=', EstadoIncidente::Cerrado->value);
    }

    /**
     * El único rojo del módulo: notificable a la AEPD, sin notificar y con las
     * 72 h del artículo 33.1 ya pasadas.
     *
     * La condición se escribe **una sola vez aquí** y la invocan por nombre la
     * cifra del panel y el filtro de la tabla, que es lo que garantiza que pulsar
     * el número enseñe exactamente ese número.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeFueraDePlazoAepd(Builder $query, ?Carbon $ahora = null): void
    {
        $limite = ($ahora ?? Carbon::now())->copy()->subHours(PlazoNotificacion::HORAS_AEPD);

        $query->where('incidentes.notificable_aepd', true)
            ->whereNull('incidentes.notificado_aepd_en')
            ->where('incidentes.fecha_deteccion', '<', $limite);
    }

    /**
     * Notificable a la AEPD, sin notificar y **todavía en plazo**.
     *
     * Separado del anterior a propósito: uno es un incumplimiento y el otro es
     * trabajo urgente. Colapsarlos pondría en rojo a quien lo está haciendo bien.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeEnPlazoAepd(Builder $query, ?Carbon $ahora = null): void
    {
        $limite = ($ahora ?? Carbon::now())->copy()->subHours(PlazoNotificacion::HORAS_AEPD);

        $query->where('incidentes.notificable_aepd', true)
            ->whereNull('incidentes.notificado_aepd_en')
            ->where('incidentes.fecha_deteccion', '>=', $limite);
    }

    /**
     * Cerrados sin lección aprendida.
     *
     * **Por construcción no puede haber ninguno** —lo impide
     * `incidentes_leccion_check`— y el scope existe igual, como cifra que tiene
     * que salir cero: el día que alguien relaje el `CHECK`, esto lo enseña. Mismo
     * papel que `Riesgo::residualSinRespaldo()`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCerradosSinLeccion(Builder $query): void
    {
        $query->where('incidentes.estado', EstadoIncidente::Cerrado->value)
            ->where(static function (Builder $sinLeccion): void {
                $sinLeccion->whereNull('incidentes.leccion_aprendida')
                    ->orWhereRaw('length(trim(incidentes.leccion_aprendida)) = 0');
            });
    }

    /**
     * Resueltos y sin cerrar: el servicio volvió y la lección sigue sin
     * escribirse. Es la cifra honesta del módulo, la hermana de `sinEmpezar` en
     * mejoras y de `sinVerificar` en no conformidades.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinLeccion(Builder $query): void
    {
        $query->where('incidentes.estado', EstadoIncidente::Resuelto->value);
    }

    /**
     * Sin no conformidad detrás.
     *
     * **No es una alerta**: no todo incidente abre una no conformidad, y exigirlo
     * convertiría cada correo fraudulento bloqueado en un incumplimiento del
     * sistema de gestión. Es un filtro para quien revisa.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinTratar(Builder $query): void
    {
        $query->whereDoesntHave('noConformidad');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'clasificacion' => ClasificacionIncidente::class,
            'peligrosidad' => PeligrosidadIncidente::class,
            'estado' => EstadoIncidente::class,
            'fecha_deteccion' => 'datetime',
            'fecha_inicio' => 'datetime',
            'fecha_cierre' => 'datetime',
            'notificado_aepd_en' => 'datetime',
            'notificado_ccn_cert_en' => 'datetime',
            'notificable_aepd' => 'boolean',
            'notificable_ccn_cert' => 'boolean',
            'afecta_confidencialidad' => 'boolean',
            'afecta_integridad' => 'boolean',
            'afecta_disponibilidad' => 'boolean',
            'afecta_autenticidad' => 'boolean',
            'afecta_trazabilidad' => 'boolean',
        ];
    }

    protected static function newFactory(): IncidenteFactory
    {
        return IncidenteFactory::new();
    }
}
