<?php

declare(strict_types=1);

namespace App\Domain\Documento\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Alguien dice que ha leído esta versión.
 *
 * Es el acuse de recibo que piden la cláusula 7.3 de ISO —«las personas deben ser
 * conscientes de la política»— y `org.2` del ENS. Sin él, «la política está
 * publicada» y «la política se conoce» son la misma frase, y sólo la segunda es
 * la que exige la norma.
 *
 * **Es histórico: se escribe y no se edita**, y por eso no tiene `updated_at`.
 * Tampoco se borra al desmarcar, porque no hay desmarcar: haber leído algo no se
 * deshace.
 *
 * **Cuelga de la versión y no del documento.** Quien acusó recibo de la v3 no ha
 * leído la v4, y arrastrar el acuse anterior convertiría el registro en un
 * trámite que se pasa solo — exactamente lo que la cláusula quiere evitar.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $documento_version_id
 * @property int $user_id
 * @property Carbon $acusada_en
 * @property ?Carbon $created_at
 */
class DocumentoLectura extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $table = 'documento_lecturas';

    protected $fillable = [
        'organizacion_id',
        'documento_version_id',
        'user_id',
        'acusada_en',
        'created_at',
    ];

    /** @return BelongsTo<DocumentoVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentoVersion::class, 'documento_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'acusada_en' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
