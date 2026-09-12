<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentoCuerpoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El documento entero, tal y como se va a entregar.
 *
 * `cuerpo` es lo que se imprime. `generado` es la línea base: lo que produjo
 * Statera la última vez que se materializó. La diferencia entre los dos es lo
 * que este documento tiene que ser capaz de declarar, porque un documento que se
 * puede editar entero y no dice que se ha editado es peor que uno que no se
 * puede editar.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $documento_id
 * @property array<string, mixed> $cuerpo
 * @property array<string, mixed> $generado
 * @property CarbonImmutable $generado_en
 * @property CarbonImmutable|null $editado_en
 * @property int|null $editado_por_id
 */
class DocumentoCuerpo extends Model
{
    /** @use HasFactory<DocumentoCuerpoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'documento_cuerpos';

    protected $fillable = [
        'organizacion_id',
        'documento_id',
        'cuerpo',
        'generado',
        'generado_en',
        'editado_en',
        'editado_por_id',
    ];

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editado_por_id');
    }

    /**
     * Si alguien ha tocado el documento después de generarse.
     *
     * De esto dependen la frase de la portada y la declaración que se añade a
     * las limitaciones. No es una propiedad de presentación: es lo que el
     * documento entregado afirma sobre sí mismo.
     */
    public function estaEditado(): bool
    {
        return $this->editado_en !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cuerpo' => 'array',
            'generado' => 'array',
            'generado_en' => 'immutable_datetime',
            'editado_en' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): DocumentoCuerpoFactory
    {
        return DocumentoCuerpoFactory::new();
    }
}
