<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Riesgo\CalculoRiesgo;
use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\Enums\NivelRiesgo;
use App\Domain\Riesgo\Metodologia;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Riesgo\RiesgoValoracionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una evaluación completa de un riesgo, congelada en el momento en que se hizo.
 *
 * **No es una transición, y por eso no se parece a `implantacion_transiciones`.**
 * Una transición registra un delta sobre un campo, y eso sirve cuando lo que
 * cambia es un enum pequeño. Una reevaluación de riesgo es un juicio nuevo y
 * entero sobre seis valores correlacionados, emitido contra una metodología que
 * puede haber cambiado entretanto. La especificación pide «histórico
 * **comparable**», y comparar marzo con octubre exige saber con qué escala se
 * midió marzo — cosa que una tabla de deltas no puede contestar.
 *
 * De ahí las dos instantáneas en JSONB, que no son redundantes con nada:
 * `escala` es la metodología del momento y `salvaguardas` son las implantaciones
 * tal como estaban. Sin la segunda, la fila **miente** en cuanto una implantación
 * cambie de estado el mes que viene — es el mismo motivo por el que el `.docx` de
 * un documento se construye desde la instantánea y no desde una consulta nueva.
 *
 * En cuanto se acepta o deja de ser la vigente, la fila es **inmutable por
 * trigger**, no por buena voluntad: una aceptación de riesgo que se puede
 * reescribir desde PHP no es una aceptación.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $riesgo_id
 * @property bool $vigente
 * @property int $probabilidad
 * @property int $impacto
 * @property array<string, int> $impacto_por_dimension
 * @property int $riesgo_intrinseco
 * @property ?int $probabilidad_residual
 * @property ?int $impacto_residual
 * @property ?int $riesgo_residual
 * @property ?string $justificacion_residual
 * @property DecisionRiesgo $decision
 * @property array<string, mixed> $escala
 * @property list<array<string, mixed>> $salvaguardas
 * @property ?int $valorada_por_id
 * @property Carbon $valorada_en
 * @property ?int $aceptada_por_id
 * @property ?Carbon $aceptada_en
 * @property ?string $nota
 * @property ?string $nota_aceptacion
 */
class RiesgoValoracion extends Model
{
    /** @use HasFactory<RiesgoValoracionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'riesgo_valoraciones';

    /** Histórico: se crea y no se vuelve a tocar. No hay `updated_at`. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'organizacion_id',
        'riesgo_id',
        'vigente',
        'probabilidad',
        'impacto',
        'impacto_por_dimension',
        'riesgo_intrinseco',
        'probabilidad_residual',
        'impacto_residual',
        'riesgo_residual',
        'justificacion_residual',
        'decision',
        'escala',
        'salvaguardas',
        'valorada_por_id',
        'valorada_en',
        'aceptada_por_id',
        'aceptada_en',
        'nota',
        'nota_aceptacion',
    ];

    /** @return BelongsTo<Riesgo, $this> */
    public function riesgo(): BelongsTo
    {
        return $this->belongsTo(Riesgo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function valoradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valorada_por_id');
    }

    /** @return BelongsTo<User, $this> */
    public function aceptadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aceptada_por_id');
    }

    public function estaAceptada(): bool
    {
        return $this->aceptada_en !== null;
    }

    /** Si todavía se puede corregir. Es la misma ventana que abre el trigger. */
    public function esEditable(): bool
    {
        return $this->vigente && ! $this->estaAceptada();
    }

    /**
     * Si el residual declarado dice que el tratamiento ha servido de algo.
     *
     * Un residual igual al intrínseco no es una rebaja: es decir que no se ha
     * conseguido bajar nada, que es una respuesta legítima y no una
     * contradicción.
     */
    public function rebajaElRiesgo(): bool
    {
        return $this->riesgo_residual !== null && $this->riesgo_residual < $this->riesgo_intrinseco;
    }

    /** La exposición de hoy: el residual si se ha declarado, y si no el intrínseco. */
    public function exposicion(): int
    {
        return $this->riesgo_residual ?? $this->riesgo_intrinseco;
    }

    /**
     * La metodología con la que se midió, releída de la instantánea.
     *
     * **Nunca la vigente.** Interpretar un número de marzo con la escala de
     * octubre es exactamente lo que esta columna existe para impedir.
     */
    public function metodologiaCongelada(): Metodologia
    {
        return Metodologia::desdeArray($this->escala);
    }

    public function nivelIntrinseco(): NivelRiesgo
    {
        return (new CalculoRiesgo)->nivel($this->riesgo_intrinseco, $this->metodologiaCongelada());
    }

    public function nivelResidual(): ?NivelRiesgo
    {
        if ($this->riesgo_residual === null) {
            return null;
        }

        return (new CalculoRiesgo)->nivel($this->riesgo_residual, $this->metodologiaCongelada());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vigente' => 'boolean',
            'probabilidad' => 'integer',
            'impacto' => 'integer',
            'impacto_por_dimension' => 'array',
            'riesgo_intrinseco' => 'integer',
            'probabilidad_residual' => 'integer',
            'impacto_residual' => 'integer',
            'riesgo_residual' => 'integer',
            'decision' => DecisionRiesgo::class,
            'escala' => 'array',
            'salvaguardas' => 'array',
            'valorada_en' => 'date',
            'aceptada_en' => 'date',
        ];
    }

    protected static function newFactory(): RiesgoValoracionFactory
    {
        return RiesgoValoracionFactory::new();
    }
}
