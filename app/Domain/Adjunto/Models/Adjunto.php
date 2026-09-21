<?php

declare(strict_types=1);

namespace App\Domain\Adjunto\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Adjunto\AdjuntoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un documento que cuelga de un registro.
 *
 * **No es una evidencia.** Una evidencia prueba un requisito y por eso lleva
 * caducidad, periodicidad y responsable; esto documenta un registro —el título
 * de un curso, el contrato firmado— y no tiene nada de eso. La frontera está
 * escrita en la migración.
 *
 * Los anfitriones van por **pivote con nombre y no por relación polimórfica**:
 * no hay un solo `morphTo` en el repositorio, y a cambio las claves foráneas son
 * reales.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $titulo
 * @property ?string $nota
 * @property string $disco
 * @property string $ruta
 * @property string $nombre_fichero
 * @property string $mime
 * @property int $tamano
 * @property string $hash_sha256
 * @property ?int $subido_por_id
 */
class Adjunto extends Model
{
    /** @use HasFactory<AdjuntoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $fillable = [
        'organizacion_id',
        'titulo',
        'nota',
        'disco',
        'ruta',
        'nombre_fichero',
        'mime',
        'tamano',
        'hash_sha256',
        'subido_por_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_id');
    }

    /** @return BelongsToMany<Persona, $this> */
    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'persona_adjunto');
    }

    /** @return BelongsToMany<AccionFormativa, $this> */
    public function accionesFormativas(): BelongsToMany
    {
        return $this->belongsToMany(AccionFormativa::class, 'accion_formativa_adjunto');
    }

    protected static function newFactory(): AdjuntoFactory
    {
        return AdjuntoFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['tamano' => 'integer'];
    }
}
