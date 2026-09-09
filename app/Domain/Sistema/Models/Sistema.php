<?php

declare(strict_types=1);

namespace App\Domain\Sistema\Models;

use App\Domain\Auditoria\Concerns\RegistraTraza;
use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Catalogo\Models\PerfilCumplimiento;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Enums\EstadoSistema;
use Database\Factories\SistemaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un SGSI de ISO o un sistema del ENS: la unidad de alcance y de certificación.
 *
 * La CATEGORÍA no es una columna. Se deriva de las cinco filas de
 * `valoracion_dimensiones` (invariante 4: la aplicabilidad se deriva, no se
 * selecciona). Guardar una copia sería abrir la puerta a que se desincronice de
 * la valoración que la justifica, que es justo lo que el auditor contrasta.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $marco_id
 * @property string $codigo
 * @property string $nombre
 * @property EstadoSistema $estado
 * @property ?int $perfil_id
 */
class Sistema extends Model
{
    /** @use HasFactory<SistemaFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'sistemas';

    protected $fillable = [
        'organizacion_id',
        'marco_id',
        'codigo',
        'nombre',
        'descripcion',
        'estado',
        'alcance_declarado',
        'exclusiones_justificadas',
        'perfil_id',
    ];

    /** @return BelongsTo<Marco, $this> */
    public function marco(): BelongsTo
    {
        return $this->belongsTo(Marco::class);
    }

    /** @return BelongsTo<PerfilCumplimiento, $this> */
    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilCumplimiento::class, 'perfil_id');
    }

    /** @return HasMany<ValoracionDimension, $this> */
    public function valoraciones(): HasMany
    {
        return $this->hasMany(ValoracionDimension::class);
    }

    /** @return HasMany<Implantacion, $this> */
    public function implantaciones(): HasMany
    {
        return $this->hasMany(Implantacion::class);
    }

    /**
     * La valoración de las cinco dimensiones, como value object. Es la entrada
     * del motor de categorización.
     *
     * Las dimensiones sin fila valen `na`: no valorar una dimensión y valorarla
     * como no aplicable son lo mismo a efectos del Anexo I.
     */
    public function valoracion(): ValoracionDimensiones
    {
        $niveles = [];

        foreach ($this->valoraciones as $valoracion) {
            $niveles[$valoracion->dimension->value] = $valoracion->nivel;
        }

        return ValoracionDimensiones::desdeArray($niveles);
    }

    /** Derivada: el máximo de las cinco dimensiones. Nula si el ENS no aplica. */
    public function categoria(): ?CategoriaEns
    {
        return $this->valoracion()->categoria();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoSistema::class,
        ];
    }

    protected static function newFactory(): SistemaFactory
    {
        return SistemaFactory::new();
    }
}
