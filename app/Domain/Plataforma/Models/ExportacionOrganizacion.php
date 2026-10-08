<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una exportación de todos los datos de un cliente (punto 56).
 *
 * @property int $id
 * @property int $organizacion_afectada_id
 * @property ?int $solicitada_por
 * @property EstadoExportacion $estado
 * @property ?string $ruta
 * @property ?int $tamano
 * @property ?string $huella
 * @property ?string $error
 * @property Carbon $solicitada_en
 * @property ?Carbon $generada_en
 * @property ?Carbon $caduca_en
 * @property-read ?User $solicitante
 */
class ExportacionOrganizacion extends Model
{
    /** Lo que dura un fichero de exportación antes de borrarse. */
    public const DIAS_DE_VALIDEZ = 7;

    protected $table = 'exportaciones_organizacion';

    public $timestamps = false;

    protected $fillable = [
        'organizacion_afectada_id',
        'solicitada_por',
        'estado',
        'ruta',
        'tamano',
        'huella',
        'error',
        'solicitada_en',
        'generada_en',
        'caduca_en',
    ];

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoExportacion::class,
            'solicitada_en' => 'datetime',
            'generada_en' => 'datetime',
            'caduca_en' => 'datetime',
        ];
    }
}
