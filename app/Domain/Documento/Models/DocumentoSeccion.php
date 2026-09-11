<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Documento\Enums\OrigenTexto;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use Database\Factories\DocumentoSeccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El texto de un hueco en UN documento concreto.
 *
 * Se materializa al crear el documento copiando lo que diga la plantilla, y a
 * partir de ahí **es un hecho del documento**: si la plantilla cambia después,
 * esto no se entera. Es deliberado y es el mismo razonamiento que hay detrás de
 * `documento_versiones.instantanea` — lo que dice un documento no puede ser el
 * resultado de un join que cambie bajo los pies.
 *
 * No lleva `tipo`: es derivable de `documentos.tipo` y duplicarlo abriría la
 * puerta a que diverjan.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $documento_id
 * @property SeccionNarrativa $seccion
 * @property string $contenido_md
 * @property OrigenTexto $origen
 */
class DocumentoSeccion extends Model
{
    /** @use HasFactory<DocumentoSeccionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'documento_secciones';

    protected $fillable = [
        'organizacion_id',
        'documento_id',
        'seccion',
        'contenido_md',
        'origen',
    ];

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function esPropio(): bool
    {
        return $this->origen === OrigenTexto::Propio;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'seccion' => SeccionNarrativa::class,
            'origen' => OrigenTexto::class,
        ];
    }

    protected static function newFactory(): DocumentoSeccionFactory
    {
        return DocumentoSeccionFactory::new();
    }
}
