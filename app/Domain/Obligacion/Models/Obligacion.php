<?php

declare(strict_types=1);

namespace App\Domain\Obligacion\Models;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Obligacion\Cadencia;
use App\Domain\Obligacion\Enums\ReferenciaCumplimiento;
use Database\Factories\Obligacion\ObligacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una obligación periódica del catálogo: algo que hay que hacer cada tanto y que
 * no sale de ningún registro.
 *
 * **Vive en `app/Domain/Obligacion/` y no en `app/Domain/Catalogo/`, aunque sea
 * catálogo**, con el mismo reparto que `Amenaza`: lo que la agrupa con el
 * catálogo normativo es cómo se carga —YAML versionado, importador idempotente,
 * tabla global sin `organizacion_id`—; lo que la agrupa con los compromisos es
 * para qué sirve, y gana lo segundo.
 *
 * **Sin `PerteneceAOrganizacion` y sin `RegistraTraza`**, los dos a propósito y
 * por lo mismo que la amenaza: la tabla es global (invariante 2) y lo que cambia
 * el catálogo es el importador, de lo que dejan constancia el diff del comando y
 * el repositorio.
 *
 * @property int $id
 * @property string $codigo
 * @property ?int $marco_id
 * @property string $nombre
 * @property ?string $descripcion
 * @property ?string $base_legal
 * @property int $periodicidad_meses_sugerida
 * @property ?CategoriaEns $categoria_minima
 * @property ?ReferenciaCumplimiento $referencia_sugerida
 * @property int $orden
 * @property ?string $huella
 * @property bool $vigente
 */
class Obligacion extends Model
{
    /** @use HasFactory<ObligacionFactory> */
    use HasFactory;

    protected $table = 'obligaciones';

    protected $fillable = [
        'codigo',
        'marco_id',
        'nombre',
        'descripcion',
        'base_legal',
        'periodicidad_meses_sugerida',
        'categoria_minima',
        'referencia_sugerida',
        'orden',
        'huella',
        'vigente',
        'retirado_en',
    ];

    /** @return BelongsTo<Marco, $this> */
    public function marco(): BelongsTo
    {
        return $this->belongsTo(Marco::class);
    }

    /** @return HasMany<Compromiso, $this> */
    public function compromisos(): HasMany
    {
        return $this->hasMany(Compromiso::class);
    }

    /** @param Builder<$this> $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('vigente', true);
    }

    public function cadenciaSugerida(): Cadencia
    {
        return new Cadencia($this->periodicidad_meses_sugerida);
    }

    /**
     * Si muerde a un sistema de esta categoría.
     *
     * Sin `categoria_minima` muerde siempre: la mayoría de las obligaciones no
     * dependen de la categoría —el informe INES lo presenta cualquiera que esté
     * sujeto al ENS— y sólo las de continuidad y las de auditoría formal lo hacen.
     */
    public function exigibleEn(CategoriaEns $categoria): bool
    {
        return $this->categoria_minima === null || $categoria->alcanza($this->categoria_minima);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'categoria_minima' => CategoriaEns::class,
            'referencia_sugerida' => ReferenciaCumplimiento::class,
            'periodicidad_meses_sugerida' => 'integer',
            'orden' => 'integer',
            'vigente' => 'boolean',
            'retirado_en' => 'datetime',
        ];
    }

    protected static function newFactory(): ObligacionFactory
    {
        return ObligacionFactory::new();
    }
}
