<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lo comercial de un cliente, que es de la plataforma (punto 54).
 *
 * El contacto de facturación lo ve también el cliente, en lectura; las notas,
 * nunca.
 *
 * @property int $id
 * @property int $organizacion_afectada_id
 * @property ?string $contacto_nombre
 * @property ?string $contacto_email
 * @property ?string $contacto_telefono
 * @property ?string $notas
 * @property ?int $actualizada_por
 * @property ?Carbon $updated_at
 */
class FichaComercial extends Model
{
    protected $table = 'fichas_comerciales';

    protected $fillable = [
        'organizacion_afectada_id',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'notas',
        'actualizada_por',
    ];
}
