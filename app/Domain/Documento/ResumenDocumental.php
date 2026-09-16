<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Models\Documento;
use App\Http\Resources\Panel\Indicador;

/**
 * Qué pide acción hoy en la documentación.
 *
 * Va en `/documentos` y no en el panel, con el mismo reparto que ya rige en el
 * inventario: **el panel contesta cómo va la cosa y la tabla contesta qué pide
 * acción hoy**. Aquí no hay reparto que enseñar —cuántas políticas frente a
 * cuántos procedimientos no le importa a nadie—, sólo dos cifras que alguien
 * tiene que resolver.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y
 * no es comodidad: es lo que garantiza que pulsar una cifra enseñe exactamente
 * esa cifra. Con la condición escrita dos veces, el día que cambie una la tira
 * dirá 12 y la lista enseñará 9, y a partir de ahí nadie se fía de la tira.
 */
final class ResumenDocumental
{
    /**
     * Lo que va mal, o está esperando a alguien.
     *
     * Sólo dos, y ninguna es un reparto. **Un indicador a cero no ocupa tarjeta**:
     * de eso se ocupa la tira, que pinta una línea diciéndolo en vez de una fila
     * de ceros que hay que leerse entera para saber que no pasa nada.
     *
     * @return list<Indicador>
     */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'revision_vencida',
                'Revisión vencida',
                'revisionVencida',
                // Una revisión que se pasó de fecha es de las pocas cosas que van
                // mal de verdad, y es el mismo uso del rojo que ya tienen una
                // evidencia caducada y una tarea fuera de plazo.
                'caducada',
                'Aprobados cuya fecha de próxima revisión ya pasó. El documento sigue en vigor: lo que está vencido es haberlo vuelto a mirar.',
            ),
            $this->indicador(
                'en_revision',
                'Pendientes de aprobar',
                'enRevision',
                // Ni rojo ni verde: está esperando a una persona, no incumpliendo
                // nada. Es el mismo criterio que deja `bloqueada` en ámbar.
                'en_progreso',
                'Mandados a revisión y a la espera de firma. Hasta que se aprueban no son una entrega.',
            ),
        ];
    }

    private function indicador(
        string $clave,
        string $etiqueta,
        string $scope,
        string $tono,
        ?string $ayuda = null,
    ): Indicador {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Documento::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/documentos',
            ayuda: $ayuda,
        );
    }
}
