<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Models;

use App\Domain\Catalogo\Enums\TipoCorrespondencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correspondencia entre un requisito de un marco y otro de un marco distinto.
 * Es lo que permite que una evidencia se registre una vez y cuente para todos
 * los marcos donde aplique.
 *
 * @property TipoCorrespondencia $tipo_correspondencia
 * @property ?string $nota
 */
class Mapeo extends Model
{
    protected $table = 'mapeos';

    protected $fillable = ['requisito_origen_id', 'requisito_destino_id', 'tipo_correspondencia', 'nota'];

    /** @return BelongsTo<Requisito, $this> */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Requisito::class, 'requisito_origen_id');
    }

    /** @return BelongsTo<Requisito, $this> */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Requisito::class, 'requisito_destino_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_correspondencia' => TipoCorrespondencia::class,
        ];
    }
}
