<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Documento\Enums\SeccionNarrativa;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Database\Factories\PlantillaSeccionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un hueco de la plantilla base de la organización.
 *
 * **La plantilla ES el conjunto de estas filas**; no hay tabla padre. Una fila
 * que falta no significa «vacío», significa «vale el texto de fábrica», y eso es
 * lo que permite que una organización nueva no necesite sembrado ninguno.
 *
 * Lleva traza porque cambiar el texto base afecta a **todos los documentos que
 * se creen a partir de ahora**, y ésa es exactamente la clase de decisión que un
 * auditor quiere poder rastrear.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property TipoDocumento $tipo
 * @property SeccionNarrativa $seccion
 * @property string $contenido_md
 * @property ?int $actualizado_por_id
 */
class PlantillaSeccion extends Model
{
    /** @use HasFactory<PlantillaSeccionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'documento_plantilla_secciones';

    protected $fillable = [
        'organizacion_id',
        'tipo',
        'seccion',
        'contenido_md',
        'actualizado_por_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    /** @param  Builder<$this>  $query */
    public function scopeDelTipo(Builder $query, TipoDocumento $tipo): void
    {
        $query->where('tipo', $tipo->value);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'seccion' => SeccionNarrativa::class,
        ];
    }

    protected static function newFactory(): PlantillaSeccionFactory
    {
        return PlantillaSeccionFactory::new();
    }
}
