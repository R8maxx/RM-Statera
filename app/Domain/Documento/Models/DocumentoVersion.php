<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Database\Factories\DocumentoVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * Si se puede emitir: hay PDF y todavía no se ha emitido.
     *
     * Que el borrador esté generado es una regla de estado y vive aquí, no en el
     * `FormRequest`: vale igual para un comando de consola que para la interfaz.
     */
    public function esEmisible(): bool
    {
        return $this->esBorrador() && $this->tieneFichero();
    }

    /** Cómo se nombra en la interfaz y en el nombre del fichero descargado. */
    public function etiqueta(): string
    {
        return $this->numero === null ? 'Borrador' : "v{$this->numero}";
    }

    /** @param  Builder<$this>  $query */
    public function scopeEmitidas(Builder $query): void
    {
        $query->whereNotNull('numero');
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
            'estado_generacion' => EstadoGeneracion::class,
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
