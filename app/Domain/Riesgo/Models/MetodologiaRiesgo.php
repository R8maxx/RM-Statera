<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Riesgo\EscalaRiesgo;
use App\Domain\Riesgo\Metodologia;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Riesgo\MetodologiaRiesgoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La metodología de análisis de riesgos que ha decidido una organización.
 *
 * **Una fila como mucho por organización, y puede no haberla.** Ausencia significa
 * «no lo he decidido, vale lo que venga de fábrica», exactamente igual que una
 * sección narrativa sin fila resuelve hasta `TextosDeFabrica`. Por eso
 * `GuardarMetodologia` borra la fila cuando coincide con la de fábrica: sin eso,
 * abrir la pantalla y darle a guardar congelaría la metodología de esa
 * organización y dejaría de recibir cualquier mejora futura, sin haberlo decidido
 * y sin enterarse.
 *
 * El modelo guarda; quien interpreta es el value object `Metodologia`, que es el
 * que valida que los umbrales tengan sentido y el que se congela en cada
 * valoración.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $nombre
 * @property ?string $referencia
 * @property list<array{valor: int, etiqueta: string, descripcion: string|null}> $escala_probabilidad
 * @property list<array{valor: int, etiqueta: string, descripcion: string|null}> $escala_impacto
 * @property int $umbral_aceptacion
 * @property int $umbral_critico
 * @property int $periodicidad_revision_meses
 * @property ?int $aprobada_por_id
 * @property ?Carbon $aprobada_en
 * @property ?string $notas
 */
class MetodologiaRiesgo extends Model
{
    /** @use HasFactory<MetodologiaRiesgoFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'metodologias_riesgo';

    protected $fillable = [
        'organizacion_id',
        'nombre',
        'referencia',
        'escala_probabilidad',
        'escala_impacto',
        'umbral_aceptacion',
        'umbral_critico',
        'periodicidad_revision_meses',
        'aprobada_por_id',
        'aprobada_en',
        'notas',
    ];

    /** @return BelongsTo<User, $this> */
    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por_id');
    }

    /**
     * El value object, que es lo que usa todo el dominio.
     *
     * Aquí es donde una fila corrupta —escala con un hueco, umbral imposible—
     * se convierte en excepción. Pasa siempre por el mismo sitio que la de
     * fábrica, así que no hay dos caminos con validaciones distintas.
     */
    public function aValueObject(): Metodologia
    {
        return new Metodologia(
            nombre: $this->nombre,
            referencia: $this->referencia,
            probabilidad: EscalaRiesgo::desdeArray($this->escala_probabilidad, 'escala de probabilidad'),
            impacto: EscalaRiesgo::desdeArray($this->escala_impacto, 'escala de impacto'),
            umbralAceptacion: $this->umbral_aceptacion,
            umbralCritico: $this->umbral_critico,
            periodicidadRevisionMeses: $this->periodicidad_revision_meses,
            esDeFabrica: false,
            // La clave foránea va con `nullOnDelete`, así que un id presente
            // implica que la fila del usuario sigue ahí.
            aprobadaPor: $this->aprobada_por_id === null ? null : $this->aprobadaPor->name,
            aprobadaEn: $this->aprobada_en,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'escala_probabilidad' => 'array',
            'escala_impacto' => 'array',
            'umbral_aceptacion' => 'integer',
            'umbral_critico' => 'integer',
            'periodicidad_revision_meses' => 'integer',
            'aprobada_en' => 'date',
        ];
    }

    protected static function newFactory(): MetodologiaRiesgoFactory
    {
        return MetodologiaRiesgoFactory::new();
    }
}
