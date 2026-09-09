<?php

declare(strict_types=1);

namespace App\Domain\Evidencia\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Database\Factories\EvidenciaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Una prueba: lo que se le enseña al auditor cuando pregunta «demuéstramelo».
 *
 * Se registra **una vez** y cuenta para todos los marcos donde aplique
 * (invariante 6). Esa relación N:M con `implantaciones` es el problema que el
 * producto resuelve.
 *
 * El fichero no está aquí: está en el disco `evidencias`, y de él se guardan la
 * ruta y el **SHA-256**. La huella es lo que permite demostrar que el fichero
 * que se enseña hoy es el que se obtuvo aquel día; sin ella, la evidencia prueba
 * lo que alguien diga que prueba.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $titulo
 * @property TipoEvidencia $tipo
 * @property ?string $descripcion
 * @property ?string $disco
 * @property ?string $ruta
 * @property ?string $nombre_fichero
 * @property ?string $mime
 * @property ?int $tamano
 * @property ?string $hash_sha256
 * @property ?string $url_externa
 * @property Carbon $fecha_obtencion
 * @property ?Carbon $fecha_caducidad
 * @property ?PeriodicidadRenovacion $periodicidad_renovacion
 * @property ?int $responsable_id
 */
class Evidencia extends Model
{
    /** @use HasFactory<EvidenciaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'evidencias';

    protected $fillable = [
        'organizacion_id',
        'titulo',
        'tipo',
        'descripcion',
        'disco',
        'ruta',
        'nombre_fichero',
        'mime',
        'tamano',
        'hash_sha256',
        'url_externa',
        'fecha_obtencion',
        'fecha_caducidad',
        'periodicidad_renovacion',
        'responsable_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Los requisitos que esta evidencia prueba, de cualquier marco.
     *
     * @return BelongsToMany<Implantacion, $this>
     */
    public function implantaciones(): BelongsToMany
    {
        return $this->belongsToMany(Implantacion::class, 'evidencia_implantacion')
            ->withPivot(['nota', 'vinculada_por_id', 'created_at']);
    }

    public function esFichero(): bool
    {
        return $this->ruta !== null;
    }

    /**
     * Caducada es distinto de sin caducidad.
     *
     * Una evidencia sin fecha de caducidad no está vigente para siempre: es que
     * nadie ha dicho cuándo deja de valer. La diferencia importa cuando el panel
     * cuenta, y por eso no se colapsan las dos en un booleano.
     */
    public function haCaducado(): bool
    {
        return $this->fecha_caducidad !== null && $this->fecha_caducidad->isPast();
    }

    /** @param  Builder<$this>  $query */
    public function scopeCaducadas(Builder $query): void
    {
        $query->whereNotNull('fecha_caducidad')->whereDate('fecha_caducidad', '<', Carbon::today());
    }

    /**
     * Las que caducan dentro de los próximos `$dias`, sin contar las caducadas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePorCaducar(Builder $query, int $dias = 30): void
    {
        $query->whereNotNull('fecha_caducidad')
            ->whereDate('fecha_caducidad', '>=', Carbon::today())
            ->whereDate('fecha_caducidad', '<=', Carbon::today()->addDays($dias));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEvidencia::class,
            'periodicidad_renovacion' => PeriodicidadRenovacion::class,
            'fecha_obtencion' => 'date',
            'fecha_caducidad' => 'date',
            'tamano' => 'integer',
        ];
    }

    protected static function newFactory(): EvidenciaFactory
    {
        return EvidenciaFactory::new();
    }
}
