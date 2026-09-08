<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use App\Domain\Catalogo\Enums\TipoRequisito;
use Database\Factories\Catalogo\RequisitoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Un requisito del catálogo: cláusula de la ISO, control de su Anexo A o medida
 * del Anexo II del ENS. Tabla única y jerarquía por `parent_id`
 * (`op` → `op.acc` → `op.acc.4`).
 *
 * @property int $id
 * @property int $marco_id
 * @property string $codigo
 * @property TipoRequisito $tipo
 * @property ?int $parent_id
 * @property string $titulo
 * @property ?string $descripcion
 * @property int $orden
 * @property array<string, mixed> $atributos
 * @property bool $vigente
 * @property ?string $huella
 */
class Requisito extends Model
{
    /** @use HasFactory<RequisitoFactory> */
    use HasFactory;

    protected $table = 'requisitos';

    protected $fillable = [
        'marco_id',
        'codigo',
        'tipo',
        'parent_id',
        'titulo',
        'descripcion',
        'orden',
        'atributos',
        'vigente',
        'retirado_en',
        'huella',
    ];

    /** @return BelongsTo<Marco, $this> */
    public function marco(): BelongsTo
    {
        return $this->belongsTo(Marco::class);
    }

    /** @return BelongsTo<self, $this> */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden')->orderBy('codigo');
    }

    /** @return HasMany<Refuerzo, $this> */
    public function refuerzos(): HasMany
    {
        return $this->hasMany(Refuerzo::class)->orderBy('orden')->orderBy('codigo');
    }

    /** @return HasMany<AplicabilidadEns, $this> */
    public function aplicabilidad(): HasMany
    {
        return $this->hasMany(AplicabilidadEns::class);
    }

    /** @return HasMany<Mapeo, $this> */
    public function mapeosSalientes(): HasMany
    {
        return $this->hasMany(Mapeo::class, 'requisito_origen_id');
    }

    /** @return HasMany<Mapeo, $this> */
    public function mapeosEntrantes(): HasMany
    {
        return $this->hasMany(Mapeo::class, 'requisito_destino_id');
    }

    /** @param Builder<$this> $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('vigente', true);
    }

    /** @param Builder<$this> $query */
    public function scopeDelMarco(Builder $query, string $codigoMarco): void
    {
        $query->whereHas('marco', fn (Builder $marco) => $marco->where('codigo', $codigoMarco));
    }

    /**
     * Subárbol completo a partir de un requisito, él incluido, mediante CTE
     * recursiva. Es una de las razones por las que el proyecto va sobre
     * PostgreSQL: en MySQL habría que resolverlo a mano o en PHP.
     *
     * Cada modelo devuelto lleva un atributo `profundidad` (0 en la raíz).
     *
     * @return Collection<int, self>
     */
    public static function subarbol(int $raizId): Collection
    {
        $filas = DB::select(<<<'SQL'
            WITH RECURSIVE arbol AS (
                SELECT r.*, 0 AS profundidad
                FROM requisitos r
                WHERE r.id = ?

                UNION ALL

                SELECT hijo.*, padre.profundidad + 1
                FROM requisitos hijo
                INNER JOIN arbol padre ON hijo.parent_id = padre.id
            )
            SELECT * FROM arbol
            ORDER BY profundidad, orden, codigo
        SQL, [$raizId]);

        return self::hydrate($filas);
    }

    /**
     * Ruta desde la raíz hasta este requisito, para migas de pan y para el
     * agrupado del catálogo. Devuelve de raíz a hoja.
     *
     * @return Collection<int, self>
     */
    public static function ruta(int $requisitoId): Collection
    {
        $filas = DB::select(<<<'SQL'
            WITH RECURSIVE ascendencia AS (
                SELECT r.*, 0 AS altura
                FROM requisitos r
                WHERE r.id = ?

                UNION ALL

                SELECT padre.*, hijo.altura + 1
                FROM requisitos padre
                INNER JOIN ascendencia hijo ON hijo.parent_id = padre.id
            )
            SELECT * FROM ascendencia
            ORDER BY altura DESC
        SQL, [$requisitoId]);

        return self::hydrate($filas);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoRequisito::class,
            'atributos' => 'array',
            'vigente' => 'boolean',
            'retirado_en' => 'datetime',
            'orden' => 'integer',
        ];
    }

    protected static function newFactory(): RequisitoFactory
    {
        return RequisitoFactory::new();
    }
}
