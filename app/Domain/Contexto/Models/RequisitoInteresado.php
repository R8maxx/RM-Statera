<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Models;

use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use Database\Factories\Contexto\RequisitoInteresadoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Lo que una parte interesada espera o exige. La segunda mitad de la cláusula 4.2.
 *
 * El nombre lleva «interesado» y no es cosmético: `requisitos` a secas es el
 * catálogo normativo, que es **global y no lleva `organizacion_id`** (invariante
 * 2). Dos tablas de requisitos con el mismo nombre en dos ámbitos distintos es
 * cómo se acaba con una consulta cruzando la frontera sin que nadie lo vea.
 *
 * **Se ata a `implantaciones` y no a `requisitos`**, igual que las salvaguardas de
 * un riesgo: «el ENS exige control de acceso» y «lo tenemos puesto en este
 * sistema» no son la misma afirmación, y lo que contesta a «¿cómo atendéis lo que
 * os exige este regulador?» es la segunda. Es también lo que permite que la SoA
 * imprima «exigido por el regulador X» como justificación de inclusión, que es tan
 * legítima para ISO 6.1.3 d) como el tratamiento de un riesgo.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $parte_interesada_id
 * @property string $descripcion
 * @property NaturalezaRequisito $naturaleza
 * @property bool $es_climatico
 * @property ?string $referencia
 * @property ?string $como_se_atiende
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class RequisitoInteresado extends Model
{
    /** @use HasFactory<RequisitoInteresadoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'requisitos_interesados';

    protected $fillable = [
        'organizacion_id',
        'parte_interesada_id',
        'descripcion',
        'naturaleza',
        'es_climatico',
        'referencia',
        'como_se_atiende',
    ];

    /** @return BelongsTo<ParteInteresada, $this> */
    public function parteInteresada(): BelongsTo
    {
        return $this->belongsTo(ParteInteresada::class);
    }

    /**
     * Las implantaciones que lo cubren.
     *
     * @return BelongsToMany<Implantacion, $this>
     */
    public function implantaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            Implantacion::class,
            'implantacion_requisito_interesado',
            'requisito_interesado_id',
            'implantacion_id',
        )->withPivot(['vinculada_por_id', 'created_at']);
    }

    /**
     * Lo legal y lo contractual: lo que tiene consecuencia exigible.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeQueObligan(Builder $query): void
    {
        $query->whereIn('requisitos_interesados.naturaleza', array_map(
            static fn (NaturalezaRequisito $naturaleza): string => $naturaleza->value,
            array_filter(
                NaturalezaRequisito::cases(),
                static fn (NaturalezaRequisito $naturaleza): bool => $naturaleza->obliga(),
            ),
        ));
    }

    /**
     * Sin ninguna implantación detrás que diga cómo se atiende.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSinCubrir(Builder $query): void
    {
        $query->whereDoesntHave('implantaciones');
    }

    /**
     * Los que obligan y no están cubiertos.
     *
     * Va por partida doble —aquí y en `ParteInteresada::scopeConObligacionSinCubrir()`—
     * porque las dos preguntas son distintas: ésta cuenta requisitos y aquélla
     * partes, y un regulador con tres exigencias sin cubrir es una parte y tres
     * requisitos. El panel cuenta requisitos, que es lo que hay que atender.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeObligacionSinCubrir(Builder $query): void
    {
        $query->queObligan()->sinCubrir();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'naturaleza' => NaturalezaRequisito::class,
            'es_climatico' => 'boolean',
        ];
    }

    protected static function newFactory(): RequisitoInteresadoFactory
    {
        return RequisitoInteresadoFactory::new();
    }
}
