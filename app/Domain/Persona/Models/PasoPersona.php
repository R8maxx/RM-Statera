<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use App\Domain\Persona\Enums\TipoPasoPersona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un paso de la checklist de incorporación o de salida de una persona.
 *
 * Es el patrón de `subtareas`, y por lo mismo: el orden lo decide quien redacta la
 * lista —los pasos de un alta son un procedimiento y no se ordenan solos por fecha
 * ni por título— y la lista se guarda entera en una sola ruta.
 *
 * **`hecho_en` no se vuelve a sellar** si ya estaba marcado: la fecha es cuándo se
 * hizo el paso, no cuándo se guardó la lista.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $persona_id
 * @property TipoPasoPersona $tipo
 * @property string $titulo
 * @property int $orden
 * @property ?Carbon $hecho_en
 */
class PasoPersona extends Model
{
    use PerteneceAOrganizacion;

    protected $table = 'pasos_persona';

    protected $fillable = [
        'organizacion_id',
        'persona_id',
        'tipo',
        'titulo',
        'orden',
        'hecho_en',
    ];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPasoPersona::class,
            'hecho_en' => 'datetime',
        ];
    }
}
