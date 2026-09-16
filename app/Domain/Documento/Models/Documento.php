<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
 * @property ?int $periodicidad_revision_meses
 * @property bool $exige_acuse
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
        'periodicidad_revision_meses',
        'exige_acuse',
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
     * La versión vigente: la que está aprobada y todavía no ha sido sustituida.
     *
     * Hay como mucho una, y lo garantiza el índice único parcial
     * `documento_versiones_aprobada_vigente` — el mismo mecanismo que el del
     * borrador, y que el de la valoración vigente de un riesgo.
     *
     * No es lo mismo que `ultimaVersionEmitida()`: ésa es la última que se
     * entregó, y bajo el flujo de aprobación toda entrega está firmada, pero las
     * versiones que se emitieron antes de que el flujo existiera se archivaron
     * como obsoletas sin firmante. Un documento así tiene última versión y no
     * tiene versión vigente, y eso es lo cierto.
     *
     * @return HasOne<DocumentoVersion, $this>
     */
    public function versionAprobada(): HasOne
    {
        return $this->hasOne(DocumentoVersion::class)->where('estado', EstadoDocumental::Aprobado->value);
    }

    /** Si hay que acusar recibo de sus versiones aprobadas. */
    public function exigeAcuse(): bool
    {
        return (bool) $this->exige_acuse;
    }

    /**
     * Los que se han pasado de la fecha de revisión que ellos mismos fijaron.
     *
     * Va por `whereHas` y no por `join` por lo de siempre: un documento con
     * varias versiones saldría repetido y la paginación contaría mal.
     *
     * **Lo cuentan a la vez el indicador del panel, el filtro de la tabla y el
     * aviso diario**, y por eso la condición se escribe una sola vez aquí. Con la
     * condición duplicada, el día que cambie una el correo dirá 12 y la pantalla
     * enseñará 9, y a partir de ahí nadie se fía de ninguno de los dos.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRevisionVencida(Builder $query): void
    {
        $query->whereHas(
            'versionAprobada',
            // Estrictamente anterior a hoy: lo que vence hoy todavía no se ha
            // pasado, igual que en `Tarea::vencidas()` y en `caducadas()`.
            fn (Builder $version) => $version
                ->whereNotNull('fecha_proxima_revision')
                ->whereDate('fecha_proxima_revision', '<', Carbon::today()),
        );
    }

    /**
     * Los que están esperando una firma.
     *
     * Mira el borrador vivo y no la versión vigente: lo que está en revisión es
     * lo que todavía no se ha entregado. Un documento aprobado con una versión
     * nueva en revisión sale aquí, y es correcto — hay algo esperando a alguien.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeEnRevision(Builder $query): void
    {
        $query->whereHas(
            'versiones',
            fn (Builder $version) => $version->where('estado', EstadoDocumental::EnRevision->value),
        );
    }

    /**
     * Los que toca revisar dentro de la ventana, sin haberse pasado todavía.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePorRevisar(Builder $query, int $dias = 30): void
    {
        $query->whereHas(
            'versionAprobada',
            fn (Builder $version) => $version
                ->whereNotNull('fecha_proxima_revision')
                ->whereDate('fecha_proxima_revision', '>=', Carbon::today())
                ->whereDate('fecha_proxima_revision', '<=', Carbon::today()->addDays($dias)),
        );
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
            'periodicidad_revision_meses' => 'integer',
            'exige_acuse' => 'boolean',
        ];
    }

    protected static function newFactory(): DocumentoFactory
    {
        return DocumentoFactory::new();
    }
}
