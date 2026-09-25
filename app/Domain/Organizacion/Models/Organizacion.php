<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Models;

use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\RegistroTraza;
use Database\Factories\OrganizacionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * modificaciones. Dar de alta un tenant es panel de superadministración, que
     * está fuera de alcance.
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
    ];

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

    /** @return HasMany<Sistema, $this> */
    public function sistemas(): HasMany
    {
        return $this->hasMany(Sistema::class);
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
        ];
    }

    protected static function newFactory(): OrganizacionFactory
    {
        return OrganizacionFactory::new();
    }
}
