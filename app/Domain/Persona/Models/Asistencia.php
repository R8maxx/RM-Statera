<?php

declare(strict_types=1);

namespace App\Domain\Persona\Models;

use App\Domain\Organizacion\Concerns\PerteneceAOrganizacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Que a esta persona se la convocó a esta acción, y si fue.
 *
 * **`asistio` es una columna y no la ausencia de fila.** Convocar a alguien que no
 * fue es un hecho distinto de no haberlo convocado, y el segundo es el que un
 * auditor pregunta: sin la columna, las dos cosas se verían igual y «formación
 * impartida al 100 % de los convocados» saldría siempre.
 *
 * Sin `updated_at`: una asistencia se corrige cambiando el booleano, y cuándo se
 * apuntó lo dice `registrada_en`.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property int $accion_formativa_id
 * @property int $persona_id
 * @property bool $asistio
 * @property Carbon $registrada_en
 */
class Asistencia extends Model
{
    use PerteneceAOrganizacion;

    public $timestamps = false;

    protected $fillable = [
        'organizacion_id',
        'accion_formativa_id',
        'persona_id',
        'asistio',
        'registrada_en',
    ];

    /** @return BelongsTo<AccionFormativa, $this> */
    public function accionFormativa(): BelongsTo
    {
        return $this->belongsTo(AccionFormativa::class);
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'asistio' => 'boolean',
            'registrada_en' => 'datetime',
        ];
    }
}
