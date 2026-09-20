<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Models\Mejora;
use App\Http\Resources\Panel\Indicador;

/**
 * Lo que pide atención en el registro de oportunidades de mejora.
 *
 * **Cada indicador cuenta con el mismo scope que usa su filtro de la tabla**, y la
 * clave del indicador es la del filtro: eso es lo que garantiza que pulsar la
 * cifra enseñe exactamente esa cifra.
 *
 * **Y este registro no tiene `alertas()`**, a diferencia de todos los demás. Es
 * deliberado y es la decisión que define el módulo: ninguna cifra de aquí va mal
 * de verdad. Una mejora que no se ha hecho no incumple nada —la 10.1 pide mejorar
 * de forma continua, no tener cero ideas pendientes—, y pintar de rojo lo que
 * alguien apuntó voluntariamente es la forma más rápida de que deje de apuntarlo.
 * Lo que sí hay es lo que conviene mirar antes de una revisión por la dirección.
 */
final readonly class RegistroMejoras
{
    public function total(): int
    {
        return Mejora::query()->count();
    }

    /**
     * Lo que conviene mirar, y ninguna en rojo. Ver la cabecera.
     *
     * @return list<Indicador>
     */
    public function pendientes(): array
    {
        return [
            $this->indicador(
                'abiertas',
                'Abiertas',
                'abiertas',
                'en_progreso',
                'Propuestas y en curso: las que siguen vivas.',
            ),
            $this->indicador(
                'sin_empezar',
                'Sin empezar',
                'sinEmpezar',
                'planificado',
                'Apuntadas y todavía sin mover. Un buzón de ideas al que nadie vuelve no es mejora continua.',
            ),
            $this->indicador(
                'sin_trabajo',
                'Sin nada en marcha',
                'sinTrabajo',
                'no_iniciado',
                'Abiertas sin ninguna tarea viva detrás.',
            ),
            $this->indicador(
                'pasadas_de_fecha',
                'Fuera de la fecha prevista',
                'pasadasDeFecha',
                'no_iniciado',
                'Abiertas cuya fecha prevista ya pasó. No incumple nada: nadie se comprometió a ellas.',
            ),
        ];
    }

    /**
     * El reparto por estado, para la ficha del registro.
     *
     * **Con los estados cerrados dentro**, como en no conformidades y objetivos:
     * la pregunta es «de las que hemos apuntado, cuántas hemos llegado a hacer»,
     * que es lo que la revisión por la dirección lee de la 10.1.
     *
     * @return array<string, int>
     */
    public function porEstado(): array
    {
        /** @var array<string, int> $conteos */
        $conteos = Mejora::query()
            ->selectRaw('estado as clave, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'clave')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $reparto = [];

        foreach (EstadoMejora::cases() as $estado) {
            $reparto[$estado->value] = $conteos[$estado->value] ?? 0;
        }

        return $reparto;
    }

    /**
     * La clave del indicador **es** la del filtro, y el scope **es** el tercer
     * argumento de su `Filtro::porScope()`.
     */
    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Mejora::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/mejoras',
            ayuda: $ayuda,
        );
    }
}
