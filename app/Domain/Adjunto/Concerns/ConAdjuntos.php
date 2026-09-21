<?php

declare(strict_types=1);

namespace App\Domain\Adjunto\Concerns;

use App\Domain\Adjunto\Models\Adjunto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * El contrato del anfitrión de un adjunto.
 *
 * Existe porque **sin morph no hay un tipo común**: `SubirAdjunto` recibe una
 * persona o una acción formativa, y tipar el parámetro como `Model` a secas deja
 * a Larastan sin saber que existe `adjuntos()` — y a quien lea la firma sin
 * saber qué se le puede pasar. Con la interfaz, el parámetro es
 * `Model&ConAdjuntos` y las dos cosas quedan dichas.
 *
 * Lo implementa el trait `TieneAdjuntos`; un anfitrión nuevo usa los dos.
 *
 * `adjuntosCargados()` existe porque **la magia de Eloquent no cruza una
 * interfaz**: `$anfitrion->adjuntos` sobre el tipo intersección es una propiedad
 * que Larastan no encuentra. Dentro del trait sí se resuelve, así que el acceso
 * vive ahí y aquí sólo se declara.
 */
interface ConAdjuntos
{
    /** El nombre de la pivote que une este modelo con `adjuntos`. */
    public function tablaDeAdjuntos(): string;

    /** @return BelongsToMany<Adjunto, covariant \Illuminate\Database\Eloquent\Model> */
    public function adjuntos(): BelongsToMany;

    /** @return Collection<int, Adjunto> */
    public function adjuntosCargados(): Collection;
}
