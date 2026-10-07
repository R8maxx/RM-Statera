<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Models;

use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use Database\Factories\OrganizacionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * La raíz del tenant.
 *
 * Es la única tabla de datos propios sin `organizacion_id`, porque es la
 * organización. No usa el trait PerteneceAOrganizacion por lo mismo: filtrarse a
 * sí misma no significa nada.
 *
 * @property int $id
 * @property string $nombre
 * @property ?string $razon_social
 * @property ?string $cif
 * @property ?string $sector
 * @property ?string $domicilio
 * @property ?string $codigo_postal
 * @property ?string $municipio
 * @property ?string $provincia
 * @property ?string $logo_ruta
 * @property ?string $simbolo_ruta
 * @property ?string $url_base_etiquetas
 * @property bool $sujeto_obligado_ens
 * @property bool $proveedor_sector_publico
 * @property bool $activa
 * @property int $reevaluacion_proveedor_alta_meses
 * @property int $reevaluacion_proveedor_media_meses
 * @property int $reevaluacion_proveedor_baja_meses
 * @property int $plazo_vulnerabilidad_critica_dias
 * @property int $plazo_vulnerabilidad_alta_dias
 * @property int $plazo_vulnerabilidad_media_dias
 * @property int $plazo_vulnerabilidad_baja_dias
 * @property ?int $retencion_personas_meses tras la baja; nulo, nadie se suprime solo (punto 36)
 * @property ?Carbon $created_at
 * @property ?int $plan_id nulo, sin plan: ni límites ni vencimiento (punto 43)
 * @property ?Carbon $suscripcion_inicia_en
 * @property ?Carbon $suscripcion_vence_en nulo, no vence
 * @property ?string $suscripcion_referencia_externa
 * @property-read ?Plan $plan
 * @property ?Carbon $soporte_hasta hasta cuándo puede entrar la plataforma como soporte (punto 44)
 * @property ?int $soporte_abierto_por
 */
class Organizacion extends Model
{
    /** @use HasFactory<OrganizacionFactory> */
    use HasFactory;

    protected $table = 'organizaciones';

    /**
     * Deja traza de los cambios de la ficha, y **sólo de los cambios**.
     *
     * No lleva `RegistraTraza` como el resto del dominio, y el motivo es de
     * orden: ese trait registra también `created`, y el alta de una organización
     * ocurre **antes de que exista contexto para ella** —`ContextoOrganizacion`
     * se establece con la fila ya creada—. La política de RLS de
     * `eventos_auditoria` rechaza entonces la inserción con «new row violates
     * row-level security policy», que es un error de privilegios que no menciona
     * ni la traza ni la organización. Lo vio la suite: 34 tests en rojo, todos
     * los que crean una organización.
     *
     * Y no se pierde nada que importe: lo que el auditor pregunta de esta tabla
     * es desde cuándo la razón social o el CIF dicen lo que dicen, y eso son
     * modificaciones. El alta la escribe a mano `AltaOrganizacion` (punto 41),
     * ya dentro del contexto de la fila recién creada.
     */
    protected static function booted(): void
    {
        static::updated(static fn (self $organizacion) => app(RegistroTraza::class)->actualizado($organizacion));
    }

    protected $fillable = [
        'nombre',
        'razon_social',
        'cif',
        'sector',
        'domicilio',
        'codigo_postal',
        'municipio',
        'provincia',
        'url_base_etiquetas',
        'sujeto_obligado_ens',
        'proveedor_sector_publico',
        'activa',
        'reevaluacion_proveedor_alta_meses',
        'reevaluacion_proveedor_media_meses',
        'reevaluacion_proveedor_baja_meses',
        'plazo_vulnerabilidad_critica_dias',
        'plazo_vulnerabilidad_alta_dias',
        'plazo_vulnerabilidad_media_dias',
        'plazo_vulnerabilidad_baja_dias',
        'retencion_personas_meses',
    ];

    /**
     * Cuántos días hay para remediar una vulnerabilidad de esta severidad, o nulo
     * si no tiene plazo (la informativa). Política de la organización: ni ISO
     * ni el ENS fijan el número.
     */
    public function diasRemediacion(Severidad $severidad): ?int
    {
        return match ($severidad) {
            Severidad::Critica => (int) $this->plazo_vulnerabilidad_critica_dias,
            Severidad::Alta => (int) $this->plazo_vulnerabilidad_alta_dias,
            Severidad::Media => (int) $this->plazo_vulnerabilidad_media_dias,
            Severidad::Baja => (int) $this->plazo_vulnerabilidad_baja_dias,
            Severidad::Informativa => null,
        };
    }

    /**
     * Cada cuántos meses se reevalúa un proveedor de esta criticidad (§ 4.9).
     *
     * Es política de la organización y no una constante: ni ISO ni el ENS fijan
     * el plazo, así que cada cliente decide el suyo en su ficha.
     */
    public function mesesReevaluacion(Criticidad $criticidad): int
    {
        return (int) match ($criticidad) {
            Criticidad::Alta => $this->reevaluacion_proveedor_alta_meses,
            Criticidad::Media => $this->reevaluacion_proveedor_media_meses,
            Criticidad::Baja => $this->reevaluacion_proveedor_baja_meses,
        };
    }

    /**
     * Con qué nombre se identifica en un documento entregable.
     *
     * La razón social manda, porque **una Declaración de Aplicabilidad la firma
     * una persona jurídica** y no una marca. `nombre` es el respaldo mientras
     * nadie haya rellenado la razón social, que es el estado de toda
     * organización que ya existía: así ningún documento cambia de texto por el
     * hecho de migrar.
     *
     * Es `?:` y no `??`: una cadena vacía guardada desde el formulario tampoco
     * es una razón social.
     */
    public function nombreLegal(): string
    {
        return $this->razon_social ?: $this->nombre;
    }

    /**
     * Por dónde pide el navegador una pieza de marca, o nulo si no hay.
     *
     * **Con sufijo de versión**, y por lo mismo que la foto de perfil: la ruta
     * es fija, así que sin él el navegador sirve de su caché el logo viejo y
     * cambiarlo no se ve hasta vaciarla — un fallo que aparece dos días después.
     * El ULID del fichero ya es distinto en cada subida, así que sirve de
     * versión tal cual.
     */
    public function urlMarca(PiezaDeMarca $pieza): ?string
    {
        $ruta = $this->getAttribute($pieza->columna());

        if (! is_string($ruta) || $ruta === '') {
            return null;
        }

        return "/organizacion/marca/{$pieza->value}?v=".pathinfo($ruta, PATHINFO_FILENAME);
    }

    /**
     * Para la raíz del tenant, su organización es ella misma.
     *
     * **No es una columna y no se persiste.** Existe porque `RegistraTraza` no
     * funcionaría sin ella: `RegistroTraza::escribir()` lee
     * `getAttribute('organizacion_id')` y **sale con un `return` en silencio**
     * si llega nulo. Esta tabla es la única de datos propios que no tiene esa
     * columna —es la organización—, así que poner el trait y quedarse ahí daría
     * una pantalla que parece dejar traza y no deja ninguna, sin que falle
     * nadie. Es la familia de fallo de `IconoTipo` y de `tonos.ts`.
     *
     * No ensucia el registro de cambios: `organizacion_id` está en
     * `RegistroTraza::IGNORADOS`.
     *
     * @return Attribute<int, never>
     */
    protected function organizacionId(): Attribute
    {
        return Attribute::get(fn (): int => $this->id);
    }

    /**
     * El plan contratado, o nulo si no hay (punto 43).
     *
     * **El plan y las fechas no están en `$fillable`**: sólo los cambia la
     * plataforma, con `CambiarSuscripcion`, y nunca la ficha del cliente.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Si la plataforma puede entrar ahora como soporte (punto 44).
     *
     * Es una fecha y no un interruptor: la ventana se cierra sola cuando pasa,
     * aunque nadie se acuerde de cerrarla.
     */
    public function soporteAbierto(): bool
    {
        return $this->soporte_hasta !== null && $this->soporte_hasta->isFuture();
    }

    /** En qué punto está su suscripción; se deriva, no se guarda. */
    public function estadoSuscripcion(): EstadoSuscripcion
    {
        return EstadoSuscripcion::de($this);
    }

    /** @return HasMany<Sistema, $this> */
    public function sistemas(): HasMany
    {
        return $this->hasMany(Sistema::class);
    }

    /**
     * Si la ficha tiene lo que se imprime en un documento entregable (punto 42).
     *
     * Razón social, CIF y domicilio: lo que identifica a la persona jurídica que
     * firma la Declaración de Aplicabilidad. Una organización recién dada de alta
     * desde la plataforma nace sólo con su nombre, y el primer paso del cliente
     * es decir quién es. Las dos banderas del ENS no cuentan: son booleanos con
     * valor por defecto, y no hay forma de saber si alguien las contestó.
     */
    public function fichaCompleta(): bool
    {
        return filled($this->razon_social) && filled($this->cif) && filled($this->domicilio);
    }

    /**
     * El ENS aplica por obligación legal o porque se hereda del cliente público.
     */
    public function leAplicaElEns(): bool
    {
        return $this->sujeto_obligado_ens || $this->proveedor_sector_publico;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sujeto_obligado_ens' => 'boolean',
            'proveedor_sector_publico' => 'boolean',
            'activa' => 'boolean',
            'reevaluacion_proveedor_alta_meses' => 'integer',
            'reevaluacion_proveedor_media_meses' => 'integer',
            'reevaluacion_proveedor_baja_meses' => 'integer',
            'plazo_vulnerabilidad_critica_dias' => 'integer',
            'plazo_vulnerabilidad_alta_dias' => 'integer',
            'plazo_vulnerabilidad_media_dias' => 'integer',
            'plazo_vulnerabilidad_baja_dias' => 'integer',
            'retencion_personas_meses' => 'integer',
            'suscripcion_inicia_en' => 'datetime',
            'suscripcion_vence_en' => 'datetime',
            'soporte_hasta' => 'datetime',
        ];
    }

    protected static function newFactory(): OrganizacionFactory
    {
        return OrganizacionFactory::new();
    }
}
