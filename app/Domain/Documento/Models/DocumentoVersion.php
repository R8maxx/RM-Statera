<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\DocumentoVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una entrega concreta: el PDF que alguien tuvo delante.
 *
 * **Emitida es inmutable.** Con `numero` puesto, la fila no se vuelve a tocar —y
 * eso no lo garantiza esta clase, lo garantiza un trigger de PostgreSQL—, porque
 * la regla que sostiene todo el módulo es que el PDF entregado se almacena y no
 * se regenera: si se regenerase seis meses después el contenido habría cambiado
 * y no habría forma de demostrar qué se firmó.
 *
 * **Borrador es `numero IS NULL`**, hay uno como mucho por documento y se
 * sobreescribe tantas veces como haga falta. Es la única fila que se edita.
 *
 * La `instantanea` no es redundante con el PDF: un PDF no se puede consultar. Sin
 * ella no se puede contestar «¿qué cambió entre la v3 y la v4?» ni demostrar que
 * el fichero corresponde a lo que había en la base ese día.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $documento_id
 * @property ?int $numero
 * @property EstadoGeneracion $estado_generacion
 * @property ?string $disco
 * @property ?string $ruta
 * @property ?string $nombre_fichero
 * @property ?string $mime
 * @property ?int $tamano
 * @property ?string $hash_sha256
 * @property array<string, mixed> $instantanea
 * @property array<string, mixed> $parametros
 * @property ?int $total_requisitos
 * @property ?int $total_excluidos
 * @property ?int $total_implantados
 * @property ?string $motivo
 * @property ?string $error
 * @property ?int $generada_por_id
 * @property ?Carbon $emitida_en
 * @property EstadoDocumental $estado
 * @property ?int $aprobada_por_id
 * @property ?Carbon $aprobada_en
 * @property ?string $nota_aprobacion
 * @property ?string $motivo_rechazo
 * @property ?Carbon $fecha_proxima_revision
 * @property ?Carbon $obsoleta_en
 * @property Carbon $created_at
 */
class DocumentoVersion extends Model
{
    /** @use HasFactory<DocumentoVersionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'documento_versiones';

    protected $fillable = [
        'organizacion_id',
        'documento_id',
        'numero',
        'estado_generacion',
        'disco',
        'ruta',
        'nombre_fichero',
        'mime',
        'tamano',
        'hash_sha256',
        'instantanea',
        'parametros',
        'total_requisitos',
        'total_excluidos',
        'total_implantados',
        'motivo',
        'error',
        'generada_por_id',
        'emitida_en',
        'estado',
        'aprobada_por_id',
        'aprobada_en',
        'nota_aprobacion',
        'motivo_rechazo',
        'fecha_proxima_revision',
        'obsoleta_en',
    ];

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por_id');
    }

    /**
     * Quien firmó. Es lo que el auditor busca, y va impreso en la portada.
     *
     * @return BelongsTo<User, $this>
     */
    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por_id');
    }

    /**
     * Quién ha acusado recibo de esta versión.
     *
     * Del acuse responde la VERSIÓN y no el documento: quien leyó la v3 no ha
     * leído la v4, y dar por buena la lectura de la anterior es exactamente el
     * fallo que la cláusula 7.3 existe para evitar.
     *
     * @return HasMany<DocumentoLectura, $this>
     */
    public function lecturas(): HasMany
    {
        return $this->hasMany(DocumentoLectura::class);
    }

    public function esBorrador(): bool
    {
        return $this->numero === null;
    }

    public function estaEmitida(): bool
    {
        return $this->numero !== null;
    }

    /** Si tiene fichero descargable detrás. */
    public function tieneFichero(): bool
    {
        return $this->estado_generacion === EstadoGeneracion::Generada && $this->ruta !== null;
    }

    public function estaAprobada(): bool
    {
        return $this->estado === EstadoDocumental::Aprobado;
    }

    /**
     * Si la dirección ya ha firmado, aunque la fila todavía no tenga número.
     *
     * Es el hueco transitorio del flujo: firmar escribe la aprobación y **manda
     * regenerar**, porque el PDF tiene que salir con ella en portada, y el
     * número se asigna al terminar esa generación. Entre las dos cosas la fila
     * está firmada y sin numerar.
     */
    public function tieneFirma(): bool
    {
        return $this->aprobada_en !== null;
    }

    /**
     * Si se puede cerrar la aprobación: hay firma, hay PDF y falta numerar.
     *
     * Que el borrador esté generado es una regla de estado y vive aquí, no en el
     * `FormRequest`: vale igual para un comando de consola que para la interfaz.
     */
    public function esEmisible(): bool
    {
        return $this->esBorrador()
            && $this->tieneFichero()
            && $this->tieneFirma()
            && $this->estado === EstadoDocumental::EnRevision;
    }

    /** Cómo se nombra en la interfaz y en el nombre del fichero descargado. */
    public function etiqueta(): string
    {
        return $this->numero === null ? 'Borrador' : "v{$this->numero}";
    }

    /**
     * La etiqueta que le TOCA, contando la aprobación en curso.
     *
     * El pie de página y el nombre del fichero se escriben durante la generación
     * que dispara la firma, cuando el número todavía no está puesto. Sin esto, el
     * PDF que se entrega llevaría «Borrador» impreso en las noventa páginas y se
     * llamaría `soa-sgsi-01-borrador.pdf`, que es justo el documento que el
     * auditor no puede aceptar.
     *
     * El número es el mismo que asignará `EmitirVersion` unos segundos después,
     * en el mismo trabajo; si alguien se colara en medio, el índice único de
     * `(documento_id, numero)` lo rechazaría en vez de dejar dos v4.
     */
    public function etiquetaPrevista(): string
    {
        if ($this->numero !== null) {
            return "v{$this->numero}";
        }

        if (! $this->tieneFirma()) {
            return 'Borrador';
        }

        return 'v'.$this->documento->siguienteNumero();
    }

    /** @param  Builder<$this>  $query */
    public function scopeEmitidas(Builder $query): void
    {
        $query->whereNotNull('numero');
    }

    /** @param  Builder<$this>  $query */
    public function scopeAprobadas(Builder $query): void
    {
        $query->where('estado', EstadoDocumental::Aprobado->value);
    }

    /** @param  Builder<$this>  $query */
    public function scopeEnCurso(Builder $query): void
    {
        $query->whereIn('estado_generacion', [
            EstadoGeneracion::Encolada->value,
            EstadoGeneracion::Generando->value,
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoDocumental::class,
            'estado_generacion' => EstadoGeneracion::class,
            'aprobada_en' => 'date',
            'fecha_proxima_revision' => 'date',
            'obsoleta_en' => 'date',
            'instantanea' => 'array',
            'parametros' => 'array',
            'numero' => 'integer',
            'tamano' => 'integer',
            'total_requisitos' => 'integer',
            'total_excluidos' => 'integer',
            'total_implantados' => 'integer',
            'emitida_en' => 'datetime',
        ];
    }

    protected static function newFactory(): DocumentoVersionFactory
    {
        return DocumentoVersionFactory::new();
    }
}
