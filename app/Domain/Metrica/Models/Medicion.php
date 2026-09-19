<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Models;

use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Traza\Concerns\RegistraTraza;
use App\Models\User;
use Database\Factories\Metrica\MedicionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una cifra sellada: lo que valía el indicador en un periodo concreto.
 *
 * **No se recalcula.** Un indicador calculado propone su cifra al cerrar el
 * periodo y aquí se queda; volver a consultar la fuente en octubre devolvería la
 * de octubre y la fila de marzo mentiría. Es el mismo razonamiento que congela
 * `riesgo_valoraciones.salvaguardas`, `documento_versiones.instantanea`, la
 * exigencia de cada punto al cerrar una auditoría y la instantánea del análisis
 * del contexto. Cuatro precedentes, y éste es el quinto sitio donde una consulta
 * en vivo reescribiría el pasado.
 *
 * **Y aun así no lleva trigger de inmutabilidad, a diferencia de aquéllos.** Una
 * medición no la firma nadie y no se entrega sola a un auditor; lo que se
 * congela es el acta de la revisión por la dirección que la cita. Blindarla aquí
 * haría imposible corregir un dedazo en una medición manual, que es el caso
 * ordinario, sin proteger nada que no esté ya protegido. La frontera del módulo
 * es otra: **derivar en silencio, prohibido; corregir con autor y traza,
 * permitido** — de eso responde `RegistraTraza`.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $indicador_id
 * @property Carbon $periodo_inicio
 * @property Carbon $periodo_fin
 * @property Carbon $medida_en
 * @property float $valor
 * @property ?int $numerador
 * @property ?int $denominador
 * @property ?float $objetivo
 * @property OrigenMedicion $origen
 * @property ?string $nota
 * @property ?int $registrada_por_id
 */
class Medicion extends Model
{
    /** @use HasFactory<MedicionFactory> */
    use HasFactory;

    use PerteneceAOrganizacion;
    use RegistraTraza;

    protected $table = 'mediciones';

    protected $fillable = [
        'organizacion_id',
        'indicador_id',
        'periodo_inicio',
        'periodo_fin',
        'medida_en',
        'valor',
        'numerador',
        'denominador',
        'objetivo',
        'origen',
        'nota',
        'registrada_por_id',
    ];

    /** @return BelongsTo<Indicador, $this> */
    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Indicador::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_id');
    }

    /**
     * La fracción escrita, cuando la hay.
     *
     * «43 de 307» al lado del «14 %»: el porcentaje dice cuánto y la fracción
     * dice sobre qué. Una media no tiene numerador —no es una fracción, es un
     * promedio— y entonces se escribe «sobre 48», que es lo que
     * `madurezMedia`/`madurezEvaluadas` ya hacen en el panel.
     */
    public function fraccion(): ?string
    {
        if ($this->denominador === null) {
            return null;
        }

        return $this->numerador === null
            ? 'sobre '.$this->denominador
            : $this->numerador.' de '.$this->denominador;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
            'medida_en' => 'datetime',
            'valor' => 'decimal:2',
            'objetivo' => 'decimal:2',
            'numerador' => 'integer',
            'denominador' => 'integer',
            'origen' => OrigenMedicion::class,
        ];
    }

    protected static function newFactory(): MedicionFactory
    {
        return MedicionFactory::new();
    }
}
