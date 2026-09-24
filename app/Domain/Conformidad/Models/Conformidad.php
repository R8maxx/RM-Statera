<?php

declare(strict_types=1);

namespace App\Domain\Conformidad\Models;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Autorizacion\Concerns\AcotadoPorAlcance;
use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Enums\ViaConformidad;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Conformidad\ConformidadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una declaración (o, algún día, una certificación) de conformidad con el ENS de
 * un sistema. § 4.17.
 *
 * Une los tres pasos de categoría básica: la **autoevaluación cerrada** que la
 * respalda (`auditoria_id`), la **versión emitida** de la Declaración de
 * Conformidad (`documento_version_id`) y el **distintivo** publicado. Cada paso
 * tiene su acción en este contexto, y ninguna escribe el estado sin pasar por el
 * histórico.
 *
 * **`categoria` está congelada**: es la del día en que se inició la declaración.
 * La categoría de un sistema se deriva y no se guarda (invariante 4), pero lo que
 * se declara es un hecho fechado; si el sistema se revalora después, lo que
 * procede es una declaración nueva, y la ficha lo avisa comparando las dos.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $sistema_id
 * @property ViaConformidad $via
 * @property CategoriaEns $categoria
 * @property EstadoConformidad $estado
 * @property int $auditoria_id
 * @property ?int $documento_version_id
 * @property ?Carbon $fecha_declaracion
 * @property ?Carbon $vigente_hasta
 * @property ?string $distintivo_url
 * @property ?Carbon $distintivo_publicado_en
 * @property ?int $distintivo_evidencia_id
 * @property ?string $entidad_certificadora
 * @property ?string $numero_certificado
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Conformidad extends Model
{
    use AcotadoPorAlcance;

    /** @use HasFactory<ConformidadFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    /** Cada cuánto se renueva: bienal, como `ens.conformidad` del catálogo. */
    public const VIGENCIA_MESES = 24;

    protected $table = 'conformidades';

    protected $fillable = [
        'organizacion_id',
        'sistema_id',
        'via',
        'categoria',
        'estado',
        'auditoria_id',
        'documento_version_id',
        'fecha_declaracion',
        'vigente_hasta',
        'distintivo_url',
        'distintivo_publicado_en',
        'distintivo_evidencia_id',
        'entidad_certificadora',
        'numero_certificado',
    ];

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return BelongsTo<Auditoria, $this> */
    public function auditoria(): BelongsTo
    {
        return $this->belongsTo(Auditoria::class);
    }

    /** @return BelongsTo<DocumentoVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentoVersion::class, 'documento_version_id');
    }

    /** @return BelongsTo<Evidencia, $this> */
    public function evidenciaDistintivo(): BelongsTo
    {
        return $this->belongsTo(Evidencia::class, 'distintivo_evidencia_id');
    }

    /** @return HasMany<ConformidadTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(ConformidadTransicion::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Si la declaración ha dejado de estar en vigor por fecha.
     *
     * Sólo tiene sentido en una declarada o publicada: una en preparación todavía
     * no ha empezado a contar, y una retirada ya no cuenta por otro motivo.
     */
    public function haCaducado(): bool
    {
        return $this->estado->estaDeclarada()
            && $this->vigente_hasta !== null
            && ! $this->vigente_hasta->isAfter(Carbon::today());
    }

    /** Si respalda hoy al sistema: declarada o publicada, y sin caducar. */
    public function estaEnVigor(): bool
    {
        return $this->estado->estaDeclarada() && ! $this->haCaducado();
    }

    /**
     * El tono de la ficha y de la tabla: el del estado, salvo que haya caducado.
     *
     * Caducar es de lo poco que va mal de verdad —el sistema deja de poder
     * enseñar el distintivo— y por eso es el único rojo del módulo.
     */
    public function tono(): string
    {
        return $this->haCaducado() ? 'caducada' : $this->estado->tono();
    }

    public function estadoEtiqueta(): string
    {
        return $this->haCaducado() ? 'Caducada' : $this->estado->etiqueta();
    }

    /**
     * Las que siguen vivas: en preparación, declaradas o publicadas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVivas(Builder $query): void
    {
        $query->where('conformidades.estado', '!=', EstadoConformidad::Retirada->value);
    }

    /**
     * Las que respaldan hoy a su sistema.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeEnVigor(Builder $query): void
    {
        $query->whereIn('conformidades.estado', [
            EstadoConformidad::Declarada->value,
            EstadoConformidad::Publicada->value,
        ])->whereDate('conformidades.vigente_hasta', '>', Carbon::today());
    }

    /**
     * Declaradas o publicadas cuya vigencia ya pasó y que nadie ha renovado.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCaducadas(Builder $query): void
    {
        $query->whereIn('conformidades.estado', [
            EstadoConformidad::Declarada->value,
            EstadoConformidad::Publicada->value,
        ])->whereDate('conformidades.vigente_hasta', '<=', Carbon::today());
    }

    /**
     * Declaradas y sin distintivo: el tercer paso, pendiente.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinDistintivo(Builder $query): void
    {
        $query->where('conformidades.estado', EstadoConformidad::Declarada->value);
    }

    /** @param  Builder<$this>  $query */
    public function scopeEnPreparacion(Builder $query): void
    {
        $query->where('conformidades.estado', EstadoConformidad::EnPreparacion->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'via' => ViaConformidad::class,
            'categoria' => CategoriaEns::class,
            'estado' => EstadoConformidad::class,
            'fecha_declaracion' => 'date',
            'vigente_hasta' => 'date',
            'distintivo_publicado_en' => 'date',
        ];
    }

    protected static function newFactory(): ConformidadFactory
    {
        return ConformidadFactory::new();
    }
}
