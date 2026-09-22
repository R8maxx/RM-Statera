<?php

declare(strict_types=1);

namespace App\Domain\Aviso;

/**
 * Lo que vence, repartido en las preguntas distintas que plantea.
 *
 * Dos ejes. Por un lado, **cada fuente va aparte**: una evidencia caducada es una
 * prueba que ya no prueba, una tarea vencida es trabajo que no se hizo y una
 * revisión documental vencida es un documento que nadie ha vuelto a mirar desde
 * que se firmó. Se arreglan de formas distintas y las lleva gente distinta —la
 * última, quien tiene potestad para aprobar—. Por otro, dentro de cada una, **lo
 * pasado y lo inminente**: lo primero es un incumplimiento hoy y lo segundo es
 * algo que planificar, y mezclarlos obliga a mirarse las fechas una a una para
 * saber si hay que correr.
 *
 * **Un mapa por fuente y no una propiedad por grupo.** Eran seis propiedades
 * fijas —`evidenciasCaducadas`, `tareasVencidas`…— y con siete fuentes serían
 * catorce, con `pasados()` y `total()` sumando a mano una por una. Ése es el
 * fallo caro del módulo y es silencioso: olvidar una fuente en `pasados()` no
 * rompe nada, sólo hace que **el asunto del correo diga «3 pasadas de fecha»
 * habiendo 9**. Con el mapa, sumar es recorrer, y hay un test que siembra un
 * vencido de cada caso de `Fuente` y comprueba que salen todos.
 */
final readonly class Vencimientos
{
    /**
     * @param  array<string, array{pasados: list<Vencimiento>, proximos: list<Vencimiento>}>  $porFuente
     */
    public function __construct(
        public array $porFuente,
        public int $dias,
    ) {}

    /** @return list<Vencimiento> */
    public function pasadosDe(Fuente $fuente): array
    {
        return $this->porFuente[$fuente->value]['pasados'] ?? [];
    }

    /** @return list<Vencimiento> */
    public function proximosDe(Fuente $fuente): array
    {
        return $this->porFuente[$fuente->value]['proximos'] ?? [];
    }

    /**
     * Las fuentes que traen algo pasado de fecha, en orden de declaración.
     *
     * @return list<Fuente>
     */
    public function fuentesConPasados(): array
    {
        return array_values(array_filter(
            Fuente::cases(),
            fn (Fuente $fuente): bool => $this->pasadosDe($fuente) !== [],
        ));
    }

    public function hayAlgo(): bool
    {
        return $this->total() > 0;
    }

    /** Lo que ya se pasó: es lo que decide el asunto del correo. */
    public function pasados(): int
    {
        return $this->contar('pasados');
    }

    public function total(): int
    {
        return $this->pasados() + $this->contar('proximos');
    }

    private function contar(string $mitad): int
    {
        $total = 0;

        foreach ($this->porFuente as $grupos) {
            $total += count($grupos[$mitad] ?? []);
        }

        return $total;
    }
}
