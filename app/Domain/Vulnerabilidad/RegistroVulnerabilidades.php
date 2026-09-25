<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\Panel\Reparto;
use App\Http\Resources\Panel\ResumenVulnerabilidadesPanel;

/**
 * Las cifras del registro de vulnerabilidades que suben al panel y al informe
 * de estado.
 *
 * **Un solo rojo: el plazo vencido.** Una crítica recién detectada no va mal, se
 * está atendiendo; una media con el plazo pasado sin arreglo, sí. Lo demás es
 * trabajo pendiente: lo crítico abierto, lo mitigado sin verificar y lo
 * aceptado, que conviene tener a la vista porque es riesgo asumido.
 */
final readonly class RegistroVulnerabilidades
{
    public function total(): int
    {
        return Vulnerabilidad::query()->vivas()->count();
    }

    /** @return list<Indicador> */
    public function alertas(): array
    {
        return [
            $this->indicador(
                'fuera_de_plazo',
                'Fuera de plazo',
                'fueraDePlazo',
                'caducada',
                'Sin arreglo y con el plazo de remediación que fija la organización para su severidad ya pasado.',
            ),
        ];
    }

    /** @return list<Indicador> */
    public function pendientes(): array
    {
        return [
            $this->indicador('criticas_abiertas', 'Críticas abiertas', 'criticasAbiertas', 'prioridad-critica', 'De severidad crítica y todavía sin arreglo.'),
            $this->indicador('sin_verificar', 'Mitigadas sin verificar', 'sinVerificar', 'en_revision', 'Se aplicó el arreglo y falta comprobar que la vulnerabilidad ya no está.'),
            $this->indicador('aceptadas', 'Aceptadas', 'aceptadas', 'planificado', 'No se corrigen, a sabiendas y con firma: es riesgo asumido.'),
        ];
    }

    public function paraElPanel(): ResumenVulnerabilidadesPanel
    {
        return new ResumenVulnerabilidadesPanel(
            total: Vulnerabilidad::query()->count(),
            vivas: $this->total(),
            fueraDePlazo: Vulnerabilidad::query()->fueraDePlazo()->count(),
            criticasAbiertas: Vulnerabilidad::query()->criticasAbiertas()->count(),
            sinVerificar: Vulnerabilidad::query()->sinVerificar()->count(),
            aceptadas: Vulnerabilidad::query()->aceptadas()->count(),
            porSeveridad: $this->porSeveridad(),
        );
    }

    /**
     * Las vivas por severidad, de la más grave a la menos.
     *
     * @return list<Reparto>
     */
    public function porSeveridad(): array
    {
        $cuentas = Vulnerabilidad::query()
            ->vivas()
            ->selectRaw('severidad, count(*) as total')
            ->groupBy('severidad')
            ->pluck('total', 'severidad');

        return array_map(
            static fn (Severidad $severidad): Reparto => new Reparto(
                clave: $severidad->value,
                etiqueta: $severidad->etiqueta(),
                valor: (int) ($cuentas[$severidad->value] ?? 0),
                tono: $severidad->tono(),
                filtro: "filter[vivas]=1&filter[severidad]={$severidad->value}",
            ),
            array_reverse(Severidad::cases()),
        );
    }

    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, string $ayuda): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: Vulnerabilidad::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/vulnerabilidades',
            ayuda: $ayuda,
        );
    }
}
