<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Models;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Riesgo\Enums\GrupoAmenaza;
use Database\Factories\Riesgo\AmenazaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una amenaza del catálogo de MAGERIT.
 *
 * **Vive en `app/Domain/Riesgo/` y no en `app/Domain/Catalogo/`, aunque sea
 * catálogo.** Lo que la agrupa con el catálogo normativo es cómo se carga —YAML
 * versionado, importador idempotente, tabla global sin `organizacion_id`—; lo que
 * la agrupa con los riesgos es para qué sirve. Gana el segundo, porque nadie va a
 * buscar «amenaza» en el contexto que contiene marcos, requisitos y mapeos. El
 * importador es de `Catalogo` y el dato es de `Riesgo`, que es exactamente la
 * relación que hay entre los dos módulos.
 *
 * **Sin `PerteneceAOrganizacion` y sin `RegistraTraza`, los dos a propósito.** El
 * primero porque la tabla es global (invariante 2) y añadirlo haría que el scope
 * buscara una columna que no existe. El segundo porque la traza de auditoría
 * registra lo que hace una organización con sus datos, y aquí no hay ninguna: lo
 * que cambia el catálogo es el importador, y de eso deja constancia el diff del
 * comando y el propio repositorio de Git.
 *
 * @property int $id
 * @property string $codigo
 * @property GrupoAmenaza $grupo
 * @property string $nombre
 * @property ?string $descripcion
 * @property list<string> $dimensiones
 * @property list<string> $tipos_activo
 * @property int $orden
 * @property ?string $huella
 * @property bool $vigente
 */
class Amenaza extends Model
{
    /** @use HasFactory<AmenazaFactory> */
    use HasFactory;

    protected $table = 'amenazas';

    protected $fillable = [
        'codigo',
        'grupo',
        'nombre',
        'descripcion',
        'dimensiones',
        'tipos_activo',
        'orden',
        'huella',
        'vigente',
        'retirado_en',
    ];

    /** @return HasMany<Riesgo, $this> */
    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class);
    }

    /** @param Builder<$this> $query */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('vigente', true);
    }

    /**
     * Las que tiene sentido plantear sobre un tipo de activo.
     *
     * Va por contención de JSONB con índice GIN, no por `LIKE`: una amenaza
     * declarada sobre `soportes` no debe salir al preguntar por `software`, y con
     * `LIKE '%software%'` saldría.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeParaTipo(Builder $query, TipoActivo $tipo): void
    {
        $query->whereJsonContains('tipos_activo', $tipo->value);
    }

    /** @param Builder<$this> $query */
    public function scopeSobreDimension(Builder $query, Dimension $dimension): void
    {
        $query->whereJsonContains('dimensiones', $dimension->value);
    }

    /**
     * Las dimensiones sobre las que puede actuar, ya resueltas.
     *
     * Se descarta en silencio lo que no reconozca el enum: el catálogo lo valida
     * el importador al entrar, y una dimensión rara que se colara no debe reventar
     * la ficha de un riesgo meses después.
     *
     * @return list<Dimension>
     */
    public function dimensionesAfectadas(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $codigo): ?Dimension => Dimension::tryFrom($codigo),
            $this->dimensiones,
        )));
    }

    /** @return list<TipoActivo> */
    public function tiposDeActivo(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $codigo): ?TipoActivo => TipoActivo::tryFrom($codigo),
            $this->tipos_activo,
        )));
    }

    /** «E.1 — Errores de los usuarios», que es como la nombra el analista. */
    public function etiqueta(): string
    {
        return "{$this->codigo} — {$this->nombre}";
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grupo' => GrupoAmenaza::class,
            'dimensiones' => 'array',
            'tipos_activo' => 'array',
            'vigente' => 'boolean',
            'retirado_en' => 'datetime',
            'orden' => 'integer',
        ];
    }

    protected static function newFactory(): AmenazaFactory
    {
        return AmenazaFactory::new();
    }
}
