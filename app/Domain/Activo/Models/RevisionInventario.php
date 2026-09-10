<?php

declare(strict_types=1);

namespace App\Domain\Activo\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Models\User;
use Database\Factories\RevisionInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una revisión del inventario: cuándo se miró, quién, qué salió y qué se acordó.
 *
 * `A.5.9` de ISO y `op.exp.1` del ENS no piden un inventario, piden un
 * inventario **mantenido**. Un listado impecable sin constancia de revisión es
 * un listado del que nadie sabe de cuándo es, y eso es precisamente lo que un
 * auditor apunta como no conformidad.
 *
 * `altas` y `bajas` son lo que contó quien revisó, no un cálculo sobre la traza:
 * es la cifra que esa persona firmó ese día. Si mañana alguien da de alta un
 * activo con fecha anterior, este número no cambia — y no debe cambiar.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property Carbon $fecha
 * @property ?int $responsable_id
 * @property string $alcance
 * @property int $altas
 * @property int $bajas
 * @property ?string $desviaciones
 * @property ?string $acciones
 */
class RevisionInventario extends Model
{
    /** @use HasFactory<RevisionInventarioFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'revisiones_inventario';

    protected $fillable = [
        'organizacion_id',
        'fecha',
        'responsable_id',
        'alcance',
        'altas',
        'bajas',
        'desviaciones',
        'acciones',
    ];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Una revisión que no anotó ninguna desviación no es necesariamente una
     * revisión limpia: puede ser una que nadie completó. Se distingue en la
     * interfaz para que la diferencia se vea.
     */
    public function tieneHallazgos(): bool
    {
        return filled($this->desviaciones);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'altas' => 'integer',
            'bajas' => 'integer',
        ];
    }

    protected static function newFactory(): RevisionInventarioFactory
    {
        return RevisionInventarioFactory::new();
    }
}
