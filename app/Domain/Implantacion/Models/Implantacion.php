<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Models;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Enums\Exigencia;
use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Categorizacion\Enums\OrigenExigencia;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Database\Factories\ImplantacionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El centro del modelo: une un requisito del catálogo global con un sistema de
 * una organización.
 *
 * Contesta las tres preguntas de cualquier auditoría — qué aplica, cómo se
 * cumple y dónde está la prueba — y tanto la Declaración de Aplicabilidad de ISO
 * como la del ENS son consultas sobre esta tabla, no documentos mantenidos a
 * mano.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $sistema_id
 * @property int $requisito_id
 * @property bool $aplica
 * @property ?string $justificacion
 * @property EstadoImplantacion $estado
 * @property ?NivelMadurez $nivel_madurez
 * @property ?OrigenExigencia $origen_exigencia
 * @property ?Dimension $dimension_moduladora
 */
class Implantacion extends Model
{
    /** @use HasFactory<ImplantacionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;

    protected $table = 'implantaciones';

    protected $fillable = [
        'organizacion_id',
        'sistema_id',
        'requisito_id',
        'aplica',
        'justificacion',
        'exigencia_calculada',
        'origen_exigencia',
        'dimension_moduladora',
        'estado',
        'nivel_madurez',
        'responsable_id',
        'fecha_objetivo',
        'notas',
    ];

    /** @return BelongsTo<Sistema, $this> */
    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    /** @return BelongsTo<Requisito, $this> */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return HasMany<ImplantacionTransicion, $this> */
    public function transiciones(): HasMany
    {
        return $this->hasMany(ImplantacionTransicion::class)->orderBy('created_at')->orderBy('id');
    }

    /** @param Builder<$this> $query */
    public function scopeAplicables(Builder $query): void
    {
        $query->where('aplica', true);
    }

    /** @param Builder<$this> $query */
    public function scopeDelSistema(Builder $query, int $sistemaId): void
    {
        $query->where('sistema_id', $sistemaId);
    }

    /**
     * El estado que tenía justo antes de marcarse `no_aplica`.
     *
     * Cuando una medida vuelve a exigirse tras una revaloración, se restaura
     * aquí: para eso se guarda el histórico. Devuelve `null` si nunca estuvo en
     * otro estado.
     */
    public function estadoPrevioANoAplica(): ?EstadoImplantacion
    {
        $transicion = $this->transiciones()
            ->where('estado_nuevo', EstadoImplantacion::NoAplica->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $transicion?->estado_anterior;
    }

    /** @return Attribute<?Exigencia, Exigencia|string|null> */
    protected function exigenciaCalculada(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor): ?Exigencia => $valor === null ? null : Exigencia::desde($valor),
            set: fn (Exigencia|string|null $valor): ?string => $valor === null ? null : (string) $valor,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aplica' => 'boolean',
            'estado' => EstadoImplantacion::class,
            'nivel_madurez' => NivelMadurez::class,
            'origen_exigencia' => OrigenExigencia::class,
            'dimension_moduladora' => Dimension::class,
            'fecha_objetivo' => 'date',
        ];
    }

    protected static function newFactory(): ImplantacionFactory
    {
        return ImplantacionFactory::new();
    }
}
