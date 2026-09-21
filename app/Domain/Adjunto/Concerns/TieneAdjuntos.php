<?php

declare(strict_types=1);

namespace App\Domain\Adjunto\Concerns;

use App\Domain\Adjunto\Models\Adjunto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * El registro admite documentos subidos.
 *
 * El trait sólo declara la relación; **la pivote la nombra cada anfitrión**, y
 * eso es a propósito: sin un morph, la tabla de unión es explícita y su nombre
 * no se puede deducir de nada. Un anfitrión nuevo escribe su pivote en una
 * migración, declara aquí su nombre y ya está.
 *
 * `tablaDeAdjuntos()` la declara `ConAdjuntos` y la escribe cada anfitrión.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements ConAdjuntos
 */
trait TieneAdjuntos
{
    /** @return BelongsToMany<Adjunto, $this> */
    public function adjuntos(): BelongsToMany
    {
        return $this->belongsToMany(Adjunto::class, $this->tablaDeAdjuntos())
            ->withPivot('created_at')
            ->orderByDesc('adjuntos.created_at');
    }

    /**
     * Los adjuntos ya cargados, sin volver a consultar.
     *
     * Ver el motivo en `ConAdjuntos`: aquí dentro la propiedad mágica sí se
     * resuelve, porque el trait declara que extiende un modelo.
     *
     * @return Collection<int, Adjunto>
     */
    public function adjuntosCargados(): Collection
    {
        return $this->adjuntos;
    }
}
