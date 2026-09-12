<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * La serie documental: «la SoA del SGSI», no una entrega concreta de ella.
 *
 * Lo que se le entrega al auditor son las **versiones**; esto es el registro que
 * las agrupa y les da código, título, clasificación y responsable. La separación
 * existe porque una fila no puede sostener un histórico, y sin histórico la
 * pregunta que importa —«enséñame la SoA que me entregaste en marzo»— no tiene
 * respuesta.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property ?int $sistema_id
 * @property string $codigo
 * @property string $titulo
 * @property TipoDocumento $tipo
 * @property ClasificacionDocumental $clasificacion
 * @property ?int $responsable_id
 * @property ?string $notas
 */
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'documentos';

    protected $fillable = [
        'organizacion_id',
        'sistema_id',
        'codigo',
        'titulo',
        'tipo',
        'clasificacion',
        'responsable_id',
        'notas',
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

    /** @return HasMany<DocumentoVersion, $this> */
    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class);
    }

    /**
     * Los textos que la organización redactó antes de que el documento entero
     * fuera editable.
     *
     * **Ya no se escriben desde ninguna pantalla.** Quedan como la fuente de la
     * que se estrena el cuerpo de un documento que nunca haya pasado por el
     * editor: `ResolverCuerpo::crear()` los hereda a través de
     * `ContenidoDocumento::$textos`. En cuanto ese cuerpo existe, mandan los
     * nodos y esta tabla no vuelve a leerse.
     *
     * Retirarla exige antes materializar el cuerpo de todos los documentos que
     * no lo tengan; hasta entonces se queda, porque borrarla sin eso borraría
     * texto escrito.
     *
     * @return HasMany<DocumentoSeccion, $this>
     */
    public function secciones(): HasMany
    {
        return $this->hasMany(DocumentoSeccion::class);
    }

    /**
     * El cuerpo editable: lo que realmente se imprime.
     *
     * @return HasOne<DocumentoCuerpo, $this>
     */
    public function cuerpo(): HasOne
    {
        return $this->hasOne(DocumentoCuerpo::class);
    }

    /**
     * Las entregadas, de la más reciente a la más antigua.
     *
     * @return HasMany<DocumentoVersion, $this>
     */
    public function versionesEmitidas(): HasMany
    {
        return $this->versiones()->whereNotNull('numero')->orderByDesc('numero');
    }

    /**
     * El borrador vivo, si lo hay. Hay como mucho uno: lo garantiza el índice
     * único parcial `documento_versiones_borrador_unico`.
     *
     * @return HasOne<DocumentoVersion, $this>
     */
    public function borrador(): HasOne
    {
        return $this->hasOne(DocumentoVersion::class)->whereNull('numero');
    }

    /** @return HasOne<DocumentoVersion, $this> */
    public function ultimaVersionEmitida(): HasOne
    {
        return $this->hasOne(DocumentoVersion::class)->whereNotNull('numero')->orderByDesc('numero');
    }

    /**
     * El número que le tocaría a la siguiente entrega.
     *
     * Se calcula consultando, no guardando un contador: un contador y las filas
     * pueden divergir, y aquí la numeración es lo que el auditor cita.
     */
    public function siguienteNumero(): int
    {
        return (int) $this->versiones()->whereNotNull('numero')->max('numero') + 1;
    }

    /**
     * Resuelve `/documentos/{documento}/versiones/{version}` acotando la versión
     * a su documento.
     *
     * Hay que escribirlo porque `scopeBindings()` deduce la relación
     * pluralizando el nombre del parámetro **en inglés** —`version` → `versions`—
     * y aquí el dominio se nombra en español. Sin esto la ruta responde 500 con
     * un «Call to undefined method», que es un error que no dice nada de la
     * causa. Lo fija `tests/Feature/Documentos/AislamientoTest.php`.
     *
     * @param  string|null  $campo
     * @return Model|null
     */
    public function resolveChildRouteBinding($childType, $value, $campo)
    {
        if ($childType === 'version') {
            return $this->versiones()->where($campo ?? 'id', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $campo);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'clasificacion' => ClasificacionDocumental::class,
        ];
    }

    protected static function newFactory(): DocumentoFactory
    {
        return DocumentoFactory::new();
    }
}
