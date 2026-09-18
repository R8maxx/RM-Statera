<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Implantacion\Models\Implantacion;
use App\Models\User;

/**
 * Ata lo que exige una parte interesada a la medida que lo cubre.
 *
 * Es la costura que paga el módulo entero. A partir de aquí, «el ENS nos obliga
 * porque somos proveedores del sector público» deja de ser una frase en la
 * introducción de un documento y pasa a ser un vínculo que la Declaración de
 * Aplicabilidad puede imprimir como **justificación de inclusión**, al lado de
 * «Anexo A» y de «tratamiento del riesgo R-014». ISO 6.1.3 d) admite las tres.
 *
 * **Apunta a `implantaciones` y no a `requisitos`**, igual que las salvaguardas de
 * un riesgo: «el ENS exige control de acceso» y «lo tenemos puesto en este
 * sistema» no son la misma afirmación, y la pregunta de la cláusula 4.2 —«¿cómo
 * atendéis lo que os exigen?»— la contesta la segunda.
 *
 * **Sin doble vínculo**, a diferencia de `VincularAccionCorrectiva`. Allí hacía
 * falta porque una acción correctiva colgada sólo de la no conformidad no aparecía
 * en `implantacion_tarea` y el plan de adecuación imprimía «sin trabajo
 * planificado» sobre una medida que sí lo tenía. Aquí no hay segundo vínculo que
 * atar: esto no crea tareas, y ninguna cifra del producto cuenta filas de esta
 * pivote.
 */
final class VincularImplantacionARequisito
{
    public function vincular(RequisitoInteresado $requisito, Implantacion $implantacion, ?User $usuario = null): void
    {
        $requisito->implantaciones()->syncWithoutDetaching([
            $implantacion->id => ['vinculada_por_id' => $usuario?->id],
        ]);
    }

    public function desvincular(RequisitoInteresado $requisito, Implantacion $implantacion): void
    {
        $requisito->implantaciones()->detach($implantacion->id);
    }
}
