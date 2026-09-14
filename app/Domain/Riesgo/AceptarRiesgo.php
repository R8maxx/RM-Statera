<?php

declare(strict_types=1);

namespace App\Domain\Riesgo;

use App\Domain\Riesgo\Excepciones\ValoracionNoAceptable;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * La firma: el propietario del riesgo acepta la exposición que queda.
 *
 * Es el acto que ISO 27001 6.1.3 f) exige y el que convierte un análisis en una
 * decisión. Por eso tiene permiso propio —`riesgos.aceptar`, aparte de
 * `riesgos.gestionar`—: un técnico registra y puntúa riesgos, y firmar que la
 * organización convive con uno no es lo mismo ni lo hace la misma persona.
 *
 * **Firmar vuelve la fila inmutable**, y lo impone un trigger de PostgreSQL. A
 * partir de aquí lo único que se le puede hacer es jubilarla volviendo a valorar,
 * que es como se cambia de opinión dejando constancia de las dos.
 *
 * Se exige residual declarado porque **lo que se acepta es lo que queda después
 * de tratar**, no lo que había al empezar. Firmar sobre el intrínseco sería
 * aceptar una cifra que la organización piensa bajar.
 */
final class AceptarRiesgo
{
    /**
     * @throws ValoracionNoAceptable
     */
    public function __invoke(Riesgo $riesgo, User $propietario, ?string $nota = null): RiesgoValoracion
    {
        $vigente = $riesgo->valoracionVigente;

        if ($vigente === null) {
            throw ValoracionNoAceptable::sinValorar($riesgo->codigo);
        }

        if ($vigente->estaAceptada()) {
            throw ValoracionNoAceptable::yaAceptada($riesgo->codigo);
        }

        if ($vigente->riesgo_residual === null) {
            throw ValoracionNoAceptable::sinResidual($riesgo->codigo);
        }

        $vigente->fill([
            'aceptada_por_id' => $propietario->id,
            'aceptada_en' => Carbon::today(),
            // Columna propia: la nota de la firma no pisa la de la valoración,
            // porque las escribieron dos personas en dos momentos y el auditor
            // quiere leer las dos.
            'nota_aceptacion' => $nota,
        ])->save();

        return $vigente->refresh();
    }
}
