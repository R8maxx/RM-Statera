<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use App\Domain\Plataforma\Enums\HitoSuscripcion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un aviso de vencimiento ya mandado (punto 46): qué hito, para qué
 * vencimiento y cuándo. Existe para no mandarlo dos veces.
 *
 * @property int $id
 * @property int $organizacion_afectada_id
 * @property HitoSuscripcion $hito
 * @property Carbon $vence_en
 * @property Carbon $enviado_en
 */
class AvisoSuscripcion extends Model
{
    protected $table = 'avisos_suscripcion';

    public $timestamps = false;

    protected $fillable = ['organizacion_afectada_id', 'hito', 'vence_en', 'enviado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hito' => HitoSuscripcion::class,
            'vence_en' => 'datetime',
            'enviado_en' => 'datetime',
        ];
    }
}
